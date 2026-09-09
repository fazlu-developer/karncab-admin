<?php

namespace App\Services;

use App\Models\User;
use App\Platform\FleetVehicleStatus;
use App\Platform\OperatorRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FleetOwnerService
{
    public function overview(User $operator): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.view');
        $vehicles = $this->vehicleRows($fleet);
        $onTrip = $this->activeTripDriverIds($vehicles);

        return [
            'fleet' => $this->presentFleet($fleet),
            'kpis' => [
                'vehicles' => count($vehicles),
                'drivers' => $this->driversQuery($fleet)->count(),
                'onlineDrivers' => $this->driversQuery($fleet)->where('online', 1)->count(),
                'tripsToday' => $this->tripsQuery($fleet)->where('bookings.status', 'COMPLETED')->where('bookings.updated_at', '>=', now()->startOfDay())->count(),
                'todayRevenueRupees' => ((int) $this->tripsQuery($fleet)->where('bookings.status', 'COMPLETED')->where('bookings.updated_at', '>=', now()->startOfDay())->sum('bookings.quote_paise')) / 100,
            ],
            'vehicles' => array_map(fn ($row) => $this->presentVehicle($row, $onTrip), $vehicles),
            'statuses' => FleetVehicleStatus::LABELS,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vehicles(User $operator): array
    {
        $fleet = $this->requireFleet($operator, 'vehicles.view');
        $rows = $this->vehicleRows($fleet);
        $onTrip = $this->activeTripDriverIds($rows);

        return array_map(fn ($row) => $this->presentVehicle($row, $onTrip), $rows);
    }

    public function vehicle(User $operator, int $id): array
    {
        $fleet = $this->requireFleet($operator, 'vehicles.view');
        $row = $this->requireVehicle($fleet, $id);
        $onTrip = $this->activeTripDriverIds([$row]);
        $payload = $this->presentVehicle($row, $onTrip);
        $payload['documents'] = $this->vehicleDocuments($id);
        $payload['drivers'] = $this->drivers($operator);
        $payload['trips'] = $this->tripsQuery($fleet)->where('bookings.vehicle_id', $id)->orderByDesc('bookings.id')->limit(20)->get()->map(fn ($trip) => $this->presentTrip($trip))->all();
        $payload['utilization'] = $this->vehicleUtilization($id);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addVehicle(User $operator, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $districtId = (int) ($data['district_id'] ?? $operator->district_id);
        abort_if($districtId < 1, 422, 'Set a district on the fleet owner profile first.');
        abort_unless($this->db()->table('districts')->where('id', $districtId)->exists(), 422, 'District not found.');
        $registration = strtoupper(preg_replace('/\s+/', '', $data['registration_no']));
        try {
            $id = $this->db()->table('vehicles')->insertGetId([
                'fleet_owner_id' => $fleet->id,
                'district_id' => $districtId,
                'category' => $data['category'],
                'registration_no' => $registration,
                'status' => 'available',
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'year' => $data['year'] ?? null,
                'color' => $data['color'] ?? null,
                'fuel' => $data['fuel'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                abort(409, 'Registration number already exists.');
            }
            throw $exception;
        }

        return $this->vehicle($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVehicle(User $operator, int $id, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireVehicle($fleet, $id);
        $patch = ['updated_at' => now()];
        foreach (['brand', 'model', 'year', 'color', 'fuel', 'category'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $data[$field];
            }
        }
        if (! empty($data['registration_no'])) {
            $patch['registration_no'] = strtoupper(preg_replace('/\s+/', '', $data['registration_no']));
        }
        if (! empty($data['status'])) {
            abort_unless(in_array($data['status'], FleetVehicleStatus::WRITABLE, true), 422, 'That vehicle status is derived and cannot be set directly.');
            $patch['status'] = $data['status'];
        }
        try {
            $this->db()->table('vehicles')->where('id', $id)->update($patch);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                abort(409, 'Registration number already exists.');
            }
            throw $exception;
        }

        return $this->vehicle($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addVehicleDocument(User $operator, int $id, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireVehicle($fleet, $id);
        abort_unless(in_array($data['type'], FleetVehicleStatus::DOC_TYPES, true), 422, 'Unknown vehicle document type.');
        $existing = $this->db()->table('vehicle_documents')->where('vehicle_id', $id)->where('type', $data['type'])->value('id');
        $payload = [
            'vehicle_id' => $id,
            'type' => $data['type'],
            'status' => 'under_review',
            'storage_key' => $data['storage_key'],
            'original_name' => $data['original_name'] ?? null,
            'mime' => $data['mime'] ?? 'application/octet-stream',
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'checksum_sha256' => $data['checksum_sha256'] ?? str_repeat('0', 64),
            'expires_at' => $data['expires_at'] ?? null,
            'updated_at' => now(),
        ];
        if ($existing) {
            $this->db()->table('vehicle_documents')->where('id', $existing)->update($payload);
        } else {
            $payload['created_at'] = now();
            $this->db()->table('vehicle_documents')->insert($payload);
        }

        return $this->vehicle($operator, $id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function drivers(User $operator): array
    {
        $fleet = $this->requireFleet($operator, 'drivers.view');

        return $this->driversQuery($fleet)
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.*', 'users.name', 'users.email', 'users.phone', 'users.status as account_status')
            ->orderByDesc('drivers.id')
            ->get()
            ->map(fn ($row) => $this->presentDriver($row, $fleet))
            ->all();
    }

    public function driver(User $operator, int $id): array
    {
        $fleet = $this->requireFleet($operator, 'drivers.view');
        $row = $this->requireDriver($fleet, $id);
        $payload = $this->presentDriver($row, $fleet);
        $payload['documents'] = $this->db()->table('driver_documents')->where('driver_id', $id)->orderBy('id')->get()->toArray();
        $payload['trips'] = $this->tripsQuery($fleet)->where('bookings.driver_id', $id)->orderByDesc('bookings.id')->limit(20)->get()->map(fn ($trip) => $this->presentTrip($trip))->all();
        $payload['performance'] = $this->driverPerformance($id);
        $payload['earnings'] = $this->driverEarnings((int) $row->user_id);
        $payload['vehicles'] = $this->vehicles($operator);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDriver(User $operator, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $email = strtolower($data['email']);
        $existing = $this->db()->table('users')
            ->where(function ($q) use ($email, $data) {
                $q->where('email', $email);
                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
            })
            ->first();
        if ($existing) {
            abort_unless($existing->role === 'DRIVER', 409, 'That email or phone is already registered.');
            $driver = $this->db()->table('drivers')->where('user_id', $existing->id)->first();
            abort_if($driver && $driver->fleet_owner_id && (int) $driver->fleet_owner_id !== (int) $fleet->id, 409, 'Driver already belongs to another fleet.');
            if ($driver) {
                $this->db()->table('drivers')->where('id', $driver->id)->update([
                    'fleet_owner_id' => $fleet->id,
                    'license_no' => $data['license_no'] ?? $driver->license_no,
                    'city' => $data['city'] ?? $driver->city,
                    'updated_at' => now(),
                ]);

                return $this->driver($operator, (int) $driver->id);
            }
        }
        $userId = $existing?->id ?? $this->db()->table('users')->insertGetId([
            'role' => 'DRIVER',
            'status' => 'ACTIVE',
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'state_id' => $operator->state_id,
            'district_id' => $operator->district_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $driverId = $this->db()->table('drivers')->insertGetId([
            'user_id' => $userId,
            'fleet_owner_id' => $fleet->id,
            'license_no' => $data['license_no'] ?? 'PENDING',
            'city' => $data['city'] ?? null,
            'kyc_status' => 'pending',
            'duty_status' => 'offline',
            'online' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->ensureWallet($userId, 'DRIVER');

        return $this->driver($operator, $driverId);
    }

    public function assignDriver(User $operator, int $driverId, int $vehicleId): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireDriver($fleet, $driverId);
        $vehicle = $this->requireVehicle($fleet, $vehicleId);
        abort_if(FleetVehicleStatus::isLocked($vehicle->status), 422, 'Vehicle is in maintenance or suspended.');
        $this->db()->table('vehicles')
            ->where('fleet_owner_id', $fleet->id)
            ->where('driver_id', $driverId)
            ->where('id', '!=', $vehicleId)
            ->update(['driver_id' => null, 'status' => 'available', 'updated_at' => now()]);
        $this->db()->table('vehicles')->where('id', $vehicleId)->update([
            'driver_id' => $driverId,
            'status' => $vehicle->status === 'offline' ? 'available' : $vehicle->status,
            'updated_at' => now(),
        ]);

        return $this->driver($operator, $driverId);
    }

    public function unassignDriver(User $operator, int $driverId, ?int $vehicleId = null): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireDriver($fleet, $driverId);
        $q = $this->db()->table('vehicles')->where('fleet_owner_id', $fleet->id)->where('driver_id', $driverId);
        if ($vehicleId) {
            $q->where('id', $vehicleId);
        }
        $q->update(['driver_id' => null, 'status' => 'available', 'updated_at' => now()]);

        return $this->driver($operator, $driverId);
    }

    public function removeDriver(User $operator, int $driverId): array
    {
        $this->unassignDriver($operator, $driverId);
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireDriver($fleet, $driverId);
        $this->db()->table('drivers')->where('id', $driverId)->update([
            'fleet_owner_id' => null,
            'online' => false,
            'duty_status' => 'offline',
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'id' => $driverId];
    }

    public function setDriverKyc(User $operator, int $driverId, string $kyc, ?string $reason = null): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $row = $this->requireDriver($fleet, $driverId);
        abort_unless(in_array($kyc, ['pending', 'under_review', 'verified', 'rejected'], true), 422, 'Invalid KYC status.');
        if ($kyc === 'verified' && $row->kyc_status !== 'verified') {
            abort_unless($operator->can('drivers.approve'), 403, 'Fleet owners cannot verify driver KYC.');
        }
        $this->db()->table('drivers')->where('id', $driverId)->update([
            'kyc_status' => $kyc,
            'kyc_rejected_reason' => $kyc === 'rejected' ? $reason : null,
            'updated_at' => now(),
        ]);

        return $this->driver($operator, $driverId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDriverDocument(User $operator, int $driverId, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $this->requireDriver($fleet, $driverId);
        abort_unless(in_array($data['type'], FleetVehicleStatus::DRIVER_DOC_TYPES, true), 422, 'Unknown driver document type.');
        $this->db()->table('driver_documents')->insert([
            'driver_id' => $driverId,
            'type' => $data['type'],
            'status' => 'pending',
            'storage_key' => $data['storage_key'],
            'original_name' => $data['original_name'] ?? null,
            'mime' => $data['mime'] ?? 'application/octet-stream',
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'checksum_sha256' => $data['checksum_sha256'] ?? str_repeat('0', 64),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->driver($operator, $driverId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setDriverStatus(User $operator, int $driverId, array $data): array
    {
        $fleet = $this->requireFleet($operator, 'fleet.manage');
        $row = $this->requireDriver($fleet, $driverId);
        if (! empty($data['account_status'])) {
            abort_unless(in_array($data['account_status'], ['ACTIVE', 'PENDING', 'SUSPENDED'], true), 422);
            $this->db()->table('users')->where('id', $row->user_id)->update([
                'status' => $data['account_status'],
                'updated_at' => now(),
            ]);
        }
        $patch = ['updated_at' => now()];
        if (! empty($data['duty_status'])) {
            abort_unless(in_array($data['duty_status'], ['offline', 'online', 'on_trip', 'busy', 'maintenance', 'suspended'], true), 422);
            $patch['duty_status'] = $data['duty_status'];
            $patch['online'] = $data['duty_status'] === 'online';
        }
        if (array_key_exists('online', $data)) {
            $patch['online'] = (bool) $data['online'];
            if ($data['online'] && empty($data['duty_status'])) {
                $patch['duty_status'] = 'online';
            }
            if (! $data['online'] && empty($data['duty_status'])) {
                $patch['duty_status'] = 'offline';
            }
        }
        $this->db()->table('drivers')->where('id', $driverId)->update($patch);

        return $this->driver($operator, $driverId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trips(User $operator): array
    {
        $fleet = $this->requireFleet($operator, 'bookings.view');

        return $this->tripsQuery($fleet)->orderByDesc('bookings.id')->limit(80)->get()->map(fn ($row) => $this->presentTrip($row))->all();
    }

    public function reports(User $operator): array
    {
        $fleet = $this->requireFleet($operator, 'reports.view');
        $completed = $this->tripsQuery($fleet)->where('bookings.status', 'COMPLETED');
        $vehicles = $this->vehicleRows($fleet);
        $onTrip = $this->activeTripDriverIds($vehicles);
        $statusCounts = [];
        foreach ($vehicles as $row) {
            $resolved = FleetVehicleStatus::resolve($this->resolveInput($row, $onTrip));
            $statusCounts[$resolved['status']] = ($statusCounts[$resolved['status']] ?? 0) + 1;
        }
        $driverRows = $this->drivers($operator);
        $rule = $this->db()->table('commission_rules')->where('active', 1)->where('name', 'default')->first()
            ?? $this->db()->table('commission_rules')->where('active', 1)->first();

        return [
            'fleet' => $this->presentFleet($fleet),
            'trips' => [
                'today' => (clone $completed)->where('bookings.updated_at', '>=', now()->startOfDay())->count(),
                'month' => (clone $completed)->where('bookings.updated_at', '>=', now()->startOfMonth())->count(),
                'total' => (clone $completed)->count(),
            ],
            'revenue' => [
                'todayRupees' => ((int) (clone $completed)->where('bookings.updated_at', '>=', now()->startOfDay())->sum('bookings.quote_paise')) / 100,
                'monthRupees' => ((int) (clone $completed)->where('bookings.updated_at', '>=', now()->startOfMonth())->sum('bookings.quote_paise')) / 100,
                'totalRupees' => ((int) (clone $completed)->sum('bookings.quote_paise')) / 100,
            ],
            'commission' => [
                'percent' => $rule ? (float) $rule->percent : null,
                'configured' => $rule !== null,
                'monthRupees' => $this->fleetCommissionPaise($fleet, now()->startOfMonth()) / 100,
            ],
            'vehicleUtilization' => array_map(fn ($row) => $this->vehicleUtilization((int) $row->id) + [
                'id' => (int) $row->id,
                'registrationNo' => $row->registration_no,
            ], $vehicles),
            'driverPerformance' => array_map(fn ($row) => $row['performance'] + [
                'id' => $row['id'],
                'name' => $row['name'],
                'earnings' => $this->driverEarnings($row['userId']),
            ], $driverRows),
            'vehicleStatuses' => $statusCounts,
            'recentTrips' => $this->trips($operator),
        ];
    }

    private function requireFleet(User $operator, string $ability): object
    {
        abort_unless($operator->can($ability), 403);
        abort_unless($operator->role === OperatorRole::FLEET_OWNER, 403, 'Fleet workspace is limited to fleet owners.');
        abort_if(! $operator->fleet_owner_id, 409, 'No fleet is assigned to this owner.');
        $fleet = $this->db()->table('fleet_owners')->where('id', $operator->fleet_owner_id)->first();
        abort_if($fleet === null, 409, 'No fleet is assigned to this owner.');

        return $fleet;
    }

    private function requireVehicle(object $fleet, int $id): object
    {
        $row = $this->db()->table('vehicles')->where('id', $id)->where('fleet_owner_id', $fleet->id)->first();
        abort_if($row === null, 404, 'Vehicle not found.');

        return $row;
    }

    private function requireDriver(object $fleet, int $id): object
    {
        $row = $this->driversQuery($fleet)
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.*', 'users.name', 'users.email', 'users.phone', 'users.status as account_status')
            ->where('drivers.id', $id)
            ->first();
        abort_if($row === null, 404, 'Driver not found.');

        return $row;
    }

    /**
     * @return list<object>
     */
    private function vehicleRows(object $fleet): array
    {
        return $this->db()->table('vehicles')->where('fleet_owner_id', $fleet->id)->orderByDesc('id')->get()->all();
    }

    private function driversQuery(object $fleet)
    {
        return $this->db()->table('drivers')->where('drivers.fleet_owner_id', $fleet->id);
    }

    private function tripsQuery(object $fleet)
    {
        $driverIds = $this->db()->table('drivers')->where('fleet_owner_id', $fleet->id)->pluck('id');
        $vehicleIds = $this->db()->table('vehicles')->where('fleet_owner_id', $fleet->id)->pluck('id');

        return $this->db()->table('bookings')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->where(function ($q) use ($driverIds, $vehicleIds) {
                $matched = false;
                if ($vehicleIds->isNotEmpty()) {
                    $q->orWhereIn('bookings.vehicle_id', $vehicleIds);
                    $matched = true;
                }
                if ($driverIds->isNotEmpty()) {
                    $q->orWhereIn('bookings.driver_id', $driverIds);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('0 = 1');
                }
            })
            ->select(
                'bookings.id',
                'bookings.public_ref',
                'bookings.status',
                'bookings.pickup_text',
                'bookings.drop_text',
                'bookings.quote_paise',
                'bookings.driver_id',
                'bookings.vehicle_id',
                'bookings.updated_at',
                'vehicles.registration_no',
            );
    }

    /**
     * @param  list<object>  $vehicles
     * @return array<int, true>
     */
    private function activeTripDriverIds(array $vehicles): array
    {
        $ids = array_values(array_filter(array_map(fn ($row) => $row->driver_id ? (int) $row->driver_id : null, $vehicles)));
        if ($ids === []) {
            return [];
        }
        $live = $this->db()->table('bookings')
            ->whereIn('driver_id', $ids)
            ->whereIn('status', FleetVehicleStatus::ACTIVE_TRIPS)
            ->pluck('driver_id');
        $set = [];
        foreach ($live as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @param  array<int, true>  $onTrip
     * @return array<string, mixed>
     */
    private function presentVehicle(object $row, array $onTrip): array
    {
        $resolved = FleetVehicleStatus::resolve($this->resolveInput($row, $onTrip));
        $docs = $this->vehicleDocuments((int) $row->id);
        $assigned = null;
        if ($row->driver_id) {
            $driver = $this->db()->table('drivers')->where('id', $row->driver_id)->first();
            $user = $driver ? $this->db()->table('users')->where('id', $driver->user_id)->first() : null;
            $assigned = $driver ? [
                'id' => (int) $driver->id,
                'name' => $user->name ?? null,
                'online' => (bool) $driver->online,
                'dutyStatus' => $driver->duty_status ?? 'offline',
            ] : null;
        }

        return [
            'id' => (int) $row->id,
            'registrationNo' => $row->registration_no,
            'category' => $row->category,
            'brand' => $row->brand ?? null,
            'model' => $row->model ?? null,
            'year' => $row->year ?? null,
            'color' => $row->color ?? null,
            'fuel' => $row->fuel ?? null,
            'districtId' => (int) $row->district_id,
            'storedStatus' => $row->status,
            'status' => $resolved['status'],
            'statusLabel' => $resolved['label'],
            'locked' => $resolved['locked'],
            'assignedDriver' => $assigned,
            'documents' => $docs,
            'documentAlerts' => $this->docAlerts($docs),
        ];
    }

    /**
     * @param  array<int, true>  $onTrip
     * @return array{stored: string, assignedDriverId: mixed, driverOnline: bool, driverDuty: ?string, hasActiveTrip: bool}
     */
    private function resolveInput(object $row, array $onTrip): array
    {
        $driver = $row->driver_id ? $this->db()->table('drivers')->where('id', $row->driver_id)->first() : null;

        return [
            'stored' => (string) $row->status,
            'assignedDriverId' => $row->driver_id,
            'driverOnline' => (bool) ($driver->online ?? false),
            'driverDuty' => $driver->duty_status ?? null,
            'hasActiveTrip' => isset($onTrip[(int) $row->driver_id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDriver(object $row, object $fleet): array
    {
        $assigned = $this->db()->table('vehicles')->where('fleet_owner_id', $fleet->id)->where('driver_id', $row->id)->first();

        return [
            'id' => (int) $row->id,
            'userId' => (int) $row->user_id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'accountStatus' => $row->account_status ?? 'ACTIVE',
            'licenseNo' => $row->license_no,
            'city' => $row->city ?? null,
            'kycStatus' => $row->kyc_status,
            'dutyStatus' => $row->duty_status ?? 'offline',
            'online' => (bool) $row->online,
            'ratingAvg' => (float) ($row->rating_avg ?? 0),
            'assignedVehicle' => $assigned ? [
                'id' => (int) $assigned->id,
                'registrationNo' => $assigned->registration_no,
                'status' => $assigned->status,
            ] : null,
            'performance' => $this->driverPerformance((int) $row->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTrip(object $row): array
    {
        $driverName = null;
        if ($row->driver_id) {
            $userId = $this->db()->table('drivers')->where('id', $row->driver_id)->value('user_id');
            $driverName = $userId ? $this->db()->table('users')->where('id', $userId)->value('name') : null;
        }

        return [
            'id' => (int) $row->id,
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'pickupText' => $row->pickup_text,
            'dropText' => $row->drop_text,
            'quoteRupees' => ((int) ($row->quote_paise ?? 0)) / 100,
            'registrationNo' => $row->registration_no ?? null,
            'driverName' => $driverName,
            'updatedAt' => (string) $row->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentFleet(object $fleet): array
    {
        return [
            'id' => (int) $fleet->id,
            'tradeName' => $fleet->trade_name,
            'gstin' => $fleet->gstin ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vehicleDocuments(int $vehicleId): array
    {
        return $this->db()->table('vehicle_documents')->where('vehicle_id', $vehicleId)->orderBy('id')->get()->map(fn ($doc) => [
            'id' => (int) $doc->id,
            'type' => $doc->type,
            'label' => FleetVehicleStatus::DOC_LABELS[$doc->type] ?? $doc->type,
            'status' => $doc->status,
            'expiresAt' => $doc->expires_at,
            'originalName' => $doc->original_name ?? null,
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return list<string>
     */
    private function docAlerts(array $docs): array
    {
        $alerts = [];
        foreach ($docs as $doc) {
            if (empty($doc['expiresAt'])) {
                continue;
            }
            $expires = strtotime((string) $doc['expiresAt']);
            if ($expires !== false && $expires <= strtotime('+30 days')) {
                $alerts[] = ($doc['label'] ?? $doc['type']).' expires '.$doc['expiresAt'];
            }
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleUtilization(int $vehicleId): array
    {
        $completed = $this->db()->table('bookings')->where('vehicle_id', $vehicleId)->where('status', 'COMPLETED')->count();
        $month = $this->db()->table('bookings')->where('vehicle_id', $vehicleId)->where('status', 'COMPLETED')->where('updated_at', '>=', now()->startOfMonth())->count();

        return [
            'completedTrips' => $completed,
            'monthTrips' => $month,
            'monthRevenueRupees' => ((int) $this->db()->table('bookings')->where('vehicle_id', $vehicleId)->where('status', 'COMPLETED')->where('updated_at', '>=', now()->startOfMonth())->sum('quote_paise')) / 100,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPerformance(int $driverId): array
    {
        $completed = $this->db()->table('bookings')->where('driver_id', $driverId)->where('status', 'COMPLETED');

        return [
            'completedTrips' => (clone $completed)->count(),
            'monthTrips' => (clone $completed)->where('updated_at', '>=', now()->startOfMonth())->count(),
            'ratingAvg' => (float) ($this->db()->table('booking_ratings')->whereIn('booking_id', $this->db()->table('bookings')->where('driver_id', $driverId)->select('id'))->avg('stars') ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function driverEarnings(int $userId): array
    {
        $walletId = $this->db()->table('wallets')->where('owner_type', 'DRIVER')->where('owner_user_id', $userId)->value('id');
        $q = $this->db()->table('wallet_ledger')->where('kind', 'trip')->where('direction', 'CREDIT');
        if ($walletId) {
            $q->where('wallet_id', $walletId);
        } else {
            $q->where('account', 'DRIVER');
        }
        $entries = $q->get();
        $fold = function ($since) use ($entries) {
            $net = $commission = $gross = 0;
            foreach ($entries as $row) {
                $created = $row->created_at ?? null;
                if ($since && $created && strtotime((string) $created) < strtotime((string) $since)) {
                    continue;
                }
                $net += (int) $row->amount_paise;
                $commission += (int) ($row->commission_paise ?? 0);
                $gross += (int) ($row->gross_paise ?? $row->amount_paise);
            }

            return [
                'grossRupees' => $gross / 100,
                'commissionRupees' => $commission / 100,
                'netRupees' => $net / 100,
            ];
        };

        return [
            'today' => $fold(now()->startOfDay()),
            'week' => $fold(now()->subDays(6)->startOfDay()),
        ];
    }

    private function fleetCommissionPaise(object $fleet, $since): int
    {
        $userIds = $this->driversQuery($fleet)->pluck('user_id');
        if ($userIds->isEmpty()) {
            return 0;
        }
        $walletIds = $this->db()->table('wallets')->where('owner_type', 'DRIVER')->whereIn('owner_user_id', $userIds)->pluck('id');
        $q = $this->db()->table('wallet_ledger')->where('kind', 'trip')->where('direction', 'CREDIT')->where('created_at', '>=', $since);
        if ($walletIds->isNotEmpty()) {
            $q->whereIn('wallet_id', $walletIds);
        } else {
            return 0;
        }

        return (int) $q->sum('commission_paise');
    }

    private function ensureWallet(int $userId, string $ownerType): void
    {
        $exists = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $userId)->exists();
        if (! $exists) {
            $this->db()->table('wallets')->insert([
                'owner_type' => $ownerType,
                'owner_user_id' => $userId,
                'balance_paise' => 0,
            ]);
        }
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000' || str_contains($exception->getMessage(), 'UNIQUE') || str_contains($exception->getMessage(), 'unique');
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
