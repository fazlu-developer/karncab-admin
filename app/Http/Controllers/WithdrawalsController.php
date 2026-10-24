<?php

namespace App\Http\Controllers;

use App\Services\WalletLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalsController extends Controller
{
    public function __construct(private readonly WalletLedgerService $wallets) {}

    public function index(Request $request): View
    {
        $operator = $request->user();
        abort_unless($this->wallets->canViewWithdrawals($operator), 403);
        $status = (string) $request->query('status', 'requested');
        $q = trim((string) $request->query('q', ''));

        return view('withdrawals.index', [
            'rows' => $this->wallets->withdrawals($operator, ['status' => $status, 'q' => $q]),
            'status' => $status,
            'q' => $q,
            'canSettle' => $this->wallets->canSettleWithdrawals($operator),
        ]);
    }

    public function decide(Request $request, int $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,settle'],
            'transfer_ref' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,reject'],
        ]);
        $this->wallets->decideWithdrawal(
            $request->user(),
            $withdrawal,
            $data['decision'],
            $data['transfer_ref'] ?? null,
            $data['note'] ?? null,
        );

        return back()->with('status', 'Withdrawal '.$data['decision'].' saved.');
    }
}
