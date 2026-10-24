<?php

namespace App\Http\Controllers;

use App\Services\CommissionSettlementService;
use App\Services\WeeklyCommissionSettle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementsController extends Controller
{
    public function __construct(
        private readonly CommissionSettlementService $settlements,
        private readonly WeeklyCommissionSettle $weekly,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('payments.view') || $request->user()?->can('wallet.view') || $request->user()?->can('platform.admin'), 403);
        $week = $request->query('week');

        return view('settlements.index', array_merge(
            $this->settlements->overview($request->user()),
            ['weeklyPay' => $this->weekly->board($request->user(), is_string($week) ? $week : null)],
        ));
    }

    public function settleDistrictWeek(Request $request, int $user): RedirectResponse
    {
        $posted = $this->weekly->settleDistrict($request->user(), $user, $request->input('week'));

        return back()->with('status', 'District Head wallet credited ₹'.number_format((float) $posted['creditedRupees'], 2).' for '.$posted['label'].'. Recorded commission was ₹'.number_format((float) $posted['recordedRupees'], 2).'.');
    }

    public function settleFleetWeek(Request $request, int $fleetOwner): RedirectResponse
    {
        $posted = $this->weekly->settleFleet($request->user(), $fleetOwner, $request->input('week'));

        return back()->with('status', 'Fleet owner wallet credited ₹'.number_format((float) $posted['creditedRupees'], 2).' for '.$posted['label'].'.');
    }

    public function statement(Request $request): StreamedResponse
    {
        $week = $request->query('week');

        return $this->weekly->statement($request->user(), is_string($week) ? $week : null);
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
