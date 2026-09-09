<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\FleetVehicleStatus;
use App\Services\FleetOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function __construct(private readonly FleetOwnerService $fleet) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->fleet->overview($request->user()));
    }

    public function vehicles(Request $request): JsonResponse
    {
        return response()->json(['vehicles' => $this->fleet->vehicles($request->user())]);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_no' => ['required', 'string', 'max:20'],
            'category' => ['required', 'in:'.implode(',', FleetVehicleStatus::CATEGORIES)],
            'district_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'fuel' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json($this->fleet->addVehicle($request->user(), $data), 201);
    }

    public function showVehicle(Request $request, int $id): JsonResponse
    {
        return response()->json($this->fleet->vehicle($request->user(), $id));
    }

    public function updateVehicle(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'registration_no' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'in:'.implode(',', FleetVehicleStatus::CATEGORIES)],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'fuel' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:'.implode(',', FleetVehicleStatus::WRITABLE)],
        ]);

        return response()->json($this->fleet->updateVehicle($request->user(), $id, $data));
    }

    public function storeVehicleDocument(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', FleetVehicleStatus::DOC_TYPES)],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return response()->json($this->fleet->addVehicleDocument($request->user(), $id, $data));
    }

    public function drivers(Request $request): JsonResponse
    {
        return response()->json(['drivers' => $this->fleet->drivers($request->user())]);
    }

    public function storeDriver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'license_no' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json($this->fleet->addDriver($request->user(), $data), 201);
    }

    public function showDriver(Request $request, int $id): JsonResponse
    {
        return response()->json($this->fleet->driver($request->user(), $id));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer']]);

        return response()->json($this->fleet->assignDriver($request->user(), $id, (int) $data['vehicle_id']));
    }

    public function unassign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['vehicle_id' => ['nullable', 'integer']]);

        return response()->json($this->fleet->unassignDriver($request->user(), $id, isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null));
    }

    public function destroyDriver(Request $request, int $id): JsonResponse
    {
        return response()->json($this->fleet->removeDriver($request->user(), $id));
    }

    public function driverKyc(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kyc_status' => ['required', 'in:pending,under_review,verified,rejected'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->fleet->setDriverKyc($request->user(), $id, $data['kyc_status'], $data['reason'] ?? null));
    }

    public function driverDocument(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', FleetVehicleStatus::DRIVER_DOC_TYPES)],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return response()->json($this->fleet->addDriverDocument($request->user(), $id, $data));
    }

    public function driverStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'account_status' => ['nullable', 'in:ACTIVE,PENDING,SUSPENDED'],
            'duty_status' => ['nullable', 'in:offline,online,on_trip,busy,maintenance,suspended'],
            'online' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->fleet->setDriverStatus($request->user(), $id, $data));
    }

    public function trips(Request $request): JsonResponse
    {
        return response()->json(['trips' => $this->fleet->trips($request->user())]);
    }

    public function reports(Request $request): JsonResponse
    {
        return response()->json($this->fleet->reports($request->user()));
    }
}
