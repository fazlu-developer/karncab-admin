<?php

namespace App\Services;

use App\Models\User;
use App\Platform\LiveFix;
use App\Platform\LiveFleetMapAccess;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;

class LiveFleetMapService
{
    public function __construct(private readonly LiveLocationStore $store) {}

    /**
     * Driver GPS ingest. Writes the live fix to cache (and Firebase if configured).
     * Last-known lat/lng on users/vehicles is throttled. Bookings are never updated.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function ingest(User $operator, array $data): array
    {
        abort_unless($operator->role === OperatorRole::DRIVER, 403, 'Driver profile required.');
        $driver = $this->requireOwnDriver($operator);
        $recordedAt = $this->recordedAtMs($data['recorded_at'] ?? null);
        $prev = $this->store->get((int) $driver->id);
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        if (LiveFix::shouldSkipPing($prev, $lat, $lng, $recordedAt) && $prev) {
            return $this->presentFix($prev, false);
        }

        $vehicle = $this->db()->table('vehicles')->where('driver_id', $driver->id)->orderByDesc('updated_at')->first();
        $bookingId = isset($data['booking_id']) ? (int) $data['booking_id'] : $this->liveBookingIdForDriver((int) $driver->id);
        $userDistrict = $this->db()->table('users')->where('id', $driver->user_id)->value('district_id');
        $districtId = $vehicle?->district_id ?? $userDistrict;
        $vehicleId = $vehicle?->id;
        $fix = LiveFix::make(
            (int) $driver->id,
            $vehicleId ? (int) $vehicleId : null,
            $bookingId,
            $districtId ? (int) $districtId : null,
            $lat,
            $lng,
            isset($data['heading']) ? (float) $data['heading'] : null,
            isset($data['speed']) ? (float) $data['speed'] : null,
            (string) ($data['trip_status'] ?? 'online'),
            $recordedAt,
        );
        $persist = LiveFix::shouldPersist($prev, $lat, $lng, $recordedAt);
        if ($persist) {
            $this->persistLastKnown((int) $driver->user_id, $vehicleId ? (int) $vehicleId : null, $lat, $lng, $recordedAt);
            $fix['persistedAt'] = now()->toIso8601String();
        }
        $this->store->put($fix);

        return $this->presentFix($fix, $persist);
    }

    /**
     * @return array<string, mixed>
     */
    public function mine(User $operator): array
    {
        abort_unless($operator->role === OperatorRole::DRIVER, 403, 'Driver profile required.');
        $driver = $this->requireOwnDriver($operator);
        $fix = $this->readWithFallback((int) $driver->id, (int) $driver->user_id);

        return $fix ? $this->presentFix($fix, false) : ['visible' => false, 'lastKnown' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $operator, ?string $status = null): array
    {
        abort_unless(LiveFleetMapAccess::canUseMap($operator), 403, 'Live fleet map is limited to Super Admin, State Head, District Head, and Fleet Owner.');
        abort_unless($operator->can('vehicles.view'), 403);

        $vehicles = $this->db()->table('vehicles');
        TerritoryScope::applyVehicles($vehicles, $operator);
        $rows = $vehicles->orderBy('id')->limit(400)->get();
        $driverIds = $rows->pluck('driver_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $vehicleIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hot = $this->store->many($driverIds);
        $trips = $this->liveTrips($vehicleIds, $driverIds);
        $parcels = $this->liveParcels($vehicleIds, $driverIds);
        $drivers = $driverIds === [] ? collect() : $this->db()->table('drivers')->whereIn('id', $driverIds)->get()->keyBy('id');
        $users = $drivers->isEmpty()
            ? collect()
            : $this->db()->table('users')->whereIn('id', $drivers->pluck('user_id'))->get()->keyBy('id');

        $counts = LiveFleetMapAccess::emptyCounts();
        $list = [];
        foreach ($rows as $row) {
            $driver = $row->driver_id ? $drivers->get($row->driver_id) : null;
            $user = $driver ? $users->get($driver->user_id) : null;
            $hasTrip = isset($trips['byVehicle'][(int) $row->id]) || ($row->driver_id && isset($trips['byDriver'][(int) $row->driver_id]));
            $hasParcel = isset($parcels['byVehicle'][(int) $row->id]) || ($row->driver_id && isset($parcels['byDriver'][(int) $row->driver_id]));
            $resolved = LiveFleetMapAccess::resolveStatus([
                'stored' => $row->status,
                'driverOnline' => (bool) ($driver->online ?? false),
                'driverDuty' => $driver->duty_status ?? null,
                'hasActiveTrip' => $hasTrip,
                'hasActiveDelivery' => $hasParcel,
            ]);
            $counts[$resolved['status']]++;
            $fix = $row->driver_id ? ($hot[(int) $row->driver_id] ?? $this->fallbackFix($row, $user, $driver)) : $this->fallbackFix($row, $user, $driver);
            $booking = $trips['rows'][(int) $row->id] ?? ($row->driver_id ? ($trips['driverRows'][(int) $row->driver_id] ?? null) : null);
            $item = [
                'vehicleId' => (int) $row->id,
                'vehicleNumber' => $row->registration_no,
                'driverName' => $user->name ?? null,
                'driverId' => $row->driver_id ? (int) $row->driver_id : null,
                'vehicleType' => $row->category,
                'driverStatus' => $resolved['status'],
                'statusLabel' => $resolved['label'],
                'districtId' => $row->district_id ? (int) $row->district_id : null,
                'fleetOwnerId' => $row->fleet_owner_id ? (int) $row->fleet_owner_id : null,
                'currentLocation' => $fix ? [
                    'lat' => (float) $fix['lat'],
                    'lng' => (float) $fix['lng'],
                    'heading' => $fix['heading'] ?? null,
                    'speed' => $fix['speed'] ?? null,
                    'stale' => LiveFix::isStale($fix['recordedAt'] ?? null),
                ] : null,
                'lastUpdate' => $fix['recordedAt'] ?? ($row->last_fix_at ?? null),
                'currentBooking' => $booking,
                'tripStatus' => $booking['status'] ?? ($fix['tripStatus'] ?? $resolved['status']),
            ];
            if ($status && $item['driverStatus'] !== $status) {
                continue;
            }
            $list[] = $item;
        }

        return [
            'scope' => [
                'role' => $operator->role,
                'stateId' => $operator->state_id,
                'districtId' => $operator->district_id,
                'fleetOwnerId' => $operator->fleet_owner_id,
                'unrestricted' => $operator->isPrivilegedOperator(),
            ],
            'statuses' => LiveFleetMapAccess::LABELS,
            'counts' => $counts,
            'realtime' => [
                'pollMs' => (int) config('karnacab.live_map_poll_ms', 4000),
                'store' => 'cache',
                'ttlSeconds' => LiveFix::CACHE_TTL_SECONDS,
                'googleMaps' => (bool) config('karnacab.google_maps_key'),
                'firebase' => (bool) config('karnacab.firebase_database_url'),
            ],
            'vehicles' => $list,
        ];
    }

    /**
     * Customer: assigned driver during an active booking only.
     * Driver: own trip. Ops: territory-scoped booking.
     *
     * @return array<string, mixed>
     */
    public function bookingLocation(User $operator, int $bookingId): array
    {
        $booking = $this->db()->table('bookings')->where('id', $bookingId)->first();
        abort_if($booking === null, 404, 'Booking not found.');

        $visible = $this->maySeeBookingLocation($operator, $booking);
        if (! $visible) {
            return ['visible' => false, 'location' => null];
        }
        if (! $booking->driver_id) {
            return ['visible' => false, 'location' => null];
        }
        $driver = $this->db()->table('drivers')->where('id', $booking->driver_id)->first();
        $fix = $this->readWithFallback((int) $booking->driver_id, $driver?->user_id ? (int) $driver->user_id : null);

        return [
            'visible' => $fix !== null,
            'location' => $fix ? $this->presentFix($fix, false) : null,
            'booking' => [
                'id' => (int) $booking->id,
                'publicRef' => $booking->public_ref,
                'status' => $booking->status,
            ],
        ];
    }

    private function maySeeBookingLocation(User $operator, object $booking): bool
    {
        if ($operator->role === OperatorRole::CUSTOMER) {
            $platformId = $operator->nest_user_id ?: $operator->id;

            return (int) $booking->customer_id === (int) $platformId
                && $booking->driver_id
                && in_array($booking->status, LiveFleetMapAccess::LIVE_TRIPS, true);
        }
        if ($operator->role === OperatorRole::DRIVER) {
            $driver = $this->ownDriverRow($operator);

            return $driver !== null && (int) $booking->driver_id === (int) $driver->id;
        }
        if (LiveFleetMapAccess::canUseMap($operator)) {
            $q = $this->db()->table('bookings')->where('id', $booking->id);
            TerritoryScope::applyBookings($q, $operator);

            return $q->exists();
        }

        return false;
    }

    private function requireOwnDriver(User $operator): object
    {
        $row = $this->ownDriverRow($operator);
        abort_if($row === null, 403, 'Driver profile required.');

        return $row;
    }

    private function ownDriverRow(User $operator): ?object
    {
        if ($operator->nest_user_id) {
            $row = $this->db()->table('drivers')->where('user_id', $operator->nest_user_id)->first();
            if ($row) {
                return $row;
            }
        }

        $user = $this->db()->table('users')->where('email', $operator->email)->first();

        return $user ? $this->db()->table('drivers')->where('user_id', $user->id)->first() : null;
    }

    private function liveBookingIdForDriver(int $driverId): ?int
    {
        $id = $this->db()->table('bookings')
            ->where('driver_id', $driverId)
            ->whereIn('status', LiveFleetMapAccess::LIVE_TRIPS)
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function persistLastKnown(int $userId, ?int $vehicleId, float $lat, float $lng, int $recordedAtMs): void
    {
        $at = now()->setTimestamp((int) floor($recordedAtMs / 1000));
        $this->db()->table('users')->where('id', $userId)->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'location_updated_at' => $at,
            'updated_at' => now(),
        ]);
        if ($vehicleId) {
            $this->db()->table('vehicles')->where('id', $vehicleId)->update([
                'last_lat' => $lat,
                'last_lng' => $lng,
                'last_fix_at' => $at,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $fix
     * @return array<string, mixed>
     */
    private function presentFix(array $fix, bool $persisted): array
    {
        return [
            'visible' => true,
            'lat' => (float) $fix['lat'],
            'lng' => (float) $fix['lng'],
            'heading' => $fix['heading'] ?? null,
            'speed' => $fix['speed'] ?? null,
            'tripStatus' => $fix['tripStatus'] ?? null,
            'recordedAt' => $fix['recordedAt'] ?? null,
            'stale' => LiveFix::isStale($fix['recordedAt'] ?? null),
            'persisted' => $persisted,
            'driverId' => isset($fix['driverId']) ? (int) $fix['driverId'] : null,
            'vehicleId' => isset($fix['vehicleId']) ? (int) $fix['vehicleId'] : null,
            'bookingId' => isset($fix['bookingId']) ? (int) $fix['bookingId'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readWithFallback(int $driverId, ?int $userId): ?array
    {
        $hot = $this->store->get($driverId);
        if ($hot) {
            return $hot;
        }
        if (! $userId) {
            return null;
        }
        $user = $this->db()->table('users')->where('id', $userId)->first();
        if (! $user || $user->last_lat === null || $user->last_lng === null) {
            return null;
        }

        return LiveFix::make(
            $driverId,
            null,
            null,
            $user->district_id ? (int) $user->district_id : null,
            (float) $user->last_lat,
            (float) $user->last_lng,
            null,
            null,
            'last_known',
            $user->location_updated_at ? strtotime((string) $user->location_updated_at) * 1000 : 0,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallbackFix(object $vehicle, ?object $user, ?object $driver): ?array
    {
        $lat = $user->last_lat ?? $vehicle->last_lat ?? null;
        $lng = $user->last_lng ?? $vehicle->last_lng ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }
        $at = $vehicle->last_fix_at ?? $user->location_updated_at ?? null;

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'heading' => null,
            'speed' => null,
            'tripStatus' => $vehicle->status,
            'recordedAt' => $at ? (string) $at : null,
            'driverId' => $driver->id ?? null,
            'vehicleId' => $vehicle->id,
        ];
    }

    /**
     * @param  list<int>  $vehicleIds
     * @param  list<int>  $driverIds
     * @return array{byVehicle: array<int, true>, byDriver: array<int, true>, rows: array<int, array<string, mixed>>, driverRows: array<int, array<string, mixed>>}
     */
    private function liveTrips(array $vehicleIds, array $driverIds): array
    {
        $empty = ['byVehicle' => [], 'byDriver' => [], 'rows' => [], 'driverRows' => []];
        if ($vehicleIds === [] && $driverIds === []) {
            return $empty;
        }
        $q = $this->db()->table('bookings')->whereIn('status', LiveFleetMapAccess::LIVE_TRIPS);
        $q->where(function ($inner) use ($vehicleIds, $driverIds) {
            if ($vehicleIds !== []) {
                $inner->orWhereIn('vehicle_id', $vehicleIds);
            }
            if ($driverIds !== []) {
                $inner->orWhereIn('driver_id', $driverIds);
            }
            if ($vehicleIds === [] && $driverIds === []) {
                $inner->whereRaw('0 = 1');
            }
        });
        foreach ($q->orderByDesc('id')->get() as $row) {
            $payload = [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
            ];
            if ($row->vehicle_id) {
                $empty['byVehicle'][(int) $row->vehicle_id] = true;
                $empty['rows'][(int) $row->vehicle_id] ??= $payload;
            }
            if ($row->driver_id) {
                $empty['byDriver'][(int) $row->driver_id] = true;
                $empty['driverRows'][(int) $row->driver_id] ??= $payload;
            }
        }

        return $empty;
    }

    /**
     * @param  list<int>  $vehicleIds
     * @param  list<int>  $driverIds
     * @return array{byVehicle: array<int, true>, byDriver: array<int, true>}
     */
    private function liveParcels(array $vehicleIds, array $driverIds): array
    {
        $out = ['byVehicle' => [], 'byDriver' => []];
        if ($vehicleIds === [] && $driverIds === []) {
            return $out;
        }
        $q = $this->db()->table('parcel_shipments')->whereIn('status', LiveFleetMapAccess::LIVE_PARCELS);
        $q->where(function ($inner) use ($vehicleIds, $driverIds) {
            if ($vehicleIds !== []) {
                $inner->orWhereIn('vehicle_id', $vehicleIds);
            }
            if ($driverIds !== []) {
                $inner->orWhereIn('driver_id', $driverIds);
            }
        });
        foreach ($q->get() as $row) {
            if ($row->vehicle_id) {
                $out['byVehicle'][(int) $row->vehicle_id] = true;
            }
            if ($row->driver_id) {
                $out['byDriver'][(int) $row->driver_id] = true;
            }
        }

        return $out;
    }

    private function recordedAtMs(?string $iso): int
    {
        if ($iso) {
            $at = strtotime($iso);
            if ($at !== false) {
                return $at * 1000;
            }
        }

        return (int) floor(microtime(true) * 1000);
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
