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

        return view('settlements.index', $this->settlements->overview($request->user()));
    }

    public function payout(Request $request, int $fleetOwner): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'bank_reference' => ['required', 'string', 'max:40'],
            'amount_rupees' => ['nullable', 'numeric', 'min:1'],
            'week_label' => ['nullable', 'string', 'max:80'],
        ]);
        $posted = $this->settlements->payout(
            $request->user(),
            $fleetOwner,
            $data['note'] ?? null,
            $data['bank_reference'],
            isset($data['amount_rupees']) ? (float) $data['amount_rupees'] : null,
            $data['week_label'] ?? null,
        );

        return back()->with('status', 'Bank transfer '.$data['bank_reference'].' recorded. ₹'.number_format((float) $posted['amountRupees'], 2).' settled to this fleet owner.');
    }

    public function payoutDistrict(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate([
            'amount_rupees' => ['required', 'numeric', 'min:1'],
            'bank_reference' => ['required', 'string', 'max:40'],
            'week_label' => ['required', 'string', 'max:80'],
        ]);
        $posted = $this->settlements->payoutDistrict(
            $request->user(),
            $user,
            (float) $data['amount_rupees'],
            $data['bank_reference'],
            $data['week_label'],
        );

        return back()->with('status', 'District head bank transfer '.$data['bank_reference'].' recorded for ₹'.number_format((float) $posted['amountRupees'], 2).'.');
    }
}
