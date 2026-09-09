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
        abort_unless($request->user()?->can('vehicles.view'), 403);

        return view('live.map', [
            'snapshot' => $this->map->snapshot($request->user(), $request->query('status')),
            'googleKey' => config('karnacab.google_maps_key'),
            'status' => $request->query('status'),
        ]);
    }
}
