<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PaymentLifecycle;
use App\Platform\TerritoryScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentService
{
    public function __construct(private readonly WalletLedgerService $wallets) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'methods' => array_map(fn (string $method) => [
                'method' => $method,
                'gateway' => PaymentLifecycle::needsWebhook($method),
                'capture' => $method === 'wallet' ? 'ledger' : ($method === 'cash' ? 'confirm_cash' : 'webhook'),
            ], PaymentLifecycle::METHODS),
            'paymentLifecycle' => PaymentLifecycle::PAYMENT_STEPS,
            'refundLifecycle' => PaymentLifecycle::REFUND_STEPS,
            'note' => 'Capture is server-side only. Frontend success is ignored. UPI/card require a signed webhook.',
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(User $operator, array $query = []): array
    {
        abort_unless($operator->can('payments.view'), 403);
        $q = $this->scoped($operator);
        if (! empty($query['status'])) {
            $status = (string) $query['status'];
            if (PaymentLifecycle::isSuccess($status) || $status === 'success') {
                $q->whereIn('payments.status', PaymentLifecycle::SUCCESS_ALIASES);
            } else {
                $q->where('payments.status', $status);
            }
        }
        if (! empty($query['method'])) {
            $q->where('payments.method', strtolower((string) $query['method']));
        }
        if (! empty($query['kind'])) {
            $q->where('payments.kind', $query['kind']);
        }
        if (! empty($query['q'])) {
            $term = '%'.$query['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('payments.public_ref', 'like', $term)
                    ->orWhere('payments.gateway_payment_id', 'like', $term)
                    ->orWhere('payments.note', 'like', $term);
            });
        }

        return $q->orderByDesc('payments.id')->limit(200)->get()->map(fn ($row) => $this->presentPayment($row))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        abort_unless($operator->can('payments.view'), 403);
        $row = $this->scoped($operator)->where('payments.id', $id)->first();
        abort_unless($row, 404, 'Payment not found');
        $events = [];
        if ($this->hasTable('payment_events')) {
            $events = $this->db()->table('payment_events')->where('payment_id', $id)->orderByDesc('id')->limit(40)->get()
                ->map(fn ($event) => [
                    'id' => (string) $event->id,
                    'source' => $event->source,
                    'eventType' => $event->event_type,
                    'gatewayEventId' => $event->gateway_event_id ?? null,
                    'signatureValid' => (bool) ($event->signature_valid ?? false),
                    'createdAt' => $event->created_at,
                ])->all();
        }
        $refunds = $this->db()->table('payments')->where('parent_id', $id)->orderBy('id')->get()
            ->map(fn ($item) => $this->presentPayment($item))->all();

        return $this->presentPayment($row) + [
            'events' => $events,
            'refunds' => $refunds,
            'invoice' => $row->invoice_id ? $this->presentInvoice($this->db()->table('invoices')->where('id', $row->invoice_id)->first()) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoices(User $operator): array
    {
        abort_unless($operator->can('payments.view'), 403);
        if (! $this->hasTable('invoices')) {
            return [];
        }
        $q = $this->db()->table('invoices');
        if (! TerritoryScope::isUnrestricted($operator)) {
            $q->whereExists(function ($sub) use ($operator) {
                $sub->selectRaw('1')->from('payments')->whereColumn('payments.invoice_id', 'invoices.id');
                $this->constrainPayments($sub, $operator);
            });
        }

        return $q->orderByDesc('id')->limit(200)->get()->map(fn ($row) => $this->presentInvoice($row))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function invoice(User $operator, int $id): array
    {
        abort_unless($operator->can('payments.view'), 403);
        $row = $this->db()->table('invoices')->where('id', $id)->first();
        abort_unless($row, 404, 'Invoice not found');
        $payments = $this->scoped($operator)->where('payments.invoice_id', $id)->orderBy('payments.id')->get()
            ->map(fn ($item) => $this->presentPayment($item))->all();
        abort_unless($payments !== [] || TerritoryScope::isUnrestricted($operator), 404, 'Invoice not found');

        return $this->presentInvoice($row) + ['payments' => $payments];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function initiate(User $operator, array $input): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('bookings.manage') || $operator->can('platform.admin'), 403);

        return $this->db()->transaction(fn () => $this->initiateInTx($operator, $input));
    }

    public function confirmCash(User $operator, int $id): array
    {
        abort_unless($this->canConfirmCash($operator), 403, 'Cash must be confirmed on the server by driver or ops');

        return $this->db()->transaction(fn () => $this->captureInTx($operator, $id, 'cash_confirm'));
    }

    public function markFailed(User $operator, int $id, array $data = []): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $row = $this->mustSee($operator, $id);
        abort_unless(PaymentLifecycle::isAwaitingCapture((string) $row->status), 422, 'Only awaiting payments can fail');
        $this->db()->table('payments')->where('id', $id)->update([
            'status' => 'failed',
            'failure_code' => $data['code'] ?? 'gateway_failed',
            'failure_note' => $data['note'] ?? 'Payment failed',
            'updated_at' => now(),
        ]);
        $this->recordEvent($id, 'api', 'payment.failed', ['code' => $data['code'] ?? 'gateway_failed']);

        return $this->one($operator, $id);
    }

    /**
     * Client apps must never flip a payment to success.
     */
    public function rejectClientCapture(): never
    {
        abort(422, 'Never mark payment successful based only on frontend response. Capture requires cash confirm, wallet ledger, or a signed webhook.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function requestRefund(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $payment = $this->mustSee($operator, $id);
        abort_unless(in_array($payment->kind, ['payment', 'cancellation_charge'], true), 422, 'Refunds apply to captured charges');
        abort_unless(PaymentLifecycle::canRefund((string) $payment->status), 422, 'Payment is not refundable');
        $already = (int) $this->db()->table('payments')
            ->where('parent_id', $id)
            ->whereIn('kind', ['refund', 'partial_refund'])
            ->whereNotIn('status', ['failed'])
            ->sum('amount_paise');
        $remaining = (int) $payment->amount_paise - $already;
        $amount = (int) ($data['amount_paise'] ?? $remaining);
        abort_unless($amount > 0 && $amount <= $remaining, 422, 'Refund amount is not valid');
        $kind = $amount < (int) $payment->amount_paise ? 'partial_refund' : 'refund';
        $payload = [
            'public_ref' => $this->newRef('KCR'),
            'customer_id' => $payment->customer_id,
            'booking_id' => $payment->booking_id,
            'invoice_id' => $payment->invoice_id ?? null,
            'parent_id' => $payment->id,
            'method' => $payment->method,
            'kind' => $kind,
            'intent' => 'refund',
            'amount_paise' => $amount,
            'status' => 'requested',
            'gateway' => $payment->gateway ?? 'none',
            'note' => $data['reason'] ?? 'Refund of '.$payment->public_ref,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $refundId = $this->db()->table('payments')->insertGetId($payload);
        $this->recordEvent((int) $refundId, 'api', 'refund.requested', ['parent' => $payment->public_ref, 'amount' => $amount]);

        return $this->one($operator, $refundId);
    }

    public function advanceRefund(User $operator, int $id, string $to): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $row = $this->mustSee($operator, $id);
        abort_unless(in_array($row->kind, ['refund', 'partial_refund'], true), 422, 'Not a refund');
        $map = [
            'approved' => ['from' => ['requested'], 'event' => 'refund.approved'],
            'processing' => ['from' => ['approved'], 'event' => 'refund.processing'],
            'completed' => ['from' => ['approved', 'processing'], 'event' => 'refund.completed'],
        ];
        abort_unless(isset($map[$to]), 422, 'Unknown refund step');
        abort_unless(in_array((string) $row->status, $map[$to]['from'], true), 422, 'Refund cannot move to '.$to);

        return $this->db()->transaction(function () use ($operator, $row, $to, $map) {
            if ($to === 'completed') {
                return $this->completeRefundInTx($operator, $row);
            }
            $this->db()->table('payments')->where('id', $row->id)->update([
                'status' => $to,
                'updated_at' => now(),
            ]);
            $this->recordEvent((int) $row->id, 'api', $map[$to]['event'], []);

            return $this->one($operator, (int) $row->id);
        });
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function handleWebhook(string $provider, ?string $signature, array $body): array
    {
        $event = (string) ($body['event'] ?? '');
        $paymentRef = (string) ($body['paymentRef'] ?? '');
        $amountPaise = (int) ($body['amountPaise'] ?? 0);
        $canonical = PaymentLifecycle::webhookCanonical($event, $paymentRef, $amountPaise);
        $secret = (string) config('karnacab.payment_webhook_secret');
        abort_unless(PaymentLifecycle::verifyWebhook($secret, $canonical, $signature), 401, 'Invalid webhook signature');
        $expected = (string) config('karnacab.payment_gateway', 'demo');
        abort_unless(in_array($provider, ['demo', $expected], true), 422, 'Unknown payment gateway');
        $payment = $this->db()->table('payments')->where('public_ref', $paymentRef)->first();
        abort_unless($payment, 404, 'Payment not found');
        $gatewayEventId = $body['gatewayEventId'] ?? ($provider.':'.$event.':'.$paymentRef.':'.$amountPaise);
        try {
            $this->recordEvent((int) $payment->id, 'webhook', $event, $body, $gatewayEventId, true);
        } catch (QueryException $exception) {
            if (! $this->isUnique($exception)) {
                throw $exception;
            }
        }
        $actor = new User(['role' => OperatorRole::SUPER_ADMIN]);
        $actor->id = 0;
        if ($event === 'payment.failed') {
            if (PaymentLifecycle::isAwaitingCapture((string) $payment->status)) {
                $this->db()->table('payments')->where('id', $payment->id)->update([
                    'status' => 'failed',
                    'failure_code' => 'webhook_failed',
                    'failure_note' => $event,
                    'updated_at' => now(),
                ]);
            }

            return $this->presentPayment($this->db()->table('payments')->where('id', $payment->id)->first());
        }
        if ($event === 'payment.refunded') {
            $open = $this->db()->table('payments')
                ->where('parent_id', $payment->id)
                ->whereIn('kind', ['refund', 'partial_refund'])
                ->whereIn('status', ['requested', 'approved', 'processing'])
                ->orderByDesc('id')
                ->first();
            if ($open) {
                return $this->db()->transaction(fn () => $this->completeRefundInTx($actor, $open));
            }

            return $this->presentPayment($payment);
        }
        abort_unless($event === 'payment.captured' || $event === 'payment.success', 422, 'Unsupported webhook event');
        abort_unless((int) $payment->amount_paise === $amountPaise, 422, 'Webhook amount does not match the payment');

        return $this->db()->transaction(fn () => $this->captureInTx($actor, (int) $payment->id, 'webhook', [
            'gateway_payment_id' => $body['gatewayPaymentId'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function initiateInTx(User $operator, array $input): array
    {
        $method = PaymentLifecycle::normalizeMethod((string) $input['method']);
        $intent = PaymentLifecycle::intentFor($method, $input['intent'] ?? null);
        $charge = PaymentLifecycle::chargeMethod($method);
        $target = $this->loadBookingTarget($operator, $input);
        $amountPaise = (int) ($input['amount_paise'] ?? $target['amountPaise']);
        abort_unless($amountPaise > 0, 422, 'Payment amount must be greater than zero');
        $invoice = $this->ensureInvoice($target);
        $open = $this->db()->table('payments')
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', PaymentLifecycle::AWAITING_CAPTURE)
            ->where('intent', $intent)
            ->whereIn('kind', ['payment', 'cancellation_charge'])
            ->first();
        if ($open) {
            return $this->presentPayment($open) + ['idempotent' => true];
        }
        $kind = $intent === 'cancel_fee' ? 'cancellation_charge' : 'payment';
        $gateway = $charge === 'wallet' ? 'wallet' : ($charge === 'cash' ? 'cash' : (string) config('karnacab.payment_gateway', 'demo'));
        $id = $this->db()->table('payments')->insertGetId([
            'public_ref' => $this->newRef('KCP'),
            'customer_id' => $target['customerId'],
            'booking_id' => $target['bookingId'],
            'invoice_id' => $invoice->id,
            'method' => $method,
            'kind' => $kind,
            'intent' => $intent,
            'amount_paise' => $amountPaise,
            'status' => 'created',
            'gateway' => $gateway,
            'note' => $input['note'] ?? null,
            'attempt_no' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->recordEvent($id, 'api', 'payment.created', ['method' => $method]);
        $this->db()->table('payments')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);
        $this->recordEvent($id, 'api', 'payment.pending', []);
        if (PaymentLifecycle::needsWebhook($charge)) {
            $this->db()->table('payments')->where('id', $id)->update([
                'status' => 'initiated',
                'gateway_order_id' => $this->newRef('KCO'),
                'updated_at' => now(),
            ]);
            $this->recordEvent($id, 'api', 'payment.initiated', ['gateway' => $gateway]);
        }
        if ($charge === 'wallet') {
            return $this->captureInTx($operator, $id, 'wallet_ledger');
        }

        return $this->presentPayment($this->db()->table('payments')->where('id', $id)->first());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function captureInTx(User $operator, int $paymentId, string $source, array $extra = []): array
    {
        $payment = $this->db()->table('payments')->where('id', $paymentId)->lockForUpdate()->first();
        abort_unless($payment, 404, 'Payment not found');
        if (PaymentLifecycle::isSuccess((string) $payment->status)) {
            return $this->presentPayment($payment) + ['idempotent' => true];
        }
        abort_unless(PaymentLifecycle::isAwaitingCapture((string) $payment->status), 422, 'Payment is not awaiting capture');
        if ($source === 'cash_confirm') {
            abort_unless($payment->method === 'cash', 422, 'Cash confirmation is only for cash payments');
        }
        if ($source === 'webhook') {
            abort_unless(PaymentLifecycle::needsWebhook(PaymentLifecycle::normalizeMethod((string) $payment->method)), 422, 'This method is not captured by webhook');
        }
        if ($source === 'wallet_ledger') {
            abort_unless($payment->method === 'wallet' || $payment->kind === 'cancellation_charge', 422, 'Wallet capture is only for wallet payments');
            abort_unless($payment->customer_id, 422, 'Wallet payment needs a customer');
            $this->wallets->postLedger([
                'ownerType' => 'CUSTOMER',
                'ownerUserId' => (int) $payment->customer_id,
                'direction' => 'DEBIT',
                'amountPaise' => (int) $payment->amount_paise,
                'commissionPaise' => 0,
                'grossPaise' => (int) $payment->amount_paise,
                'bookingId' => $payment->booking_id ? (int) $payment->booking_id : null,
                'kind' => $payment->kind === 'cancellation_charge' ? 'adjustment' : 'trip',
                'note' => $payment->note ?? ('Payment '.$payment->public_ref),
                'paymentRef' => $payment->public_ref,
            ]);
        }
        $this->db()->table('payments')->where('id', $paymentId)->update([
            'status' => 'success',
            'verified_at' => now(),
            'verified_source' => $source,
            'gateway_payment_id' => $extra['gateway_payment_id'] ?? $payment->gateway_payment_id,
            'updated_at' => now(),
        ]);
        if ($payment->invoice_id) {
            $invoice = $this->db()->table('invoices')->where('id', $payment->invoice_id)->first();
            if ($invoice) {
                $paid = (int) $invoice->paid_paise + (int) $payment->amount_paise;
                $this->db()->table('invoices')->where('id', $invoice->id)->update([
                    'paid_paise' => $paid,
                    'status' => PaymentLifecycle::invoiceStatus($paid, (int) $invoice->refunded_paise, (int) $invoice->total_paise),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->recordEvent($paymentId, $source === 'webhook' ? 'webhook' : 'system', 'payment.success', ['source' => $source], null, $source === 'webhook');

        return $this->presentPayment($this->db()->table('payments')->where('id', $paymentId)->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function completeRefundInTx(User $operator, object $refund): array
    {
        if ((string) $refund->status === 'completed') {
            return $this->presentPayment($refund) + ['idempotent' => true];
        }
        $parent = $this->db()->table('payments')->where('id', $refund->parent_id)->first();
        abort_unless($parent, 404, 'Parent payment not found');
        if ($parent->method === 'wallet' && $parent->customer_id) {
            $this->wallets->postLedger([
                'ownerType' => 'CUSTOMER',
                'ownerUserId' => (int) $parent->customer_id,
                'direction' => 'CREDIT',
                'amountPaise' => (int) $refund->amount_paise,
                'commissionPaise' => 0,
                'grossPaise' => (int) $refund->amount_paise,
                'bookingId' => $parent->booking_id ? (int) $parent->booking_id : null,
                'kind' => 'reversal',
                'note' => $refund->note ?? 'Refund',
                'paymentRef' => $refund->public_ref,
            ]);
        }
        $this->db()->table('payments')->where('id', $refund->id)->update([
            'status' => 'completed',
            'verified_at' => now(),
            'verified_source' => 'refund',
            'updated_at' => now(),
        ]);
        $done = (int) $this->db()->table('payments')
            ->where('parent_id', $parent->id)
            ->where('status', 'completed')
            ->sum('amount_paise');
        $this->db()->table('payments')->where('id', $parent->id)->update([
            'status' => $done >= (int) $parent->amount_paise ? 'refunded' : 'partially_refunded',
            'updated_at' => now(),
        ]);
        if ($parent->invoice_id) {
            $invoice = $this->db()->table('invoices')->where('id', $parent->invoice_id)->first();
            if ($invoice) {
                $refundedPaise = (int) $invoice->refunded_paise + (int) $refund->amount_paise;
                $this->db()->table('invoices')->where('id', $invoice->id)->update([
                    'refunded_paise' => $refundedPaise,
                    'status' => PaymentLifecycle::invoiceStatus((int) $invoice->paid_paise, $refundedPaise, (int) $invoice->total_paise),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->recordEvent((int) $refund->id, 'system', 'refund.completed', ['parent' => $parent->public_ref]);
        if ($parent->customer_id) {
            app(NotificationService::class)->dispatch((int) $parent->customer_id, 'refund', [
                'amount' => number_format(((int) $refund->amount_paise) / 100, 2, '.', ''),
                'ref' => $parent->public_ref,
            ], ['entity' => ['type' => 'payment', 'id' => (string) $refund->id]]);
        }

        return $this->presentPayment($this->db()->table('payments')->where('id', $refund->id)->first());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{kind: string, customerId: int, bookingId: int, amountPaise: int, snapshot: mixed}
     */
    private function loadBookingTarget(User $operator, array $input): array
    {
        $bookingId = (int) ($input['booking_id'] ?? 0);
        abort_unless($bookingId > 0, 422, 'Payment must target a booking');
        $q = $this->db()->table('bookings')->where('id', $bookingId);
        TerritoryScope::applyBookings($q, $operator);
        $row = $q->first();
        abort_unless($row, 404, 'Booking not found');

        return [
            'kind' => 'ride',
            'customerId' => (int) $row->customer_id,
            'bookingId' => (int) $row->id,
            'amountPaise' => (int) ($row->quote_paise ?? 0),
            'snapshot' => $row->quote_snapshot ?? null,
        ];
    }

    /**
     * @param  array{kind: string, customerId: int, bookingId: int, amountPaise: int, snapshot: mixed}  $target
     */
    private function ensureInvoice(array $target): object
    {
        $existing = $this->db()->table('invoices')->where('booking_id', $target['bookingId'])->first();
        if ($existing) {
            return $existing;
        }
        $snap = is_string($target['snapshot']) ? json_decode($target['snapshot'], true) : $target['snapshot'];
        $snap = is_array($snap) ? $snap : [];
        $breakdown = is_array($snap['breakdown'] ?? null) ? $snap['breakdown'] : $snap;
        $tax = (int) ($snap['gstPaise'] ?? $breakdown['gstPaise'] ?? 0);
        $total = max(0, $target['amountPaise']);
        $id = $this->db()->table('invoices')->insertGetId([
            'public_ref' => $this->newRef('KCI'),
            'kind' => $target['kind'],
            'customer_id' => $target['customerId'],
            'booking_id' => $target['bookingId'],
            'status' => 'issued',
            'currency' => 'INR',
            'subtotal_paise' => max(0, $total - $tax),
            'tax_paise' => $tax,
            'total_paise' => $total,
            'paid_paise' => 0,
            'refunded_paise' => 0,
            'lines' => json_encode($breakdown),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->db()->table('invoices')->where('id', $id)->first();
    }

    private function mustSee(User $operator, int $id): object
    {
        $row = $this->scoped($operator)->where('payments.id', $id)->first();
        abort_unless($row, 404, 'Payment not found');

        return $row;
    }

    private function scoped(?User $operator): Builder
    {
        $q = $this->db()->table('payments');
        $this->constrainPayments($q, $operator);

        return $q;
    }

    private function constrainPayments(Builder $q, ?User $operator): void
    {
        if ($operator === null || TerritoryScope::isUnrestricted($operator)) {
            return;
        }
        $q->where(function ($inner) use ($operator) {
            $inner->whereExists(function ($sub) use ($operator) {
                $sub->selectRaw('1')->from('bookings')->whereColumn('bookings.id', 'payments.booking_id');
                TerritoryScope::applyBookings($sub, $operator, 'bookings');
            });
            if ($operator->nest_user_id) {
                $inner->orWhere('payments.customer_id', $operator->nest_user_id);
            }
        });
    }

    private function canConfirmCash(User $operator): bool
    {
        return $operator->can('payments.edit')
            || $operator->can('platform.admin')
            || in_array((string) $operator->role, [
                OperatorRole::DRIVER,
                OperatorRole::FLEET_OWNER,
                OperatorRole::DISTRICT_HEAD,
                OperatorRole::FRANCHISE,
            ], true);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function recordEvent(int $paymentId, string $source, string $type, ?array $payload = null, ?string $gatewayEventId = null, bool $signed = false): void
    {
        if (! $this->hasTable('payment_events')) {
            return;
        }
        $this->db()->table('payment_events')->insert([
            'payment_id' => $paymentId,
            'source' => $source,
            'event_type' => $type,
            'gateway_event_id' => $gatewayEventId,
            'payload' => $payload ? json_encode($payload) : null,
            'signature_valid' => $signed ? 1 : 0,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPayment(?object $row): array
    {
        if ($row === null) {
            return [];
        }
        $kind = (string) ($row->kind ?? 'payment');
        $status = (string) ($row->status ?? '');
        $lifecycle = in_array($kind, ['refund', 'partial_refund'], true)
            ? PaymentLifecycle::REFUND_STEPS
            : PaymentLifecycle::PAYMENT_STEPS;

        return [
            'id' => (string) $row->id,
            'transactionId' => $row->public_ref,
            'paymentReference' => $row->public_ref,
            'method' => $row->method,
            'kind' => $kind,
            'intent' => $row->intent ?? 'capture',
            'amountPaise' => (int) $row->amount_paise,
            'amountRupees' => ((int) $row->amount_paise) / 100,
            'status' => in_array($kind, ['refund', 'partial_refund'], true) ? $status : PaymentLifecycle::displayPaymentStatus($status),
            'lifecycle' => $lifecycle,
            'gateway' => $row->gateway ?? null,
            'gatewayOrderId' => $row->gateway_order_id ?? null,
            'gatewayPaymentId' => $row->gateway_payment_id ?? null,
            'failureCode' => $row->failure_code ?? null,
            'failureNote' => $row->failure_note ?? null,
            'verifiedAt' => $row->verified_at ?? null,
            'verifiedSource' => $row->verified_source ?? null,
            'bookingId' => isset($row->booking_id) && $row->booking_id ? (string) $row->booking_id : null,
            'customerId' => isset($row->customer_id) && $row->customer_id ? (string) $row->customer_id : null,
            'invoiceId' => isset($row->invoice_id) && $row->invoice_id ? (string) $row->invoice_id : null,
            'parentId' => isset($row->parent_id) && $row->parent_id ? (string) $row->parent_id : null,
            'note' => $row->note ?? null,
            'createdAt' => $row->created_at ?? null,
            'clientCaptureIgnored' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentInvoice(?object $row): array
    {
        if ($row === null) {
            return [];
        }

        return [
            'id' => (string) $row->id,
            'invoiceNumber' => $row->public_ref,
            'kind' => $row->kind,
            'status' => $row->status,
            'currency' => $row->currency ?? 'INR',
            'subtotalPaise' => (int) $row->subtotal_paise,
            'taxPaise' => (int) $row->tax_paise,
            'totalPaise' => (int) $row->total_paise,
            'paidPaise' => (int) $row->paid_paise,
            'refundedPaise' => (int) $row->refunded_paise,
            'totalRupees' => ((int) $row->total_paise) / 100,
            'lines' => is_string($row->lines ?? null) ? json_decode((string) $row->lines, true) : ($row->lines ?? null),
            'issuedAt' => $row->issued_at ?? $row->created_at ?? null,
            'bookingId' => isset($row->booking_id) && $row->booking_id ? (string) $row->booking_id : null,
        ];
    }

    private function isUnique(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000' || str_contains($exception->getMessage(), 'UNIQUE');
    }

    private function newRef(string $prefix): string
    {
        return substr($prefix.strtoupper(bin2hex(random_bytes(6))), 0, 24);
    }

    private function hasTable(string $table): bool
    {
        return Schema::connection('platform')->hasTable($table);
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
