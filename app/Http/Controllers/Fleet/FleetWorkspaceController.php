<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Platform\FleetVehicleStatus;
use App\Services\FleetOwnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FleetWorkspaceController extends Controller
{
    public function __construct(private readonly FleetOwnerService $fleet) {}

    public function dashboard(Request $request): View
    {
        return $this->page(fn () => view('fleet.dashboard', $this->fleet->overview($request->user())));
    }

    public function vehicles(Request $request): View
    {
        return $this->page(fn () => view('fleet.vehicles', [
            'rows' => $this->fleet->vehicles($request->user()),
            'categories' => FleetVehicleStatus::CATEGORIES,
            'statuses' => FleetVehicleStatus::LABELS,
        ]));
    }

    public function storeVehicle(Request $request): RedirectResponse
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
        $row = $this->fleet->addVehicle($request->user(), $data);

        return redirect()->route('fleet.vehicle', $row['id'])->with('status', 'Vehicle added.');
    }

    public function showVehicle(Request $request, int $vehicle): View
    {
        return $this->page(fn () => view('fleet.vehicle', [
            'row' => $this->fleet->vehicle($request->user(), $vehicle),
            'categories' => FleetVehicleStatus::CATEGORIES,
            'writableStatuses' => FleetVehicleStatus::WRITABLE,
            'docTypes' => FleetVehicleStatus::DOC_TYPES,
            'docLabels' => FleetVehicleStatus::DOC_LABELS,
        ]));
    }

    public function updateVehicle(Request $request, int $vehicle): RedirectResponse
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
        $this->fleet->updateVehicle($request->user(), $vehicle, $data);

        return back()->with('status', 'Vehicle updated.');
    }

    public function storeVehicleDocument(Request $request, int $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', FleetVehicleStatus::DOC_TYPES)],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $this->fleet->addVehicleDocument($request->user(), $vehicle, $data);

        return back()->with('status', 'Document recorded.');
    }

    public function drivers(Request $request): View
    {
        return $this->page(fn () => view('fleet.drivers', [
            'rows' => $this->fleet->drivers($request->user()),
        ]));
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'license_no' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
        $row = $this->fleet->addDriver($request->user(), $data);

        return redirect()->route('fleet.driver', $row['id'])->with('status', 'Driver added to this fleet.');
    }

    public function showDriver(Request $request, int $driver): View
    {
        return $this->page(fn () => view('fleet.driver', [
            'row' => $this->fleet->driver($request->user(), $driver),
            'docTypes' => FleetVehicleStatus::DRIVER_DOC_TYPES,
        ]));
    }

    public function assign(Request $request, int $driver): RedirectResponse
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer']]);
        $this->fleet->assignDriver($request->user(), $driver, (int) $data['vehicle_id']);

        return back()->with('status', 'Driver assigned.');
    }

    public function unassign(Request $request, int $driver): RedirectResponse
    {
        $data = $request->validate(['vehicle_id' => ['nullable', 'integer']]);
        $this->fleet->unassignDriver($request->user(), $driver, isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null);

        return back()->with('status', 'Driver removed from the vehicle.');
    }

    public function removeDriver(Request $request, int $driver): RedirectResponse
    {
        $this->fleet->removeDriver($request->user(), $driver);

        return redirect()->route('fleet.drivers')->with('status', 'Driver removed from this fleet.');
    }

    public function driverKyc(Request $request, int $driver): RedirectResponse
    {
        $data = $request->validate([
            'kyc_status' => ['required', 'in:pending,under_review,verified,rejected'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->fleet->setDriverKyc($request->user(), $driver, $data['kyc_status'], $data['reason'] ?? null);

        return back()->with('status', 'Driver KYC updated.');
    }

    public function driverDocument(Request $request, int $driver): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', FleetVehicleStatus::DRIVER_DOC_TYPES)],
            'storage_key' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:180'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $this->fleet->addDriverDocument($request->user(), $driver, $data);

        return back()->with('status', 'Driver document recorded.');
    }

    public function driverStatus(Request $request, int $driver): RedirectResponse
    {
        $data = $request->validate([
            'account_status' => ['nullable', 'in:ACTIVE,PENDING,SUSPENDED'],
            'duty_status' => ['nullable', 'in:offline,online,on_trip,busy,maintenance,suspended'],
            'online' => ['nullable', 'boolean'],
        ]);
        $this->fleet->setDriverStatus($request->user(), $driver, $data);

        return back()->with('status', 'Driver status updated.');
    }

    public function reports(Request $request): View
    {
        return $this->page(fn () => view('fleet.reports', $this->fleet->reports($request->user())));
    }

    private function page(callable $view): View
    {
        try {
            return $view();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                return view('fleet.unassigned');
            }
            throw $exception;
        }
    }
}
