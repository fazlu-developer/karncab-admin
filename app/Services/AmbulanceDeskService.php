<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmbulanceDeskService
{
    public function rows(?string $bucket = null, ?string $search = null): array
    {
        if (! Schema::connection('platform')->hasTable('bookings')) {
            return [];
        }
        $q = $this->db()->table('bookings')->where('product', 'AMBULANCE')->orderByDesc('id')->limit(80);
        $statuses = match ($bucket) {
            'waiting' => ['REQUESTED', 'PENDING', 'CONFIRMED'],
            'assigned' => ['DRIVER_ACCEPTED', 'ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'OTP_VERIFIED', 'TRIP_STARTED', 'STARTED'],
            'done' => ['COMPLETED'],
            'cancelled' => ['CUSTOMER_CANCELLED', 'DRIVER_CANCELLED', 'CANCELLED', 'EXPIRED'],
            default => null,
        };
        if ($statuses) {
            $q->whereIn('status', $statuses);
        }
        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('public_ref', 'like', $like)
                    ->orWhere('pickup_text', 'like', $like)
                    ->orWhere('drop_text', 'like', $like)
                    ->orWhere('passenger_name', 'like', $like)
                    ->orWhere('passenger_phone', 'like', $like);
            });
        }
        $drivers = $this->driverNames();

        return $q->get()->map(function ($row) use ($drivers) {
            return [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => strtoupper((string) $row->status),
                'pickup' => $row->pickup_text ?? '',
                'drop' => $row->drop_text ?? '',
                'contactName' => $row->passenger_name ?? '',
                'contactPhone' => $row->passenger_phone ?? '',
                'photo' => $row->patient_photo_url ?? null,
                'driverName' => $drivers[(int) ($row->driver_id ?? 0)] ?? null,
                'canAssign' => in_array(strtoupper((string) $row->status), ['REQUESTED', 'PENDING', 'CONFIRMED'], true) && empty($row->driver_id),
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function ambulanceDrivers(): array
    {
        if (! Schema::connection('platform')->hasTable('drivers') || ! Schema::connection('platform')->hasTable('vehicles')) {
            return [];
        }
        $q = $this->db()->table('drivers')
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id')
            ->whereRaw('UPPER(vehicles.category) = ?', ['AMBULANCE'])
            ->select('drivers.id', 'users.name', 'users.phone')
            ->orderBy('users.name')
            ->limit(300);

        return $q->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'label' => trim(($row->name ?: 'Driver').' · '.($row->phone ?: '')),
        ])->all();
    }

    public function assign(int $id, int $driverId): void
    {
        $row = $this->db()->table('bookings')->where('id', $id)->where('product', 'AMBULANCE')->first();
        abort_unless($row, 404, 'Ambulance booking not found.');
        abort_unless(in_array(strtoupper((string) $row->status), ['REQUESTED', 'PENDING', 'CONFIRMED'], true), 422, 'This emergency is no longer waiting for a driver.');
        $driver = $this->db()->table('drivers')->where('id', $driverId)->first();
        abort_unless($driver, 422, 'Choose an ambulance driver.');
        $vehicleId = $this->db()->table('vehicles')
            ->where('driver_id', $driverId)
            ->whereRaw('UPPER(category) = ?', ['AMBULANCE'])
            ->orderByDesc('id')
            ->value('id');
        abort_unless($vehicleId, 422, 'That driver has no ambulance vehicle.');
        $userId = (int) ($driver->user_id ?? 0);
        abort_if($userId < 1, 422, 'That driver has no login.');
        $this->db()->table('bookings')->where('id', $id)->update(array_filter([
            'status' => 'DRIVER_ACCEPTED',
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'quote_paise' => 0,
            'commission_paise' => Schema::connection('platform')->hasColumn('bookings', 'commission_paise') ? 0 : null,
            'updated_at' => now(),
        ], fn ($value) => $value !== null));
        $ref = $row->public_ref ?: ('#'.$id);
        $this->notify((int) $row->customer_id, 'Ambulance assigned', 'An ambulance is on the way for '.$ref.'.', 'ambulance_assigned');
        $this->notify($userId, 'Ambulance job', 'You are assigned to emergency '.$ref.'. Open the job and go to pickup.', 'ambulance_assigned');
    }

    private function notify(int $userId, string $title, string $body, string $event): void
    {
        if ($userId < 1) {
            return;
        }
        try {
            app(NotificationService::class)->dispatch($userId, $event, ['title' => $title, 'body' => $body], [
                'title' => $title,
                'body' => $body,
                'channels' => ['in_app', 'push'],
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<int, string>
     */
    private function driverNames(): array
    {
        if (! Schema::connection('platform')->hasTable('drivers')) {
            return [];
        }

        return $this->db()->table('drivers')
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.id', 'users.name')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id => (string) ($row->name ?: 'Driver')])
            ->all();
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
