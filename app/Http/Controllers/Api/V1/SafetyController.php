<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\SafetyPolicy;
use App\Services\SafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    public function __construct(private readonly SafetyService $safety) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->safety->catalog());
    }

    public function publicShare(string $token): JsonResponse
    {
        return response()->json($this->safety->publicShare($token));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->safety->me($request->user()));
    }

    public function contacts(Request $request): JsonResponse
    {
        return response()->json(['contacts' => $this->safety->contacts($request->user())]);
    }

    public function saveEmergency(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($this->safety->saveEmergency($request->user(), $data));
    }

    public function destroyContact(Request $request, int $id): JsonResponse
    {
        $this->safety->removeContact($request->user(), $id);

        return response()->json(['ok' => true]);
    }

    public function sosPeek(Request $request): JsonResponse
    {
        return response()->json($this->safety->sos($request->user(), []));
    }

    public function sos(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:'.implode(',', SafetyPolicy::SOS_KINDS)],
            'booking_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->safety->sos($request->user(), $data), ! empty($data['kind']) && ($data['kind'] ?? '') !== 'share' ? 201 : 200);
    }

    public function share(Request $request): JsonResponse
    {
        $data = $request->validate(['booking_id' => ['nullable', 'integer']]);

        return response()->json($this->safety->share($request->user(), isset($data['booking_id']) ? (int) $data['booking_id'] : null));
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        return response()->json($this->safety->verify($request->user(), $id));
    }

    public function confirmOtp(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:start,complete'],
            'otp' => ['required', 'string', 'max:8'],
        ]);

        return response()->json($this->safety->confirmOtp($request->user(), $id, $data['action'], $data['otp']));
    }

    public function incidents(Request $request): JsonResponse
    {
        return response()->json(['incidents' => $this->safety->listIncidents($request->user(), $request->query())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->safety->oneIncident($request->user(), $id));
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', SafetyPolicy::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->safety->review($request->user(), $id, $data));
    }
}
