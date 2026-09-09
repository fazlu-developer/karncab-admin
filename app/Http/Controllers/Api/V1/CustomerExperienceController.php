<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\SupportPolicy;
use App\Services\CustomerExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerExperienceController extends Controller
{
    public function __construct(private readonly CustomerExperienceService $me) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->me->catalog());
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->me->dashboard($request->user()));
    }

    public function section(Request $request, string $section): JsonResponse
    {
        return response()->json($this->me->section($request->user(), $section));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'last_address' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->me->updateProfile($request->user(), $data));
    }

    public function updateEmergency(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json($this->me->updateEmergency($request->user(), $data));
    }

    public function storeFamily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($this->me->addFamily($request->user(), $data), 201);
    }

    public function destroyFamily(Request $request, int $id): JsonResponse
    {
        $this->me->removeFamily($request->user(), $id);

        return response()->json(['ok' => true]);
    }

    public function storePlace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        return response()->json($this->me->addPlace($request->user(), $data), 201);
    }

    public function destroyPlace(Request $request, int $id): JsonResponse
    {
        $this->me->removePlace($request->user(), $id);

        return response()->json(['ok' => true]);
    }

    public function storeComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(SupportPolicy::CATEGORIES))],
            'priority' => ['nullable', 'in:'.implode(',', SupportPolicy::PRIORITIES)],
            'booking_id' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'max:2048'],
        ]);
        if ($request->hasFile('file')) {
            $data['attachment'] = $request->file('file');
        }

        return response()->json($this->me->addComplaint($request->user(), $data), 201);
    }

    public function storeRating(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->me->addRating($request->user(), $data), 201);
    }

    public function readNotification(Request $request, int $id): JsonResponse
    {
        $this->me->markNotificationRead($request->user(), $id);

        return response()->json(['ok' => true]);
    }
}
