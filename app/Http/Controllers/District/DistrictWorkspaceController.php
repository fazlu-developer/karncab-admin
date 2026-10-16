<?php

namespace App\Http\Controllers\District;

use App\Http\Controllers\Controller;
use App\Services\DistrictHeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DistrictWorkspaceController extends Controller
{
    public function __construct(private readonly DistrictHeadService $districts) {}

    public function dashboard(Request $request): View
    {
        try {
            $payload = $this->districts->dashboard($request->user(), $request->query());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                return view('district.unassigned');
            }
            throw $exception;
        }

        return view('district.dashboard', $payload);
    }

    public function settings(Request $request): View
    {
        return view('district.settings', $this->districts->settings($request->user(), $request->query()));
    }

    public function saveFleetCut(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fleet_id' => ['required', 'integer'],
            'percent' => ['required', 'numeric', 'min:0', 'max:4'],
        ]);
        $this->districts->saveFleetCut($request->user(), (int) $data['fleet_id'], (float) $data['percent'], $request->query());

        return back()->with('status', 'Fleet cut saved. It is taken from the District Head 4% share.');
    }
}
