<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin desk for bulk bookings. One parent job, N child cabs.
 * Drivers are assigned only after the customer pays 10% of the grand total.
 */
class BulkDeskService
{
    public function rows(): array
    {
        $this->ensure();
        if (! Schema::connection('platform')->hasTable('bulk_bookings')) {
            return [];
        }
        $jobs = $this->db()->table('bulk_bookings')->orderByDesc('id')->limit(80)->get();
        $ids = $jobs->pluck('id')->all() ?: [0];
        $cabs = Schema::connection('platform')->hasTable('bulk_booking_cabs')
            ? $this->db()->table('bulk_booking_cabs')->whereIn('bulk_booking_id', $ids)->orderBy('cab_no')->get()->groupBy('bulk_booking_id')
            : collect();

        return $jobs->map(function ($row) use ($cabs) {
            $fleet = $cabs->get($row->id, collect());
            $assigned = $fleet->filter(fn ($cab) => (int) ($cab->driver_id ?? 0) > 0)->count();
            $n = (int) ($row->vehicle_count ?? $fleet->count());
            $total = (int) ($row->quote_paise ?? 0);
            $advance = (int) ($row->advance_paise ?? (int) round($total * 0.10));
            $wait = (int) ($row->waiting_extra_paise ?? 0);

            return [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => (string) ($row->status ?? ''),
                'statusLabel' => $this->label((string) ($row->status ?? ''), $assigned, max(1, $n)),
                'tripKind' => (string) ($row->trip_kind ?? 'ONE_WAY'),
                'when' => trim(($row->event_date ?? '').' '.($row->event_time ?? '')),
                'pickup' => $row->pickup_text ?? '',
                'drop' => $row->drop_text ?? '',
                'category' => $row->category ?? '',
                'vehicleCount' => $n,
                'assignedCount' => $assigned,
                'assignRemaining' => (string) $row->status === 'ADVANCE_PAID' && $assigned < $n,
                'contactName' => $row->contact_name ?? '',
                'contactPhone' => $row->contact_phone ?? '',
                'totalRupees' => ($total + $wait) / 100,
                'advanceRupees' => $advance / 100,
                'balanceRupees' => (max(0, $total - $advance) + $wait) / 100,
                'advancePaid' => (int) ($row->advance_paid_paise ?? 0) > 0,
                'rejectReason' => $row->reject_reason ?? null,
                'cabs' => $fleet->map(fn ($cab) => [
                    'cabNo' => (int) $cab->cab_no,
                    'category' => (string) ($cab->category ?? ''),
                    'driverName' => $cab->driver_name,
                    'driverPhone' => $cab->driver_phone,
                    'plate' => $cab->plate,
                    'phase' => (string) ($cab->phase ?? 'idle'),
                ])->all(),
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, label: string, category: string}>
     */
    public function drivers(): array
    {
        if (! Schema::connection('platform')->hasTable('drivers') || ! Schema::connection('platform')->hasTable('users')) {
            return [];
        }
        $q = $this->db()->table('drivers')->join('users', 'users.id', '=', 'drivers.user_id')->select('drivers.id', 'users.name', 'users.phone');
        $vehicles = Schema::connection('platform')->hasTable('vehicles')
            ? $this->db()->table('vehicles')->orderByDesc('id')->get()->groupBy('driver_id')
            : collect();

        return $q->orderBy('users.name')->limit(400)->get()->map(function ($row) use ($vehicles) {
            $vehicle = ($vehicles->get($row->id) ?? collect())->first();
            $category = strtoupper((string) ($vehicle->category ?? ''));
            $plate = (string) ($vehicle->registration_no ?? '');
            $label = trim(implode(' · ', array_filter([
                trim((string) $row->name) !== '' ? trim((string) $row->name) : 'Driver',
                trim((string) $row->phone),
                $category,
                $plate,
            ])));

            return [
                'id' => (int) $row->id,
                'label' => $label,
                'category' => $category,
            ];
        })->all();
    }

    public function confirm(int $id): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'Only a requested bulk booking can be confirmed.');
        $this->db()->table('bulk_bookings')->where('id', $id)->update($this->only('bulk_bookings', [
            'status' => 'CONFIRMED_UNPAID',
            'invoice_no' => $row->invoice_no ?: ('BINV'.strtoupper(Str::random(8))),
            'confirmed_at' => now(),
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Bulk booking confirmed', 'Pay the 10% advance on '.$row->public_ref.' so drivers can be assigned.', 'bulk_confirmed');
    }

    public function reject(int $id, string $reason): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'This bulk booking can no longer be rejected.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'A reject reason is required.');
        $this->db()->table('bulk_bookings')->where('id', $id)->update($this->only('bulk_bookings', [
            'status' => 'REJECTED',
            'reject_reason' => $reason,
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Bulk booking not confirmed', $reason, 'bulk_rejected');
    }

    /**
     * @param  list<int>  $driverIds
     */
    public function assign(int $id, array $driverIds): void
    {
        $this->ensure();
        $row = $this->must($id);
        $cabs = $this->db()->table('bulk_booking_cabs')->where('bulk_booking_id', $id)->orderBy('cab_no')->get()->all();
        $n = count($cabs);
        $ids = array_map('intval', array_values($driverIds));
        abort_unless(count($ids) === $n && $n >= 2, 422, 'Assign remaining drivers. This booking needs exactly '.$n.' drivers.');
        abort_unless(count($ids) === count(array_unique($ids)), 422, 'Each cab needs a different driver.');
        abort_unless($row->status === 'ADVANCE_PAID', 422, 'Pay the 10% advance before assigning drivers.');
        $picked = [];
        foreach ($ids as $i => $driverId) {
            $picked[] = $this->driverForCab($cabs[$i], $driverId, $row);
        }
        $otp = $row->start_otp ?: (string) random_int(1000, 9999);
        foreach ($cabs as $i => $cab) {
            $driver = $picked[$i];
            $this->db()->table('bulk_booking_cabs')->where('id', $cab->id)->update($this->only('bulk_booking_cabs', [
                'driver_id' => $driver['driverId'],
                'driver_user_id' => $driver['userId'],
                'vehicle_id' => $driver['vehicleId'],
                'driver_name' => $driver['name'],
                'driver_phone' => $driver['phone'],
                'plate' => $driver['plate'],
                'status' => 'assigned',
                'phase' => 'idle',
                'updated_at' => now(),
            ]));
        }
        $this->db()->table('bulk_bookings')->where('id', $id)->update($this->only('bulk_bookings', [
            'status' => 'ASSIGNED',
            'start_otp' => $otp,
            'assigned_at' => now(),
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Bulk drivers assigned', $n.' cabs are assigned on '.$row->public_ref.'. Share the start OTP.', 'bulk_assigned');
        foreach ($picked as $driver) {
            $this->notify($driver['userId'], 'Bulk job assigned', 'You have one cab on '.$row->public_ref.'. Call the customer, then activate with their OTP.', 'bulk_assigned');
        }
    }

    /**
     * @return array{driverId: int, userId: int, vehicleId: ?int, name: string, phone: string, plate: string}
     */
    private function driverForCab(object $cab, int $driverId, object $parent): array
    {
        $driver = $this->db()->table('drivers')->where('id', $driverId)->first();
        abort_unless($driver, 422, 'Choose a driver from the list.');
        $user = $this->db()->table('users')->where('id', $driver->user_id)->first();
        abort_unless($user, 422, 'That driver has no login.');
        $vehicles = Schema::connection('platform')->hasTable('vehicles')
            ? $this->db()->table('vehicles')->where('driver_id', $driverId)->get()
            : collect();
        $match = $vehicles->first(fn ($vehicle) => $this->sameFamily((string) ($vehicle->category ?? ''), (string) $cab->category));
        abort_unless($match, 422, trim(($user->name ?? 'Driver').' is not in the '.$cab->category.' category.'));
        $this->assertFree($driverId, $parent);

        return [
            'driverId' => $driverId,
            'userId' => (int) $user->id,
            'vehicleId' => isset($match->id) ? (int) $match->id : null,
            'name' => (string) ($user->name ?? 'Driver'),
            'phone' => (string) ($user->phone ?? ''),
            'plate' => (string) ($match->registration_no ?? ''),
        ];
    }

    private function assertFree(int $driverId, object $parent): void
    {
        $date = (string) $parent->event_date;
        $time = substr((string) $parent->event_time, 0, 5);
        $hit = $this->db()->table('bulk_booking_cabs as cabs')
            ->join('bulk_bookings as jobs', 'jobs.id', '=', 'cabs.bulk_booking_id')
            ->where('cabs.driver_id', $driverId)
            ->where('jobs.id', '!=', $parent->id)
            ->whereNotIn('jobs.status', ['REJECTED', 'CANCELLED', 'COMPLETED'])
            ->where('jobs.event_date', $date)
            ->get(['jobs.event_time', 'cabs.phase']);
        foreach ($hit as $row) {
            if ((string) $row->phase === 'completed') {
                continue;
            }
            if (abs($this->minutes((string) $row->event_time) - $this->minutes($time)) < 120) {
                abort(422, 'This driver is already booked at that date and time.');
            }
        }
        if (! Schema::connection('platform')->hasTable('bookings')) {
            return;
        }
        $busy = $this->db()->table('bookings')->where('driver_id', $driverId)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'CUSTOMER_CANCELLED', 'DRIVER_CANCELLED', 'EXPIRED'])
            ->whereNotNull('scheduled_at')
            ->get(['scheduled_at']);
        foreach ($busy as $booking) {
            $at = \Carbon\Carbon::parse($booking->scheduled_at)->timezone('Asia/Kolkata');
            if ($at->toDateString() === $date && abs($this->minutes($at->format('H:i')) - $this->minutes($time)) < 120) {
                abort(422, 'This driver is already booked at that date and time.');
            }
        }
    }

    private function minutes(string $time): int
    {
        $parts = preg_split('/[:.]/', $time) ?: [];

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function sameFamily(string $left, string $right): bool
    {
        $norm = function (string $value): string {
            $value = strtoupper(trim($value));

            return match ($value) {
                'CAR', 'CAB', 'HATCHBACK', 'MINI', 'PREMIUM' => 'SEDAN',
                'VAN', 'TEMPO', 'TEMPO_TRAVELLER' => 'TRAVELLER',
                default => $value,
            };
        };

        return $norm($left) !== '' && $norm($left) === $norm($right);
    }

    private function label(string $status, int $assigned, int $n): string
    {
        return match ($status) {
            'REQUESTED', 'pending_review' => 'Requested / Waiting for admin',
            'CONFIRMED_UNPAID' => 'Confirmed · customer pays 10%',
            'ADVANCE_PAID' => 'Assign remaining drivers ('.$assigned.'/'.$n.')',
            'ASSIGNED' => 'Drivers assigned',
            'LIVE' => 'Live',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
            'CANCELLED' => 'Cancelled',
            default => $status,
        };
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

    private function must(int $id): object
    {
        $row = $this->db()->table('bulk_bookings')->where('id', $id)->first();
        abort_unless($row, 404, 'Bulk booking not found');

        return $row;
    }

    private function ensure(): void
    {
        if (! Schema::connection('platform')->hasTable('bulk_bookings')) {
            return;
        }
        $cols = [
            'advance_paise' => fn ($t) => $t->unsignedBigInteger('advance_paise')->default(0),
            'advance_paid_paise' => fn ($t) => $t->unsignedBigInteger('advance_paid_paise')->default(0),
            'advance_payment_ref' => fn ($t) => $t->string('advance_payment_ref', 64)->nullable(),
            'commission_paise' => fn ($t) => $t->unsignedBigInteger('commission_paise')->default(0),
            'waiting_extra_paise' => fn ($t) => $t->unsignedBigInteger('waiting_extra_paise')->default(0),
            'waiting_paise_per_min' => fn ($t) => $t->unsignedInteger('waiting_paise_per_min')->default(0),
            'distance_km' => fn ($t) => $t->decimal('distance_km', 8, 2)->nullable(),
            'start_otp' => fn ($t) => $t->string('start_otp', 8)->nullable(),
            'stops_json' => fn ($t) => $t->text('stops_json')->nullable(),
            'invoice_no' => fn ($t) => $t->string('invoice_no', 24)->nullable(),
            'reject_reason' => fn ($t) => $t->string('reject_reason', 240)->nullable(),
            'confirmed_at' => fn ($t) => $t->timestamp('confirmed_at')->nullable(),
            'assigned_at' => fn ($t) => $t->timestamp('assigned_at')->nullable(),
        ];
        foreach ($cols as $name => $define) {
            if (! Schema::connection('platform')->hasColumn('bulk_bookings', $name)) {
                Schema::connection('platform')->table('bulk_bookings', $define);
            }
        }
    }

    private function db()
    {
        return DB::connection('platform');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function only(string $table, array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (Schema::connection('platform')->hasColumn($table, $key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
