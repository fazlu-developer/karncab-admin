<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\LiveFleetMapAccess;
use App\Services\LiveFleetMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveMapController extends Controller
{
    public function __construct(private readonly LiveFleetMapService $map) {}

    public function snapshot(Request $request): JsonResponse
    {
        $status = $request->query('status');
        if ($status && ! in_array($status, LiveFleetMapAccess::STATUSES, true)) {
            abort(422, 'Unknown map status filter.');
        }

        return response()->json($this->map->snapshot($request->user(), $status));
    }

    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
            'trip_status' => ['nullable', 'string', 'max:32'],
            'booking_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->map->ingest($request->user(), $data));
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json($this->map->mine($request->user()));
    }

    public function booking(Request $request, int $id): JsonResponse
    {
        return response()->json($this->map->bookingLocation($request->user(), $id));
    }
}
