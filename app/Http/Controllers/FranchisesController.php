<?php

namespace App\Http\Controllers;

use App\Platform\ExclusiveDistrictSeat;
use App\Services\DistrictFranchiseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FranchisesController extends Controller
{
    public function __construct(private readonly DistrictFranchiseService $franchises) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('franchise.view'), 403);

        return view('franchises.index', [
            'rows' => $this->franchises->list($request->user(), $request->query()),
            'districts' => $this->franchises->districtChoices($request->user()),
            'kinds' => ExclusiveDistrictSeat::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->applicationData($request);
        $row = $this->franchises->apply($request->user(), $data);

        return redirect()->route('franchises.show', $row['id'])->with('status', 'Application saved as APPLIED. The exclusive district seat is claimed only when status becomes ACTIVE.');
    }

    public function show(Request $request, int $franchise): View
    {
        return view('franchises.show', [
            'row' => $this->franchises->one($request->user(), $franchise),
            'districts' => $this->franchises->districtChoices($request->user()),
            'transitions' => ExclusiveDistrictSeat::TRANSITIONS,
        ]);
    }

    public function lifecycle(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->guardConflict(function () use ($request, $franchise, $data) {
            $this->franchises->lifecycle($request->user(), $franchise, $data['status'], $data['reason'] ?? null);

            return back()->with('status', 'Status updated to '.$data['status'].'.');
        });
    }

    public function kyc(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate([
            'kyc_status' => ['required', 'in:pending,submitted,verified,rejected'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->franchises->setKyc($request->user(), $franchise, $data['kyc_status'], $data['reason'] ?? null);

        return back()->with('status', 'KYC updated.');
    }

    public function document(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:32'],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
        ]);
        $this->franchises->addDocument($request->user(), $franchise, $data);

        return back()->with('status', 'KYC document recorded.');
    }

    public function agreement(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate(['version' => ['nullable', 'string', 'max:32']]);
        $this->franchises->signAgreement($request->user(), $franchise, $data['version'] ?? 'v1');

        return back()->with('status', 'Agreement signed.');
    }

    public function fee(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:24'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->franchises->addFee($request->user(), $franchise, $data);

        return back()->with('status', 'Fee added.');
    }

    public function payFee(Request $request, int $franchise, int $fee): RedirectResponse
    {
        $this->franchises->payFee($request->user(), $franchise, $fee);

        return back()->with('status', 'Fee marked paid.');
    }

    public function renewal(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ]);
        $this->franchises->requestRenewal($request->user(), $franchise, $data);

        return back()->with('status', 'Renewal period recorded.');
    }

    public function decideRenewal(Request $request, int $franchise, int $renewal): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $this->franchises->decideRenewal($request->user(), $franchise, $renewal, $data['status']);

        return back()->with('status', 'Renewal '.$data['status'].'.');
    }

    public function commission(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate(['commission_percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $this->franchises->setCommission($request->user(), $franchise, (float) $data['commission_percent']);

        return back()->with('status', 'Commission updated.');
    }

    public function territory(Request $request, int $franchise): RedirectResponse
    {
        $data = $request->validate(['district_id' => ['required', 'integer']]);

        return $this->guardConflict(function () use ($request, $franchise, $data) {
            $this->franchises->reassign($request->user(), $franchise, (int) $data['district_id']);

            return back()->with('status', 'Territory updated.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationData(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'in:DISTRICT_HEAD,EXCLUSIVE_FRANCHISE'],
            'trade_name' => ['required', 'string', 'max:160'],
            'district_id' => ['required', 'integer'],
            'owner_user_id' => ['nullable', 'integer'],
            'name' => ['required_without:owner_user_id', 'nullable', 'string', 'max:120'],
            'email' => ['required_without:owner_user_id', 'nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required_without:owner_user_id', 'nullable', 'string', 'min:8'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'fee_amount_paise' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function guardConflict(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                return back()->withErrors(['seat' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }
}
