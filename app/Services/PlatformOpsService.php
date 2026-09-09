<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Platform\TerritoryScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformOpsService
{
    private const LIVE = [
        'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'ONGOING', 'STARTED',
    ];

    public function dashboard(?User $operator = null): array
    {
        $users = $this->scopedUsers($operator);
        $drivers = $this->scopedDrivers($operator);
        $bookings = $this->bookingsQuery($operator);
        $parcels = $this->scopedParcels($operator);
        $fleets = $this->scopedFleets($operator);
        $completed = (clone $bookings)->where('status', 'COMPLETED');
        $startToday = now()->startOfDay();
        $startMonth = now()->startOfMonth();
        $expiry = now()->addDays(30);

        $todayPaise = (int) (clone $completed)->where('created_at', '>=', $startToday)->sum('quote_paise');
        $monthPaise = (int) (clone $completed)->where('created_at', '>=', $startMonth)->sum('quote_paise');
        $earnPaise = (int) $this->q('wallet_ledger')->where('account', 'DRIVER')->where('direction', 'CREDIT')->sum('amount_paise');
        $commissionPaise = (int) $this->q('wallet_ledger')->sum('commission_paise');

        return [
            'kpis' => [
                'totalUsers' => (clone $users)->count(),
                'activeUsers' => (clone $users)->where('status', 'ACTIVE')->count(),
                'totalDrivers' => (clone $drivers)->count(),
                'onlineDrivers' => (clone $drivers)->where('online', 1)->count(),
                'activeRides' => (clone $bookings)->whereIn('status', self::LIVE)->count(),
                'completedRides' => (clone $completed)->count(),
                'cancelledRides' => (clone $bookings)->where('status', 'CANCELLED')->count(),
                'todayRevenuePaise' => $todayPaise,
                'monthlyRevenuePaise' => $monthPaise,
                'todayRevenueRupees' => $todayPaise / 100,
                'monthlyRevenueRupees' => $monthPaise / 100,
                'driverEarningsPaise' => $earnPaise,
                'driverEarningsRupees' => $earnPaise / 100,
                'commissionEarnedPaise' => $commissionPaise,
                'commissionEarnedRupees' => $commissionPaise / 100,
                'parcelOrders' => (clone $parcels)->count(),
                'corporateBookings' => (clone $bookings)->whereNotNull('corporate_account_id')->count(),
                'bulkBookings' => $this->q('bulk_bookings')->count(),
                'activeFleet' => (clone $fleets)->count(),
                'pendingKyc' => (clone $drivers)->whereIn('kyc_status', ['pending', 'under_review'])->count(),
                'pendingComplaints' => $this->q('support_tickets')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'expiringDocuments' => $this->q('driver_documents')->whereNotNull('expires_at')->where('expires_at', '<=', $expiry)->count(),
                'activeAdvertisements' => $this->q('ad_campaigns')->where('status', 'published')->where('starts_on', '<=', now())->where('ends_on', '>=', now())->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listing(string $module, array $query, ?User $operator = null): array
    {
        $role = $query['role'] ?? null;
        if ($module === 'state-heads') {
            $role = 'STATE_HEAD';
            $module = 'users';
        }
        if ($module === 'district-heads') {
            $role = 'DISTRICT_HEAD';
            $module = 'users';
        }

        $query = TerritoryScope::constrainQuery($query, $operator);

        return match ($module) {
            'users' => ['users' => $this->listUsers($query, $operator, is_string($role) ? $role : null)],
            'drivers' => ['drivers' => $this->listDrivers($operator)],
            'fleet' => ['fleets' => $this->map($this->scopedFleets($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'tradeName' => $row->trade_name ?? null,
                'userId' => (string) $row->user_id,
            ])],
            'vehicles' => ['vehicles' => $this->map($this->scopedVehicles($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'registrationNo' => $row->registration_no,
                'status' => $row->status,
                'category' => $row->category,
                'districtId' => $row->district_id,
            ])],
            'bookings' => ['bookings' => $this->listBookings($query, $operator)],
            'parcels' => ['parcels' => $this->map($this->scopedParcels($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
                'pickupText' => $row->pickup_text ?? null,
                'dropText' => $row->drop_text ?? null,
            ])],
            'travel', 'services' => ['packages' => $this->map($this->q('travel_packages')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'title' => $row->title ?? $row->name ?? null,
                'status' => $row->status ?? null,
            ])],
            'travel-bookings' => ['bookings' => $this->map($this->q('travel_bookings')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'publicRef' => $row->public_ref ?? null,
                'status' => $row->status ?? null,
            ])],
            'bulk' => ['bulk' => $this->map($this->q('bulk_bookings')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
                'eventKey' => $row->event_key ?? null,
            ])],
            'corporate' => ['accounts' => $this->map($this->q('corporate_accounts')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'companyName' => $row->company_name,
                'gstin' => $row->gstin,
                'status' => $row->status,
            ])],
            'franchise' => ['franchises' => $this->map($this->scopedFranchises($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'tradeName' => $row->trade_name ?? null,
                'status' => $row->status,
                'districtId' => $row->district_id,
            ])],
            'kyc' => ['drivers' => $this->map($this->scopedDrivers($operator)->whereIn('kyc_status', ['pending', 'under_review'])->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'kycStatus' => $row->kyc_status,
                'userId' => (string) $row->user_id,
            ])],
            'payments' => ['payments' => $this->map($this->scopedPayments($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'publicRef' => $row->public_ref,
                'method' => $row->method,
                'amountPaise' => (int) $row->amount_paise,
                'status' => $row->status,
            ])],
            'wallets' => ['wallets' => $this->map($this->scopedWallets($operator)->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'ownerType' => $row->owner_type,
                'balancePaise' => (int) $row->balance_paise,
                'balanceRupees' => ((int) $row->balance_paise) / 100,
            ])],
            'commission' => ['commissionRules' => $this->q('commission_rules')->orderBy('id')->limit(200)->get()->toArray()],
            'coupons' => ['coupons' => $this->map($this->q('coupons')->orderByDesc('id')->limit(200)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'code' => $row->code,
                'title' => $row->title,
                'active' => (bool) $row->active,
            ])],
            'advertising' => ['campaigns' => $this->map($this->q('ad_campaigns')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'title' => $row->title,
                'status' => $row->status,
                'businessName' => $row->business_name ?? null,
            ])],
            'map' => ['vehicles' => $this->map($this->scopedVehicles($operator)->whereNotNull('last_lat')->limit(200)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'registrationNo' => $row->registration_no,
                'lat' => $row->last_lat,
                'lng' => $row->last_lng,
                'status' => $row->status,
            ])],
            'complaints' => ['tickets' => $this->map($this->q('support_tickets')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'ticketId' => $row->public_ref,
                'publicRef' => $row->public_ref,
                'kind' => $row->kind,
                'status' => $row->status,
                'subject' => $row->subject,
                'category' => $row->category ?? null,
                'priority' => $row->priority ?? null,
            ])],
            'ratings' => ['ratings' => $this->map($this->q('booking_ratings')->orderByDesc('id')->limit(100)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'stars' => $row->stars,
                'fromRole' => $row->from_role,
                'comment' => $row->comment,
            ])],
            'reports' => $this->dashboard($operator),
            'notifications' => ['notifications' => $this->map($this->q('user_notifications')->orderByDesc('id')->limit(80)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'kind' => $row->kind,
            ])],
            'fare' => ['fareRules' => $this->q('fare_rules')->orderBy('id')->limit(500)->get()->toArray()],
            'locations' => ['states' => $this->listLocations()],
            'roles' => [
                'roles' => collect(OperatorRole::all())->map(fn ($role) => [
                    'role' => $role,
                    'permissions' => PlatformPermission::forRole($role),
                ])->all(),
            ],
            'settings' => ['settings' => $this->q('system_settings')->orderBy('key')->get()
                ->filter(fn ($row) => ! preg_match('/secret|password|token|private/i', (string) $row->key))
                ->map(fn ($row) => ['key' => $row->key, 'value' => $row->value])
                ->values()
                ->all()],
            'audit' => ['events' => $this->map($this->q('platform_audit_events')->orderByDesc('id')->limit(200)->get(), fn ($row) => [
                'id' => (string) $row->id,
                'domain' => $row->domain,
                'action' => $row->action,
                'entityType' => $row->entity_type,
                'entityId' => $row->entity_id,
                'createdAt' => $row->created_at,
            ])],
            default => throw new \InvalidArgumentException('Unknown module'),
        };
    }

    /**
     * Flatten listing rows for CSV/Excel-style export. Same listing() scope as the screen.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $module, array $query, User $operator): array
    {
        $payload = $this->listing($module, $query, $operator);

        return $this->rowsFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function rowsFromPayload(array $payload): array
    {
        foreach ($payload as $value) {
            if (is_array($value) && $value !== [] && array_is_list($value) && is_array($value[0] ?? null)) {
                return array_map(function (array $row) {
                    return array_filter($row, fn ($item) => ! is_array($item));
                }, $value);
            }
        }

        return [$payload];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listUsers(array $query, ?User $operator = null, ?string $role = null): array
    {
        $q = $this->scopedUsers($operator);
        $role = $role ?? ($query['role'] ?? null);
        if (is_string($role) && $role !== '') {
            $q->where('role', $role);
        }
        if (! empty($query['status'])) {
            $q->where('status', $query['status']);
        }
        if (! empty($query['q'])) {
            $term = $query['q'];
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        return $this->map($q->orderByDesc('id')->limit(200)->get(), fn ($row) => $this->presentUser($row));
    }

    public function user(int $id, ?User $operator = null): array
    {
        $row = $this->scopedUsers($operator)->where('id', $id)->first();
        abort_if($row === null, 404, 'User not found');
        $payload = $this->presentUser($row);
        $payload['bookings'] = $this->map($this->q('bookings')->where('customer_id', $id)->orderByDesc('id')->limit(30)->get(), fn ($b) => [
            'id' => (string) $b->id,
            'publicRef' => $b->public_ref,
            'product' => $b->product,
            'status' => $b->status,
            'quotePaise' => $b->quote_paise,
        ]);
        $payload['payments'] = $this->map($this->q('payments')->where('customer_id', $id)->orderByDesc('id')->limit(30)->get(), fn ($p) => [
            'id' => (string) $p->id,
            'publicRef' => $p->public_ref,
            'method' => $p->method,
            'amountPaise' => (int) $p->amount_paise,
            'status' => $p->status,
        ]);
        $payload['wallets'] = $this->map($this->q('wallets')->where('owner_user_id', $id)->get(), fn ($w) => [
            'id' => (string) $w->id,
            'ownerType' => $w->owner_type,
            'balancePaise' => (int) $w->balance_paise,
            'balanceRupees' => ((int) $w->balance_paise) / 100,
        ]);
        $payload['complaints'] = $this->map($this->q('support_tickets')->where('user_id', $id)->orderByDesc('id')->limit(20)->get(), fn ($t) => [
            'id' => (string) $t->id,
            'publicRef' => $t->public_ref,
            'kind' => $t->kind,
            'status' => $t->status,
            'subject' => $t->subject,
        ]);
        $payload['ratings'] = $this->map(
            $this->q('booking_ratings')->whereIn('booking_id', $this->q('bookings')->where('customer_id', $id)->select('id'))->limit(20)->get(),
            fn ($r) => [
                'id' => (string) $r->id,
                'stars' => $r->stars,
                'fromRole' => $r->from_role,
                'comment' => $r->comment,
            ],
        );
        $payload['devices'] = $this->map($this->q('push_devices')->where('user_id', $id)->get(), fn ($d) => [
            'id' => (string) $d->id,
            'platform' => $d->platform,
            'tokenHint' => Str::of((string) $d->token)->length() > 12
                ? substr((string) $d->token, 0, 6).'…'.substr((string) $d->token, -4)
                : '••••',
        ]);
        $payload['loginActivity'] = $this->map(
            $this->q('platform_audit_events')->where('entity_type', 'user')->where('entity_id', (string) $id)->where('action', 'login')->orderByDesc('id')->limit(20)->get(),
            fn ($e) => ['id' => (string) $e->id, 'action' => $e->action, 'createdAt' => $e->created_at],
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createUser(array $data, ?User $actor = null): array
    {
        $id = $this->db()->table('users')->insertGetId([
            'role' => $data['role'],
            'status' => 'ACTIVE',
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'state_id' => $data['state_id'] ?? $this->forcedStateId($actor),
            'district_id' => $data['district_id'] ?? $this->forcedDistrictId($actor),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($actor, 'users', 'created', 'user', (string) $id);

        return $this->user((int) $id, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patchUser(int $id, array $data, ?User $actor = null): array
    {
        abort_if($this->scopedUsers($actor)->where('id', $id)->doesntExist(), 404, 'User not found');
        $patch = array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'updated_at' => now(),
        ], fn ($value) => $value !== null);
        if (! empty($data['status'])) {
            $patch['status'] = $data['status'];
        }
        $this->db()->table('users')->where('id', $id)->update($patch);
        $this->audit($actor, 'users', isset($data['status']) ? 'status' : 'updated', 'user', (string) $id);

        return $this->user($id, $actor);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listBookings(array $query, ?User $operator = null): array
    {
        $query = TerritoryScope::constrainQuery($query, $operator);
        $q = $this->bookingsQuery($operator)
            ->leftJoin('users as customers', 'customers.id', '=', 'bookings.customer_id')
            ->leftJoin('drivers', 'drivers.id', '=', 'bookings.driver_id')
            ->leftJoin('users as driver_users', 'driver_users.id', '=', 'drivers.user_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->leftJoin('districts', 'districts.id', '=', 'bookings.district_id')
            ->leftJoin('states', 'states.id', '=', 'districts.state_id')
            ->select(
                'bookings.*',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'driver_users.name as driver_name',
                'vehicles.registration_no as vehicle_reg',
                'districts.name as district_name',
                'states.name as state_name',
            );

        if (! empty($query['publicRef']) || ! empty($query['q'])) {
            $term = $query['publicRef'] ?? $query['q'];
            $q->where(function (Builder $inner) use ($term) {
                $inner->where('bookings.public_ref', 'like', "%{$term}%")
                    ->orWhere('customers.name', 'like', "%{$term}%");
            });
        }
        foreach (['customer' => 'customers.name', 'driver' => 'driver_users.name', 'vehicle' => 'vehicles.registration_no'] as $key => $col) {
            if (! empty($query[$key])) {
                $q->where($col, 'like', '%'.$query[$key].'%');
            }
        }
        if (! empty($query['product'])) {
            $q->where('bookings.product', $query['product']);
        }
        if (! empty($query['status'])) {
            $q->where('bookings.status', $query['status']);
        }
        if (! empty($query['districtId'])) {
            $q->where('bookings.district_id', $query['districtId']);
        }
        if (! empty($query['stateId'])) {
            $q->where('districts.state_id', $query['stateId']);
        }
        if (! empty($query['fleetId'])) {
            $q->where('vehicles.fleet_owner_id', $query['fleetId']);
        }
        if (! empty($query['from'])) {
            $q->where('bookings.created_at', '>=', $query['from']);
        }
        if (! empty($query['to'])) {
            $q->where('bookings.created_at', '<=', $query['to']);
        }
        if (! empty($query['paymentStatus'])) {
            $q->whereExists(function ($sub) use ($query) {
                $sub->from('payments')
                    ->whereColumn('payments.booking_id', 'bookings.id')
                    ->where('payments.status', $query['paymentStatus']);
            });
        }

        return $this->map($q->orderByDesc('bookings.id')->limit(100)->get(), function ($row) {
            $track = $this->tripTrack((string) $row->status);

            return [
                'id' => (string) $row->id,
                'publicRef' => $row->public_ref,
                'product' => $row->product,
                'status' => $row->status,
                'customer' => $row->customer_name,
                'driver' => $row->driver_name,
                'vehicle' => $row->vehicle_reg,
                'district' => $row->district_name,
                'state' => $row->state_name,
                'quotePaise' => $row->quote_paise,
                'track' => $track,
                'createdAt' => $row->created_at,
            ];
        });
    }

    public function booking(int $id, ?User $operator = null): array
    {
        $row = $this->bookingsQuery($operator)->where('bookings.id', $id)->first();
        abort_if($row === null, 404, 'Booking not found');
        $log = $this->q('platform_audit_events')->where('entity_type', 'booking')->where('entity_id', (string) $id)->orderBy('id')->get();

        return [
            'id' => (string) $row->id,
            'publicRef' => $row->public_ref,
            'product' => $row->product,
            'status' => $row->status,
            'pickupText' => $row->pickup_text,
            'dropText' => $row->drop_text,
            'track' => $this->tripTrack((string) $row->status),
            'statusLog' => $this->map($log, fn ($e) => [
                'action' => $e->action,
                'createdAt' => $e->created_at,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function patchDriver(int $id, array $data, ?User $actor = null): void
    {
        $this->assertDriverVisible($id, $actor);
        $driver = $this->q('drivers')->where('id', $id)->first();
        abort_if($driver === null, 404, 'Driver not found');
        $action = $data['action'] ?? '';
        if (in_array($action, ['suspend', 'reject'], true)) {
            $this->db()->table('users')->where('id', $driver->user_id)->update(['status' => 'SUSPENDED', 'updated_at' => now()]);
            if ($action === 'reject') {
                $this->q('drivers')->where('id', $id)->update([
                    'kyc_status' => 'rejected',
                    'kyc_rejected_reason' => $data['reason'] ?? 'Rejected by ops',
                    'online' => 0,
                    'updated_at' => now(),
                ]);
                app(NotificationService::class)->dispatch((int) $driver->user_id, 'kyc_rejection', [
                    'reason' => $data['reason'] ?? 'Rejected by ops',
                ], ['entity' => ['type' => 'kyc', 'id' => (string) $driver->user_id]]);
            }
        }
        if (in_array($action, ['activate', 'approve'], true)) {
            $this->db()->table('users')->where('id', $driver->user_id)->update(['status' => 'ACTIVE', 'updated_at' => now()]);
            if ($action === 'approve') {
                $this->q('drivers')->where('id', $id)->update([
                    'kyc_status' => 'verified',
                    'kyc_rejected_reason' => null,
                    'updated_at' => now(),
                ]);
                app(NotificationService::class)->dispatch((int) $driver->user_id, 'kyc_approval', [], [
                    'entity' => ['type' => 'kyc', 'id' => (string) $driver->user_id],
                ]);
            }
        }
        if (! empty($data['fleetOwnerId'])) {
            $this->q('drivers')->where('id', $id)->update(['fleet_owner_id' => $data['fleetOwnerId'], 'updated_at' => now()]);
        }
        if (! empty($data['vehicleId'])) {
            $this->q('vehicles')->where('id', $data['vehicleId'])->update(['driver_id' => $id, 'updated_at' => now()]);
        }
        $this->audit($actor, 'drivers', $action ?: 'updated', 'driver', (string) $id);
    }

    /**
     * @param  array{title: string, body: string, audience: string}  $data
     */
    public function announce(array $data, ?User $actor = null): int
    {
        $roles = match ($data['audience']) {
            'driver' => ['DRIVER'],
            'ops' => ['ADMIN', 'SUPER_ADMIN', 'DISTRICT_HEAD', 'STATE_HEAD', 'FRANCHISE'],
            default => ['CUSTOMER', 'CORPORATE'],
        };
        $ids = $this->q('users')->whereIn('role', $roles)->where('status', 'ACTIVE')->pluck('id');
        app(NotificationService::class)->broadcast($ids->all(), $data['title'], $data['body']);
        $this->audit($actor, 'notifications', 'announce', 'broadcast', $data['audience']);

        return $ids->count();
    }

    public function ping(): bool
    {
        $this->db()->select('select 1 as ok');

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listDrivers(?User $operator = null): array
    {
        $rows = $this->scopedDrivers($operator)
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->orderByDesc('drivers.id')
            ->limit(100)
            ->select('drivers.*', 'users.name', 'users.email', 'users.phone', 'users.status as user_status')
            ->get();

        return $this->map($rows, fn ($row) => [
            'id' => (string) $row->id,
            'userId' => (string) $row->user_id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'online' => (bool) $row->online,
            'kycStatus' => $row->kyc_status,
            'fleetOwnerId' => $row->fleet_owner_id ? (string) $row->fleet_owner_id : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLocations(): array
    {
        $states = $this->q('states')->orderBy('name')->get();

        return $this->map($states, fn ($state) => [
            'id' => $state->id,
            'name' => $state->name,
            'districts' => $this->q('districts')->where('state_id', $state->id)->orderBy('name')->get(['id', 'name'])->toArray(),
        ]);
    }

    private function presentUser(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'role' => $row->role,
            'status' => $row->status,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'stateId' => $row->state_id ?? null,
            'districtId' => $row->district_id ?? null,
            'lastAddress' => $row->last_address ?? null,
            'emergencyName' => $row->emergency_name ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tripTrack(string $status): array
    {
        $map = [
            'DRAFT' => 0, 'PENDING' => 0, 'QUOTED' => 0, 'CONFIRMED' => 0, 'REQUESTED' => 0, 'DRIVER_SEARCHING' => 0,
            'ASSIGNED' => 1, 'DRIVER_ASSIGNED' => 1,
            'DRIVER_ARRIVING' => 2,
            'DRIVER_ARRIVED' => 3,
            'STARTED' => 4, 'ONGOING' => 4,
            'COMPLETED' => 5,
            'CANCELLED' => -1,
        ];
        $stage = $map[$status] ?? 0;
        $steps = [
            ['key' => 'requested', 'label' => 'Requested', 'from' => 0],
            ['key' => 'assigned', 'label' => 'Assigned', 'from' => 1],
            ['key' => 'accepted', 'label' => 'Accepted', 'from' => 1],
            ['key' => 'arrived', 'label' => 'Arrived', 'from' => 3],
            ['key' => 'otp', 'label' => 'OTP Verified', 'from' => 4],
            ['key' => 'started', 'label' => 'Started', 'from' => 4],
            ['key' => 'progress', 'label' => 'In Progress', 'from' => 4],
            ['key' => 'completed', 'label' => 'Completed', 'from' => 5],
        ];
        $cancelled = $status === 'CANCELLED';

        return [
            'status' => $status,
            'label' => $cancelled ? 'Cancelled' : $status,
            'cancelled' => $cancelled,
            'path' => $cancelled ? ['Requested', 'Cancelled'] : array_column($steps, 'label'),
            'steps' => array_map(fn ($step) => [
                'key' => $step['key'],
                'label' => $step['label'],
                'done' => ! $cancelled && $stage > $step['from'],
                'active' => ! $cancelled && $stage === $step['from'],
            ], $steps),
        ];
    }

    private function scopedUsers(?User $operator): Builder
    {
        $q = $this->q('users');
        TerritoryScope::applyUsers($q, $operator);

        return $q;
    }

    private function scopedDrivers(?User $operator): Builder
    {
        $q = $this->q('drivers');
        TerritoryScope::applyDrivers($q, $operator);

        return $q;
    }

    private function scopedVehicles(?User $operator): Builder
    {
        $q = $this->q('vehicles');
        TerritoryScope::applyVehicles($q, $operator);

        return $q;
    }

    private function scopedFleets(?User $operator): Builder
    {
        $q = $this->q('fleet_owners');
        TerritoryScope::applyFleets($q, $operator);

        return $q;
    }

    private function scopedFranchises(?User $operator): Builder
    {
        $q = $this->q('franchises');
        TerritoryScope::applyFranchises($q, $operator);

        return $q;
    }

    private function scopedParcels(?User $operator): Builder
    {
        $q = $this->q('parcel_shipments');
        TerritoryScope::applyParcels($q, $operator);

        return $q;
    }

    private function scopedPayments(?User $operator): Builder
    {
        $q = $this->q('payments');
        if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
            $q->where(function ($inner) use ($operator) {
                $inner->whereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('bookings')->whereColumn('bookings.id', 'payments.booking_id');
                    TerritoryScope::applyBookings($sub, $operator, 'bookings');
                })->orWhereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('users')->whereColumn('users.id', 'payments.customer_id');
                    TerritoryScope::applyUsers($sub, $operator, 'users');
                });
            });
        }

        return $q;
    }

    private function scopedWallets(?User $operator): Builder
    {
        $q = $this->q('wallets');
        if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
            $q->whereExists(function ($sub) use ($operator) {
                $sub->selectRaw('1')->from('users')->whereColumn('users.id', 'wallets.owner_user_id');
                TerritoryScope::applyUsers($sub, $operator, 'users');
            });
        }

        return $q;
    }

    private function bookingsQuery(?User $operator): Builder
    {
        $q = $this->q('bookings');
        TerritoryScope::applyBookings($q, $operator);

        return $q;
    }

    private function assertDriverVisible(int $id, ?User $actor): void
    {
        $visible = $this->scopedDrivers($actor)->where('drivers.id', $id)->exists();
        abort_unless($visible, 404, 'Driver not found');
    }

    private function forcedStateId(?User $actor): ?int
    {
        if (! $actor || TerritoryScope::isUnrestricted($actor)) {
            return null;
        }

        return $actor->state_id;
    }

    private function forcedDistrictId(?User $actor): ?int
    {
        if (! $actor || TerritoryScope::isUnrestricted($actor)) {
            return null;
        }

        return $actor->district_id;
    }

    private function q(string $table): Builder
    {
        return $this->db()->table($table);
    }

    private function db(): \Illuminate\Database\Connection
    {
        return DB::connection('platform');
    }

    private function audit(?User $actor, string $domain, string $action, string $entityType, string $entityId): void
    {
        $this->db()->table('platform_audit_events')->insert([
            'actor_user_id' => $actor?->nest_user_id,
            'domain' => $domain,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => now(),
        ]);
    }

    /**
     * @template T
     * @param  iterable<T>  $rows
     * @param  callable(T): array<string, mixed>  $fn
     * @return list<array<string, mixed>>
     */
    private function map(iterable $rows, callable $fn): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $fn($row);
        }

        return $out;
    }
}
