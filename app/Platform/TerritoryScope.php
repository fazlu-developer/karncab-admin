<?php

namespace App\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class TerritoryScope
{
    public static function isUnrestricted(?User $operator): bool
    {
        return $operator !== null && $operator->isPrivilegedOperator();
    }

    public static function canAccessDistrict(?int $actorDistrictId, string $role, ?int $districtId): bool
    {
        if (in_array($role, [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN], true)) {
            return true;
        }
        if (! in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            return false;
        }

        return $actorDistrictId !== null && $districtId !== null && $actorDistrictId === $districtId;
    }

    public static function canAccessState(?int $actorStateId, string $role, ?int $stateId): bool
    {
        if (in_array($role, [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN], true)) {
            return true;
        }
        if ($role !== OperatorRole::STATE_HEAD) {
            return false;
        }

        return $actorStateId !== null && $stateId !== null && $actorStateId === $stateId;
    }

    public static function canAccessFleet(?int $actorFleetId, string $role, ?int $fleetId): bool
    {
        if (in_array($role, [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN], true)) {
            return true;
        }
        if ($role !== OperatorRole::FLEET_OWNER) {
            return false;
        }

        return $actorFleetId !== null && $fleetId !== null && $actorFleetId === $fleetId;
    }

    /**
     * Drop or rewrite request filters so a caller cannot widen scope via query params.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function constrainQuery(array $query, ?User $operator): array
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return $query;
        }

        $role = (string) $operator->role;

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            $query['districtId'] = $operator->district_id;
            unset($query['stateId'], $query['fleetId']);
        }

        if ($role === OperatorRole::STATE_HEAD) {
            $query['stateId'] = $operator->state_id;
            $requestedDistrict = isset($query['districtId']) ? (int) $query['districtId'] : null;
            if ($requestedDistrict && ! in_array($requestedDistrict, self::districtIdsForState((int) $operator->state_id), true)) {
                unset($query['districtId']);
            }
            unset($query['fleetId']);
        }

        if ($role === OperatorRole::FLEET_OWNER) {
            $query['fleetId'] = $operator->fleet_owner_id;
            unset($query['stateId'], $query['districtId']);
        }

        return $query;
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyUsers($query, ?User $operator, string $table = 'users'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.district_id', $operator->district_id);

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            self::restrictToStateColumn($query, $operator, $table.'.state_id', $table.'.district_id');

            return;
        }

        if ($role === OperatorRole::FLEET_OWNER) {
            $fleetId = $operator->fleet_owner_id;
            if (! $fleetId) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where(function ($inner) use ($table, $fleetId) {
                $inner->whereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')
                        ->from('drivers')
                        ->whereColumn('drivers.user_id', $table.'.id')
                        ->where('drivers.fleet_owner_id', $fleetId);
                })->orWhereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')
                        ->from('fleet_owners')
                        ->whereColumn('fleet_owners.user_id', $table.'.id')
                        ->where('fleet_owners.id', $fleetId);
                });
            });

            return;
        }

        if (in_array($role, [OperatorRole::DRIVER, OperatorRole::CUSTOMER, OperatorRole::CORPORATE], true) && $operator->nest_user_id) {
            $query->where($table.'.id', $operator->nest_user_id);

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyDrivers($query, ?User $operator, string $table = 'drivers'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if ($role === OperatorRole::FLEET_OWNER) {
            if (! $operator->fleet_owner_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.fleet_owner_id', $operator->fleet_owner_id);

            return;
        }

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->whereExists(function ($sub) use ($table, $operator) {
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', $table.'.user_id')
                    ->where('users.district_id', $operator->district_id);
            });

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            $districtIds = self::districtIdsForState((int) $operator->state_id);
            if (! $operator->state_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->whereExists(function ($sub) use ($table, $operator, $districtIds) {
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', $table.'.user_id')
                    ->where(function ($user) use ($operator, $districtIds) {
                        $user->where('users.state_id', $operator->state_id);
                        if ($districtIds !== []) {
                            $user->orWhereIn('users.district_id', $districtIds);
                        }
                    });
            });

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyBookings($query, ?User $operator, string $table = 'bookings'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.district_id', $operator->district_id);

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            self::restrictBookingsToState($query, $operator, $table);

            return;
        }

        if ($role === OperatorRole::FLEET_OWNER) {
            if (! $operator->fleet_owner_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $fleetId = $operator->fleet_owner_id;
            $query->where(function ($inner) use ($table, $fleetId) {
                $inner->whereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')
                        ->from('vehicles')
                        ->whereColumn('vehicles.id', $table.'.vehicle_id')
                        ->where('vehicles.fleet_owner_id', $fleetId);
                })->orWhereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')
                        ->from('drivers')
                        ->whereColumn('drivers.id', $table.'.driver_id')
                        ->where('drivers.fleet_owner_id', $fleetId);
                });
            });

            return;
        }

        if (in_array($role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE], true)) {
            $customerId = (int) ($operator->nest_user_id ?: $operator->id);
            if ($customerId < 1) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.customer_id', $customerId);

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applySafetyIncidents($query, ?User $operator, string $table = 'safety_incidents'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.district_id', $operator->district_id);

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            $districtIds = self::districtIdsForState((int) $operator->state_id);
            if ($districtIds === []) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->whereIn($table.'.district_id', $districtIds);

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyVehicles($query, ?User $operator, string $table = 'vehicles'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if ($role === OperatorRole::FLEET_OWNER) {
            if (! $operator->fleet_owner_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.fleet_owner_id', $operator->fleet_owner_id);

            return;
        }

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.district_id', $operator->district_id);

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            $districtIds = self::districtIdsForState((int) $operator->state_id);
            if ($districtIds === []) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->whereIn($table.'.district_id', $districtIds);

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyFleets($query, ?User $operator, string $table = 'fleet_owners'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        if ((string) $operator->role === OperatorRole::FLEET_OWNER) {
            if (! $operator->fleet_owner_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.id', $operator->fleet_owner_id);

            return;
        }

        if (in_array((string) $operator->role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE, OperatorRole::STATE_HEAD], true)) {
            $query->whereExists(function ($sub) use ($table, $operator) {
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', $table.'.user_id');
                self::applyUsers($sub, $operator, 'users');
            });

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyFranchises($query, ?User $operator, string $table = 'franchises'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            if (! $operator->district_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where($table.'.district_id', $operator->district_id);

            return;
        }

        if ($role === OperatorRole::STATE_HEAD) {
            if (! $operator->state_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->where(function ($inner) use ($table, $operator) {
                $inner->where($table.'.state_id', $operator->state_id);
                $ids = self::districtIdsForState((int) $operator->state_id);
                if ($ids !== []) {
                    $inner->orWhereIn($table.'.district_id', $ids);
                }
            });

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function applyParcels($query, ?User $operator, string $table = 'parcel_shipments'): void
    {
        if ($operator === null || self::isUnrestricted($operator)) {
            return;
        }

        $role = (string) $operator->role;

        if ($role === OperatorRole::FLEET_OWNER) {
            if (! $operator->fleet_owner_id) {
                $query->whereRaw('0 = 1');

                return;
            }
            $fleetId = $operator->fleet_owner_id;
            $query->where(function ($inner) use ($table, $fleetId) {
                $inner->whereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')->from('drivers')
                        ->whereColumn('drivers.id', $table.'.driver_id')
                        ->where('drivers.fleet_owner_id', $fleetId);
                })->orWhereExists(function ($sub) use ($table, $fleetId) {
                    $sub->selectRaw('1')->from('vehicles')
                        ->whereColumn('vehicles.id', $table.'.vehicle_id')
                        ->where('vehicles.fleet_owner_id', $fleetId);
                });
            });

            return;
        }

        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE, OperatorRole::STATE_HEAD], true)) {
            $query->whereExists(function ($sub) use ($table, $operator) {
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', $table.'.customer_id');
                self::applyUsers($sub, $operator, 'users');
            });

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @return list<int>
     */
    public static function districtIdsForState(?int $stateId): array
    {
        if (! $stateId) {
            return [];
        }

        return DB::connection('platform')
            ->table('districts')
            ->where('state_id', $stateId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function restrictToStateColumn($query, User $operator, string $stateCol, string $districtCol): void
    {
        if (! $operator->state_id) {
            $query->whereRaw('0 = 1');

            return;
        }
        $districtIds = self::districtIdsForState((int) $operator->state_id);
        $query->where(function ($inner) use ($operator, $stateCol, $districtCol, $districtIds) {
            $inner->where($stateCol, $operator->state_id);
            if ($districtIds !== []) {
                $inner->orWhereIn($districtCol, $districtIds);
            }
        });
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function restrictBookingsToState($query, User $operator, string $table): void
    {
        $districtIds = self::districtIdsForState((int) $operator->state_id);
        if ($districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereIn($table.'.district_id', $districtIds);
    }
}
