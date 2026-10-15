<?php

namespace App\Http\Controllers;

use App\Services\CommissionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementsController extends Controller
{
    public function __construct(private readonly CommissionSettlementService $settlements) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('payments.view') || $request->user()?->can('wallet.view') || $request->user()?->can('platform.admin'), 403);

        return view('settlements.index', $this->settlements->overview());
    }

    public function payout(Request $request, int $fleetOwner): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $posted = $this->settlements->payout($request->user(), $fleetOwner, $data['note'] ?? null);

        return back()->with('status', 'Settled ₹'.number_format((float) $posted['amountRupees'], 2).' to this fleet owner. A debit is on their wallet.');
    }
}
