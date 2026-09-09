<?php

namespace App\Http\Controllers\State;

use App\Http\Controllers\Controller;
use App\Services\StateHeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StateWorkspaceController extends Controller
{
    public function __construct(private readonly StateHeadService $stateHeads) {}

    public function dashboard(Request $request): View
    {
        try {
            $payload = $this->stateHeads->dashboard($request->user(), $request->query());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                return view('state.unassigned');
            }
            throw $exception;
        }

        return view('state.dashboard', $payload);
    }

    public function section(Request $request, string $section): View|RedirectResponse
    {
        if ($section === 'map') {
            return redirect()->route('live.map');
        }
        try {
            $payload = $this->stateHeads->section($section, $request->user(), $request->query());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                return view('state.unassigned');
            }
            throw $exception;
        }

        $view = match ($section) {
            'map' => 'state.map',
            'notifications' => 'state.notify',
            'district-heads' => 'state.district-heads',
            default => 'state.section',
        };

        return view($view, $payload + ['query' => $request->query()]);
    }

    public function storeDistrictHead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'district_id' => ['required', 'integer'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $this->stateHeads->createDistrictHead($request->user(), $data, $request->query());

        return back()->with('status', 'District Head application created (APPLIED). The exclusive district seat is claimed only when status becomes ACTIVE.');
    }

    public function storeFranchise(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:DISTRICT_HEAD,EXCLUSIVE_FRANCHISE'],
            'trade_name' => ['required', 'string', 'max:160'],
            'district_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'fee_amount_paise' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->stateHeads->saveFranchise($request->user(), $data, $request->query());

        return back()->with('status', 'Franchise application saved as APPLIED. Exclusive district seat is claimed only on ACTIVE.');
    }

    public function notify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'audience' => ['required', 'in:customer,driver,ops'],
        ]);
        $count = $this->stateHeads->notify($request->user(), $data, $request->query());

        return back()->with('status', "Notification queued for {$count} accounts in this state.");
    }
}
