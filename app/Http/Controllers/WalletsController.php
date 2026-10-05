<?php

namespace App\Http\Controllers;

use App\Services\WalletLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletsController extends Controller
{
    public function __construct(private readonly WalletLedgerService $wallets) {}

    public function index(Request $request): View
    {
        $operator = $request->user();
        $canCredit = $operator?->can('payments.edit') || $operator?->can('platform.admin');

        return view('wallets.index', [
            'wallets' => $this->wallets->wallets($operator, $request->query()),
            'ledger' => $this->wallets->ledger($operator, $request->query()),
            'accounts' => WalletLedgerService::ACCOUNTS,
            'query' => $request->query(),
            'canCredit' => $canCredit,
            'creditUsers' => $canCredit ? $this->wallets->creditCandidates($operator, (string) $request->query('user_q', '')) : [],
            'userQuery' => (string) $request->query('user_q', ''),
        ]);
    }

    public function show(Request $request, int $wallet): View
    {
        return view('wallets.show', [
            'wallet' => $this->wallets->wallet($request->user(), $wallet),
        ]);
    }

    public function commission(Request $request): View
    {
        abort_unless($request->user()?->can('payments.view') || $request->user()?->can('wallet.view'), 403);
        $this->wallets->ensureDefaultRule();

        return view('wallets.commission', [
            'policy' => $this->wallets->policy(),
        ]);
    }

    public function updateCommission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'on_base_fare' => ['nullable', 'boolean'],
            'on_gst' => ['nullable', 'boolean'],
            'on_toll' => ['nullable', 'boolean'],
            'on_parking' => ['nullable', 'boolean'],
            'on_waiting' => ['nullable', 'boolean'],
            'on_discount' => ['nullable', 'boolean'],
            'on_other' => ['nullable', 'boolean'],
            'on_complete' => ['nullable', 'boolean'],
        ]);
        $this->wallets->updatePolicy($request->user(), $data + [
            'on_base_fare' => $request->boolean('on_base_fare'),
            'on_gst' => $request->boolean('on_gst'),
            'on_toll' => $request->boolean('on_toll'),
            'on_parking' => $request->boolean('on_parking'),
            'on_waiting' => $request->boolean('on_waiting'),
            'on_discount' => $request->boolean('on_discount'),
            'on_other' => $request->boolean('on_other'),
            'on_complete' => $request->boolean('on_complete'),
        ]);

        return back()->with('status', 'Commission rule saved. Ledger posts will use this percent and these fare buckets.');
    }

    public function credit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'amount_rupees' => ['required', 'numeric', 'min:1', 'max:500000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $paise = (int) round(((float) $data['amount_rupees']) * 100);
        $posted = $this->wallets->creditUser($request->user(), (int) $data['user_id'], $paise, $data['note'] ?? null);
        $added = number_format($paise / 100, 2);
        $balance = number_format(((int) ($posted['newBalancePaise'] ?? 0)) / 100, 2);

        return back()->with('status', "Added ₹{$added}. New wallet balance is ₹{$balance}.");
    }

    public function post(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', 'in:'.implode(',', WalletLedgerService::ACCOUNTS)],
            'owner_user_id' => ['required', 'integer'],
            'direction' => ['required', 'in:CREDIT,DEBIT'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'commission_paise' => ['nullable', 'integer', 'min:0'],
            'booking_id' => ['nullable', 'integer'],
            'kind' => ['nullable', 'string', 'max:24'],
            'payment_ref' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->wallets->post($request->user(), $data);

        return back()->with('status', 'Ledger row posted.');
    }

    public function settle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
        ]);
        $this->wallets->settleBooking($request->user(), (int) $data['booking_id']);

        return back()->with('status', 'Booking settled through the wallet ledger.');
    }
}
