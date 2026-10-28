<?php

namespace App\Services;

use App\Support\AdvancePayment;
use App\Support\TourMoney;
use App\Support\TravelPackageSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin desk for Travel & Tour. Same travel_bookings state machine as the API.
 * Assign is allowed only after the 30% advance (CONFIRMED).
 *
 * T = ₹10,000 → advance ₹3,000, platform ₹1,000, driver ₹2,000 after assign, ₹7,000 on the ground.
 */
class TourDeskService
{
    public function rows(?string $bucket = null, ?string $search = null): array
    {
        TravelPackageSchema::ensure('platform');
        if (! Schema::connection('platform')->hasTable('travel_bookings')) {
            return [];
        }
        $q = $this->db()->table('travel_bookings')->orderByDesc('id')->limit(80);
        $statuses = $this->bucketStatuses($bucket);
        if ($statuses) {
            $q->where(function ($inner) use ($statuses) {
                foreach ($statuses as $status) {
                    $inner->orWhereRaw('UPPER(status) = ?', [strtoupper($status)]);
                }
            });
        }
        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('public_ref', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('pickup_text', 'like', $like);
            });
        }
        $packages = Schema::connection('platform')->hasTable('travel_packages')
            ? $this->db()->table('travel_packages')->get()->keyBy('id')
            : collect();
        $drivers = $this->driverNames();

        return $q->get()->map(function ($row) use ($packages, $drivers) {
            $pkg = $packages->get($row->package_id);
            $total = (int) ($row->quote_paise ?? 0);
            $platform = intdiv($total * 10, 100);
            $driverShare = intdiv($total * 20, 100);
            $advance = $platform + $driverShare;
            $balance = $total - $advance;
            $passengers = $this->passengers($row->passengers ?? null);

            return [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => strtoupper((string) ($row->status ?? '')),
                'travelDate' => $row->travel_date ?? null,
                'guests' => (int) ($row->guests ?? count($passengers)),
                'pickup' => $row->pickup_text ?? '',
                'contactName' => $row->contact_name ?? '',
                'contactPhone' => $row->contact_phone ?? '',
                'passengers' => $passengers,
                'package' => $pkg->title ?? $pkg->name ?? 'Package',
                'totalRupees' => $total / 100,
                'advanceRupees' => $advance / 100,
                'platformRupees' => $platform / 100,
                'driverRupees' => $driverShare / 100,
                'balanceRupees' => $balance / 100,
                'advancePaid' => (int) ($row->advance_paid_paise ?? 0) > 0,
                'ticketNo' => $row->ticket_no ?? null,
                'invoiceNo' => $row->invoice_no ?? null,
                'rejectReason' => $row->reject_reason ?? null,
                'driverName' => $drivers[(int) ($row->driver_id ?? 0)] ?? null,
                'collectedRupees' => ((int) ($row->collected_outside_paise ?? 0)) / 100,
            ];
        })->all();
    }

    public function approve(int $id): void
    {
        TravelPackageSchema::ensure('platform');
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'Only a requested tour can be approved.');
        $ticket = 'TKT'.strtoupper(Str::random(8));
        $invoice = 'TINV'.strtoupper(Str::random(8));
        $this->db()->table('travel_bookings')->where('id', $id)->update($this->only('travel_bookings', [
            'status' => 'APPROVED_UNPAID',
            'ticket_no' => $ticket,
            'invoice_no' => $invoice,
            'approved_at' => now(),
            'updated_at' => now(),
        ]));
        try {
            $this->writeInvoice($this->must($id), $invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('tour.approve_invoice_failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
        $pct = AdvancePayment::percent('TRAVEL');
        $this->notify((int) $row->customer_id, 'Tour approved', 'Pay the '.$pct.'% advance to confirm your seat on '.$row->public_ref.'.', 'tour_approved');
    }

    public function reject(int $id, string $reason): void
    {
        TravelPackageSchema::ensure('platform');
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'This tour can no longer be rejected.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'A reject reason is required.');
        $this->db()->table('travel_bookings')->where('id', $id)->update($this->only('travel_bookings', [
            'status' => 'REJECTED',
            'reject_reason' => $reason,
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Tour not confirmed', $reason, 'tour_rejected');
    }

    public function assign(int $id, int $driverId): void
    {
        TravelPackageSchema::ensure('platform');
        $row = $this->must($id);
        abort_unless($row->status === 'CONFIRMED', 422, 'Assign a driver only after the '.AdvancePayment::percent('TRAVEL').'% advance is paid.');
        $driver = $this->db()->table('drivers')->where('id', $driverId)->first();
        abort_unless($driver, 422, 'Choose a driver from the list.');
        $userId = (int) ($driver->user_id ?? 0);
        abort_if($userId < 1, 422, 'That driver has no login.');
        $total = (int) $row->quote_paise;
        $driverShare = (int) TourMoney::split($total, AdvancePayment::percent('TRAVEL'))['driverAdvancePaise'];
        try {
            $this->releaseDriver($userId, $id, $driverShare, $total);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('tour.assign_wallet_failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
        $this->db()->table('travel_bookings')->where('id', $id)->update($this->only('travel_bookings', [
            'status' => 'ASSIGNED',
            'driver_id' => $driverId,
            'start_otp' => (string) random_int(1000, 9999),
            'driver_advance_paise' => $driverShare,
            'assigned_at' => now(),
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Tour assigned', 'Tour '.$row->public_ref.' has a driver.', 'tour_assigned');
        $this->notify($userId, 'Tour assigned', 'You are assigned to tour '.$row->public_ref.'.', 'tour_assigned');
    }

    private function releaseDriver(int $driverUserId, int $tourId, int $amount, int $gross): void
    {
        if ($amount < 1) {
            return;
        }
        $platformId = (int) ($this->db()->table('users')->whereIn('role', ['ADMIN', 'SUPER_ADMIN'])->orderBy('id')->value('id') ?? 0);
        if ($platformId > 0) {
            $this->move($platformId, 'PLATFORM', 'DEBIT', $amount, 'tour_driver_escrow_release', 'TOUR'.$tourId.'-ESCROW-OUT', 'Released tour driver advance from escrow', $gross);
        }
        $this->move($driverUserId, 'DRIVER', 'CREDIT', $amount, 'tour_driver_advance', 'TOUR'.$tourId.'-DRIVER', 'Tour advance share 20% of package total', $gross);
    }

    private function move(int $userId, string $ownerType, string $direction, int $amount, string $kind, string $ref, string $note, int $gross): void
    {
        if (! Schema::connection('platform')->hasTable('wallets') || ! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return;
        }
        if ($this->db()->table('wallet_ledger')->where('public_ref', $ref)->exists()) {
            return;
        }
        $wallet = $this->db()->table('wallets')->where('owner_user_id', $userId)->where('owner_type', $ownerType)->first();
        if (! $wallet) {
            $walletId = $this->db()->table('wallets')->insertGetId($this->only('wallets', [
                'owner_user_id' => $userId,
                'owner_type' => $ownerType,
                'balance_paise' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $wallet = $this->db()->table('wallets')->where('id', $walletId)->first();
        }
        $before = (int) $wallet->balance_paise;
        $after = $direction === 'DEBIT' ? $before - $amount : $before + $amount;
        $this->db()->table('wallets')->where('id', $wallet->id)->update(['balance_paise' => $after, 'updated_at' => now()]);
        $this->db()->table('wallet_ledger')->insert($this->only('wallet_ledger', [
            'public_ref' => $ref,
            'payment_ref' => $ref,
            'wallet_id' => $wallet->id,
            'owner_user_id' => $userId,
            'account' => $ownerType,
            'owner_type' => $ownerType,
            'direction' => $direction,
            'amount_paise' => $amount,
            'gross_paise' => $gross ?: $amount,
            'balance_before_paise' => $before,
            'balance_after_paise' => $after,
            'kind' => $kind,
            'status' => 'posted',
            'note' => $note,
            'created_at' => now(),
        ]));
    }

    private function writeInvoice(object $row, string $invoiceNo): void
    {
        if (! Schema::connection('platform')->hasTable('invoices')) {
            return;
        }
        if ($this->db()->table('invoices')->where('public_ref', $invoiceNo)->exists()) {
            return;
        }
        $payload = [
            'public_ref' => $invoiceNo,
            'customer_id' => $row->customer_id,
            'kind' => 'tour',
            'product' => 'TRAVEL',
            'status' => 'issued',
            'subtotal_paise' => (int) $row->quote_paise,
            'tax_paise' => 0,
            'total_paise' => (int) $row->quote_paise,
            'paid_paise' => 0,
            'refunded_paise' => 0,
            'currency' => 'INR',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->stampInvoiceBookingId($payload);
        $this->db()->table('invoices')->insert($this->only('invoices', $payload));
    }

    private function notify(int $userId, string $title, string $body, string $event): void
    {
        if ($userId < 1) {
            return;
        }
        try {
            app(NotificationService::class)->dispatch($userId, $event, ['title' => $title, 'body' => $body], [
                'title' => $title,
                'body' => $body,
                'channels' => ['in_app', 'push'],
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<int, string>
     */
    private function driverNames(): array
    {
        if (! Schema::connection('platform')->hasTable('drivers') || ! Schema::connection('platform')->hasTable('users')) {
            return [];
        }

        return $this->db()->table('drivers')
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.id', 'users.name', 'users.phone')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id => trim($row->name.' · '.$row->phone)])
            ->all();
    }

    /**
     * @return list<array{name: string, phone: string, age: string, gender: string}>
     */
    private function passengers(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>|null
     */
    private function bucketStatuses(?string $bucket): ?array
    {
        return match ($bucket) {
            'pending' => ['REQUESTED'],
            'approved' => ['APPROVED_UNPAID'],
            'paid' => ['CONFIRMED'],
            'assigned' => ['ASSIGNED', 'ON_TOUR'],
            'completed' => ['COMPLETED'],
            'rejected' => ['REJECTED', 'CANCELLED'],
            default => null,
        };
    }

    private function must(int $id): object
    {
        $row = $this->db()->table('travel_bookings')->where('id', $id)->first();
        abort_unless($row, 404, 'Tour booking not found');

        return $row;
    }

    private function db()
    {
        return DB::connection('platform');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    private function stampInvoiceBookingId(array &$payload): void
    {
        if (! Schema::connection('platform')->hasColumn('invoices', 'booking_id')) {
            return;
        }
        $nullable = true;
        try {
            $col = $this->db()->selectOne("SHOW COLUMNS FROM invoices LIKE 'booking_id'");
            $nullable = strtoupper((string) ($col->Null ?? 'YES')) !== 'NO';
        } catch (\Throwable) {
        }
        $payload['booking_id'] = $nullable ? null : 0;
    }

    private function only(string $table, array $row): array
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable($table)) {
            return $row;
        }

        return array_filter($row, fn ($key) => $schema->hasColumn($table, $key), ARRAY_FILTER_USE_KEY);
    }
}
