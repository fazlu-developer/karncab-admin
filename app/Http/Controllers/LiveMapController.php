<?php

namespace App\Http\Controllers;

use App\Platform\LiveFleetMapAccess;
use App\Services\LiveFleetMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveMapController extends Controller
{
    public function __construct(private readonly LiveFleetMapService $map) {}

    public function show(Request $request): View
    {
        abort_unless(LiveFleetMapAccess::canUseMap($request->user()), 403);
        abort_unless($request->user()?->can('vehicles.view') || $request->user()?->can('tracking.view'), 403);

        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));
        $family = strtoupper(trim((string) $request->query('family', '')));
        $vehicleId = (int) $request->query('vehicle', 0);

        return view('live.map', [
            'snapshot' => $this->map->snapshot($request->user(), $status, [
                'q' => $q,
                'family' => $family,
                'vehicleId' => $vehicleId,
            ]),
            'googleKey' => config('karnacab.google_maps_key'),
            'status' => $status,
            'q' => $q,
            'family' => $family,
            'vehicleId' => $vehicleId,
        ]);
    }
}
