<?php

namespace App\Support;

use App\Models\User;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NamePicker
{
    /**
     * @return list<array{id: int, label: string}>
     */
    public static function users(?User $actor = null, ?string $role = null, int $limit = 250): array
    {
        if (! Schema::connection('platform')->hasTable('users')) {
            return [];
        }
        $q = DB::connection('platform')->table('users')
            ->select('users.id', 'users.name', 'users.phone', 'users.email', 'users.role');
        if ($actor) {
            TerritoryScope::applyUsers($q, $actor, 'users');
        }
        if ($role !== null && $role !== '') {
            $q->where('users.role', $role);
        }

        return $q->orderBy('users.name')->orderByDesc('users.id')->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => self::personLabel($row->name, $row->phone, $row->role, (int) $row->id),
            ])->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function drivers(?User $actor = null, int $limit = 200): array
    {
        if (! Schema::connection('platform')->hasTable('drivers')) {
            return [];
        }
        $q = DB::connection('platform')->table('drivers')
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.id', 'users.name', 'users.phone');
        if (Schema::connection('platform')->hasTable('vehicles')) {
            $q->leftJoin('vehicles', 'vehicles.driver_id', '=', 'drivers.id')
                ->addSelect('vehicles.registration_no', 'vehicles.category', 'vehicles.brand', 'vehicles.model');
        }
        if ($actor) {
            TerritoryScope::applyUsers($q, $actor, 'users');
        }

        $seen = [];
        foreach ($q->orderBy('users.name')->orderByDesc('drivers.id')->limit($limit * 3)->get() as $row) {
            $id = (int) $row->id;
            if (isset($seen[$id])) {
                continue;
            }
            $vehicle = trim(implode(' ', array_filter([
                $row->brand ?? null,
                $row->model ?? null,
                $row->category ?? null,
                $row->registration_no ?? null,
            ])));
            $seen[$id] = [
                'id' => $id,
                'label' => self::personLabel($row->name, $row->phone, $vehicle !== '' ? $vehicle : 'DRIVER', $id),
                'phone' => $row->phone ?? '',
                'vehicle' => $vehicle,
            ];
            if (count($seen) >= $limit) {
                break;
            }
        }

        return array_values($seen);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function bookings(?User $actor = null, int $limit = 150): array
    {
        if (! Schema::connection('platform')->hasTable('bookings')) {
            return [];
        }
        $q = DB::connection('platform')->table('bookings');
        if (Schema::connection('platform')->hasTable('users')) {
            $q->leftJoin('users', 'users.id', '=', 'bookings.customer_id')
                ->select(
                    'bookings.id',
                    'bookings.status',
                    'users.name as customer_name',
                    'users.phone as customer_phone',
                );
            if (Schema::connection('platform')->hasColumn('bookings', 'public_ref')) {
                $q->addSelect('bookings.public_ref');
            }
            if (Schema::connection('platform')->hasColumn('bookings', 'product')) {
                $q->addSelect('bookings.product');
            }
            if (Schema::connection('platform')->hasColumn('bookings', 'pickup_text')) {
                $q->addSelect('bookings.pickup_text');
            }
        } else {
            $q->select('bookings.*');
        }
        if ($actor) {
            TerritoryScope::applyBookings($q, $actor, 'bookings');
        }

        return $q->orderByDesc('bookings.id')->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => self::bookingLabel($row),
            ])->all();
    }

    /**
     * @param  list<array<string, mixed>|null>  $rows
     * @return list<array{id: int, label: string}>
     */
    public static function fromBookingRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $id = (int) $row['id'];
            $out[$id] = [
                'id' => $id,
                'label' => trim(implode(' · ', array_filter([
                    $row['publicRef'] ?? ('#'.$id),
                    $row['product'] ?? null,
                    $row['status'] ?? null,
                    $row['pickup'] ?? null,
                ], fn ($part) => $part !== null && trim((string) $part) !== ''))),
            ];
        }

        return array_values($out);
    }

    private static function bookingLabel(object $row): string
    {
        $ref = trim((string) ($row->public_ref ?? ''));

        return trim(implode(' · ', array_filter([
            $ref !== '' ? $ref : '#'.$row->id,
            $row->customer_name ?? null,
            $row->customer_phone ?? null,
            $row->product ?? null,
            $row->status ?? null,
        ], fn ($part) => $part !== null && trim((string) $part) !== '')));
    }

    private static function personLabel(?string $name, ?string $phone, ?string $role, int $id): string
    {
        $parts = [trim((string) $name) !== '' ? trim((string) $name) : 'User #'.$id];
        if (trim((string) $phone) !== '') {
            $parts[] = trim((string) $phone);
        }
        if (trim((string) $role) !== '') {
            $parts[] = str_replace('_', ' ', (string) $role);
        }

        return implode(' · ', $parts);
    }
}
