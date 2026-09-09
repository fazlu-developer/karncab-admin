<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlacesController extends Controller
{
    public function __construct(private readonly GooglePlacesService $places) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:180'],
            'types' => ['nullable', 'string', 'max:80'],
        ]);
        try {
            return response()->json(['predictions' => $this->places->autocomplete($data['q'], $data['types'] ?? null)]);
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }
    }

    public function details(Request $request): JsonResponse
    {
        $data = $request->validate([
            'placeId' => ['required', 'string', 'max:255'],
        ]);
        try {
            return response()->json($this->places->details($data['placeId']));
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }
    }

    public function directions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'originLat' => ['required', 'numeric'],
            'originLng' => ['required', 'numeric'],
            'destLat' => ['required', 'numeric'],
            'destLng' => ['required', 'numeric'],
        ]);
        try {
            return response()->json($this->places->directions(
                (float) $data['originLat'],
                (float) $data['originLng'],
                (float) $data['destLat'],
                (float) $data['destLng'],
            ));
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }
    }
}
