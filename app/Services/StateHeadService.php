<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StateHeadService
{
    public const SECTIONS = [
        'districts', 'district-heads', 'franchises', 'fleets', 'drivers', 'vehicles',
        'bookings', 'parcels', 'map', 'complaints', 'revenue', 'commission', 'reports', 'notifications',
    ];

    /**
     * @return array{stateId: int, stateName: string, districtIds: list<int>}
     */
    public function context(User $operator, ?int $requestedStateId = null): array
    {
        abort_unless($operator->can('state.operate'), 403);

        if ($operator->isStateHead()) {
            abort_if(! $operator->state_id, 409, 'No state is assigned to this State Head.');
            $stateId = (int) $operator->state_id;
        } else {
            abort_unless($operator->isPrivilegedOperator(), 403);
            $stateId = (int) ($requestedStateId ?: $operator->state_id);
            abort_if($stateId < 1, 422, 'stateId is required.');
        }

        $state = $this->db()->table('states')->where('id', $stateId)->first();
        abort_if($state === null, 404, 'State not found.');

        return [
            'stateId' => $stateId,
            'stateName' => (string) $state->name,
            'districtIds' => TerritoryScope::districtIdsForState($stateId),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function dashboard(User $operator, array $query = []): array
    {
        $ctx = $this->context($operator, isset($query['stateId']) ? (int) $query['stateId'] : null);
        $bookings = $this->bookings($ctx);
        $completed = (clone $bookings)->where('status', 'COMPLETED');

        return [
            'state' => $ctx,
            'kpis' => [
                'districts' => count($ctx['districtIds']),
                'districtHeads' => $this->users($ctx)->where('role', 'DISTRICT_HEAD')->count(),
                'franchises' => $this->franchisesQuery($ctx)->count(),
                'fleets' => $this->fleetsQuery($ctx)->count(),
                'drivers' => $this->driversQuery($ctx)->count(),
                'onlineDrivers' => $this->driversQuery($ctx)->where('drivers.online', 1)->count(),
                'vehicles' => $this->vehiclesQuery($ctx)->count(),
                'activeRides' => (clone $bookings)->whereIn('status', [
                    'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'ONGOING', 'STARTED',
                ])->count(),
                'completedRides' => (clone $completed)->count(),
                'parcels' => $this->parcelsQuery($ctx)->count(),
                'todayRevenueRupees' => ((int) (clone $completed)->where('created_at', '>=', now()->startOfDay())->sum('quote_paise')) / 100,
                'monthlyRevenueRupees' => ((int) (clone $completed)->where('created_at', '>=', now()->startOfMonth())->sum('quote_paise')) / 100,
                'openComplaints' => $this->complaintsQuery($ctx)->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
            'districts' => $this->districtRows($ctx),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function section(string $section, User $operator, array $query = []): array
    {
        if (! in_array($section, self::SECTIONS, true)) {
            throw new InvalidArgumentException('Unknown state section');
        }
        $ctx = $this->context($operator, isset($query['stateId']) ? (int) $query['stateId'] : null);

        return match ($section) {
            'districts' => ['section' => $section, 'state' => $ctx, 'rows' => $this->districtRows($ctx)],
            'district-heads' => ['section' => $section, 'state' => $ctx, 'rows' => $this->districtHeadRows($ctx), 'districts' => $this->districtOptions($ctx)],
            'franchises' => ['section' => $section, 'state' => $ctx, 'rows' => $this->franchiseRows($ctx), 'districts' => $this->districtOptions($ctx)],
            'fleets' => ['section' => $section, 'state' => $ctx, 'rows' => $this->fleetRows($ctx)],
            'drivers' => ['section' => $section, 'state' => $ctx, 'rows' => $this->driverRows($ctx)],
            'vehicles' => ['section' => $section, 'state' => $ctx, 'rows' => $this->vehicleRows($ctx)],
            'bookings' => ['section' => $section, 'state' => $ctx, 'rows' => $this->bookingRows($ctx, $query)],
            'parcels' => ['section' => $section, 'state' => $ctx, 'rows' => $this->parcelRows($ctx)],
            'map' => ['section' => $section, 'state' => $ctx, 'rows' => $this->mapRows($ctx)],
            'complaints' => ['section' => $section, 'state' => $ctx, 'rows' => $this->complaintRows($ctx)],
            'revenue' => ['section' => $section, 'state' => $ctx, 'rows' => $this->revenueRows($ctx)],
            'commission' => ['section' => $section, 'state' => $ctx, 'rows' => $this->commissionRows($ctx)],
            'reports' => ['section' => $section, 'state' => $ctx, 'rows' => $this->reportRows($ctx)],
            'notifications' => ['section' => $section, 'state' => $ctx, 'rows' => $this->notificationRows($ctx)],
            default => throw new InvalidArgumentException('Unknown state section'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDistrictHead(User $operator, array $data, array $query = []): array
    {
        abort_unless($operator->can('users.create'), 403);
        $ctx = $this->context($operator, isset($query['stateId']) ? (int) $query['stateId'] : null);
        $districtId = (int) $data['district_id'];
        abort_unless(in_array($districtId, $ctx['districtIds'], true), 422, 'District is outside the assigned state.');

        $seat = app(DistrictFranchiseService::class)->apply($operator, [
            'kind' => 'DISTRICT_HEAD',
            'trade_name' => $data['name'].' District Head',
            'district_id' => $districtId,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        return ['id' => $seat['id'], 'stateId' => $ctx['stateId'], 'districtId' => $districtId, 'status' => 'APPLIED'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveFranchise(User $operator, array $data, array $query = []): array
    {
        $this->context($operator, isset($query['stateId']) ? (int) $query['stateId'] : null);

        return app(DistrictFranchiseService::class)->apply($operator, [
            'kind' => $data['kind'] ?? 'EXCLUSIVE_FRANCHISE',
            'trade_name' => $data['trade_name'],
            'district_id' => (int) $data['district_id'],
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'fee_amount_paise' => $data['fee_amount_paise'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User $operator, array $data, array $query = []): int
    {
        abort_unless($operator->can('notifications.send'), 403);
        $ctx = $this->context($operator, isset($query['stateId']) ? (int) $query['stateId'] : null);
        $roles = match ($data['audience'] ?? 'driver') {
            'customer' => ['CUSTOMER'],
            'ops' => ['DISTRICT_HEAD', 'FRANCHISE', 'FLEET_OWNER'],
            default => ['DRIVER'],
        };
        $ids = $this->users($ctx)->whereIn('role', $roles)->where('status', 'ACTIVE')->pluck('id');
        app(NotificationService::class)->broadcast($ids->all(), $data['title'], $data['body']);

        return $ids->count();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function districtRows(array $ctx): array
    {
        $rows = [];
        foreach ($this->districtOptions($ctx) as $district) {
            $id = (int) $district['id'];
            $bookings = $this->db()->table('bookings')->where('district_id', $id);
            $rows[] = [
                'id' => $id,
                'name' => $district['name'],
                'districtHeads' => $this->users($ctx)->where('role', 'DISTRICT_HEAD')->where('district_id', $id)->count(),
                'drivers' => $this->db()->table('drivers')->join('users', 'users.id', '=', 'drivers.user_id')->where('users.district_id', $id)->count(),
                'vehicles' => $this->db()->table('vehicles')->where('district_id', $id)->count(),
                'bookings' => (clone $bookings)->count(),
                'revenueRupees' => ((int) (clone $bookings)->where('status', 'COMPLETED')->sum('quote_paise')) / 100,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function districtHeadRows(array $ctx): array
    {
        return $this->users($ctx)->where('role', 'DISTRICT_HEAD')->orderBy('name')->get()->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'districtId' => $row->district_id,
            'status' => $row->status,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function franchiseRows(array $ctx): array
    {
        return $this->franchisesQuery($ctx)->orderByDesc('id')->limit(200)->get()->map(fn ($row) => [
            'id' => $row->id,
            'tradeName' => $row->trade_name,
            'kind' => $row->kind ?? 'EXCLUSIVE_FRANCHISE',
            'status' => $row->status,
            'districtId' => $row->district_id,
            'exclusiveSeat' => ($row->status ?? '') === 'ACTIVE',
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function fleetRows(array $ctx): array
    {
        return $this->fleetsQuery($ctx)
            ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
            ->select('fleet_owners.*', 'users.name as owner_name', 'users.district_id')
            ->orderByDesc('fleet_owners.id')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'tradeName' => $row->trade_name,
                'owner' => $row->owner_name,
                'districtId' => $row->district_id,
            ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function driverRows(array $ctx): array
    {
        return $this->driversQuery($ctx)
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.*', 'users.name', 'users.phone', 'users.district_id')
            ->orderByDesc('drivers.id')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'phone' => $row->phone,
                'kycStatus' => $row->kyc_status,
                'online' => (bool) $row->online,
                'fleetOwnerId' => $row->fleet_owner_id,
                'districtId' => $row->district_id,
            ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function vehicleRows(array $ctx): array
    {
        return $this->vehiclesQuery($ctx)->orderByDesc('id')->limit(200)->get()->map(fn ($row) => [
            'id' => $row->id,
            'registrationNo' => $row->registration_no,
            'status' => $row->status,
            'category' => $row->category,
            'districtId' => $row->district_id,
            'fleetOwnerId' => $row->fleet_owner_id,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function bookingRows(array $ctx, array $query): array
    {
        $q = $this->bookings($ctx)->orderByDesc('id')->limit(200);
        if (! empty($query['districtId']) && in_array((int) $query['districtId'], $ctx['districtIds'], true)) {
            $q->where('district_id', (int) $query['districtId']);
        }
        if (! empty($query['status'])) {
            $q->where('status', $query['status']);
        }

        return $q->get()->map(fn ($row) => [
            'id' => $row->id,
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'product' => $row->product,
            'districtId' => $row->district_id,
            'quoteRupees' => ((int) $row->quote_paise) / 100,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function parcelRows(array $ctx): array
    {
        return $this->parcelsQuery($ctx)->orderByDesc('id')->limit(200)->get()->map(fn ($row) => [
            'id' => $row->id,
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'pickupText' => $row->pickup_text,
            'dropText' => $row->drop_text,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $ctx): array
    {
        return $this->vehiclesQuery($ctx)->whereNotNull('last_lat')->whereNotNull('last_lng')->limit(300)->get()->map(fn ($row) => [
            'id' => $row->id,
            'registrationNo' => $row->registration_no,
            'lat' => (float) $row->last_lat,
            'lng' => (float) $row->last_lng,
            'status' => $row->status,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function complaintRows(array $ctx): array
    {
        return $this->complaintsQuery($ctx)->orderByDesc('id')->limit(200)->get()->map(fn ($row) => [
            'id' => $row->id,
            'publicRef' => $row->public_ref,
            'subject' => $row->subject,
            'status' => $row->status,
            'kind' => $row->kind,
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function revenueRows(array $ctx): array
    {
        return collect($this->districtRows($ctx))->map(fn ($row) => [
            'district' => $row['name'],
            'bookings' => $row['bookings'],
            'revenueRupees' => $row['revenueRupees'],
        ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function commissionRows(array $ctx): array
    {
        $gross = (int) $this->bookings($ctx)->where('status', 'COMPLETED')->sum('quote_paise');

        return [[
            'grossRupees' => $gross / 100,
            'estimatedCommissionRupees' => round(($gross * 0.15) / 100, 2),
            'note' => 'State commission view uses completed bookings in assigned districts only.',
        ]];
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function reportRows(array $ctx): array
    {
        return [[
            'state' => $ctx['stateName'] ?? $ctx['stateId'],
            'districts' => count($ctx['districtIds']),
            'drivers' => $this->driversQuery($ctx)->count(),
            'vehicles' => $this->vehiclesQuery($ctx)->count(),
            'completedRides' => $this->bookings($ctx)->where('status', 'COMPLETED')->count(),
            'parcels' => $this->parcelsQuery($ctx)->count(),
            'openComplaints' => $this->complaintsQuery($ctx)->whereNotIn('status', ['resolved', 'closed'])->count(),
        ]];
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array<string, mixed>>
     */
    private function notificationRows(array $ctx): array
    {
        $userIds = $this->users($ctx)->pluck('id');
        if ($userIds->isEmpty()) {
            return [];
        }

        return $this->db()->table('user_notifications')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'kind' => $row->kind,
            ])->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     * @return list<array{id: int, name: string}>
     */
    private function districtOptions(array $ctx): array
    {
        if ($ctx['districtIds'] === []) {
            return [];
        }

        return $this->db()->table('districts')->whereIn('id', $ctx['districtIds'])->orderBy('name')->get(['id', 'name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function users(array $ctx)
    {
        $q = $this->db()->table('users');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyUsers($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function bookings(array $ctx)
    {
        $q = $this->db()->table('bookings');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyBookings($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function driversQuery(array $ctx)
    {
        $q = $this->db()->table('drivers');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyDrivers($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function vehiclesQuery(array $ctx)
    {
        $q = $this->db()->table('vehicles');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyVehicles($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function fleetsQuery(array $ctx)
    {
        $q = $this->db()->table('fleet_owners');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyFleets($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function franchisesQuery(array $ctx)
    {
        $q = $this->db()->table('franchises');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyFranchises($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function parcelsQuery(array $ctx)
    {
        $q = $this->db()->table('parcel_shipments');
        $actor = new User(['role' => OperatorRole::STATE_HEAD, 'state_id' => $ctx['stateId']]);
        TerritoryScope::applyParcels($q, $actor);

        return $q;
    }

    /**
     * @param  array{stateId: int, districtIds: list<int>}  $ctx
     */
    private function complaintsQuery(array $ctx)
    {
        $q = $this->db()->table('support_tickets');
        $userIds = $this->users($ctx)->pluck('id');
        if ($userIds->isEmpty()) {
            return $q->whereRaw('0 = 1');
        }

        return $q->whereIn('user_id', $userIds);
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
