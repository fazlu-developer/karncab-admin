<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use App\Support\CorporatePlanSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin desk for corporate plans. One plan, one driver, a child trip per day.
 * Money stays with admin: approve, record that 10% then 90% landed, assign, renew, commission shares.
 */
class CorporateDeskService
{
    public function rows(User $operator, ?string $status = null): array
    {
        $this->ensure();
        if (! Schema::connection('platform')->hasTable('corporate_travel_bookings')) {
            return [];
        }
        $q = $this->db()->table('corporate_travel_bookings')->orderByDesc('id')->limit(80);
        $this->territory($q, $operator);
        if ($status) {
            $q->where('status', $status);
        }
        $jobs = $q->get();
        $ids = $jobs->pluck('id')->all() ?: [0];
        $trips = Schema::connection('platform')->hasTable('corporate_daily_trips')
            ? $this->db()->table('corporate_daily_trips')->whereIn('corporate_booking_id', $ids)->orderBy('trip_date')->get()->groupBy('corporate_booking_id')
            : collect();

        return $jobs->map(function ($row) use ($trips) {
            $days = $trips->get($row->id, collect());
            $total = (int) ($row->quote_paise ?? 0);
            $commission = (int) ($row->commission_paise ?? 0);
            $today = $days->first(fn ($trip) => (string) $trip->trip_date === now()->toDateString());

            return [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => (string) ($row->status ?? ''),
                'statusLabel' => $this->label((string) ($row->status ?? '')),
                'planKey' => $row->plan_key,
                'tripKind' => (string) ($row->trip_kind ?? 'ONE_WAY'),
                'when' => trim(($row->starts_on ?? $row->travel_date).' → '.($row->ends_on ?? '').' '.(substr((string) ($row->pickup_time ?? ''), 0, 5))),
                'pickup' => $row->pickup_text ?? '',
                'drop' => $row->drop_text ?? '',
                'category' => $row->category ?? '',
                'contactName' => $row->contact_name ?? '',
                'contactPhone' => $row->contact_phone ?? '',
                'totalRupees' => $total / 100,
                'pay10Rupees' => ((int) ($row->pay10_paise ?? 0)) / 100,
                'pay90Rupees' => ((int) ($row->pay90_paise ?? 0)) / 100,
                'paidRupees' => ((int) ($row->paid_paise ?? 0)) / 100,
                'commissionRupees' => $commission / 100,
                'driverShareRupees' => ((int) ($row->driver_share_paise ?? 0)) / 100,
                'districtHeadRupees' => $row->district_head_paise !== null ? ((int) $row->district_head_paise) / 100 : null,
                'fleetOwnerRupees' => $row->fleet_owner_paise !== null ? ((int) $row->fleet_owner_paise) / 100 : null,
                'driverName' => $row->driver_name,
                'driverPhone' => $row->driver_phone,
                'plate' => $row->plate,
                'fleetOwnerId' => $row->fleet_owner_id ? (int) $row->fleet_owner_id : null,
                'fleetOwnerName' => $this->fleetName($row->fleet_owner_id ?? null),
                'stateId' => $row->state_id ? (int) $row->state_id : null,
                'districtId' => $row->district_id ? (int) $row->district_id : null,
                'dayCount' => $days->count(),
                'doneCount' => $days->whereIn('status', ['completed', 'skipped'])->count(),
                'todayPhase' => $today?->phase,
                'rejectReason' => $row->reject_reason ?? null,
                'invoiceNo' => $row->invoice_no ?? null,
                'pickupTime' => substr((string) ($row->pickup_time ?? ''), 0, 5),
                'startsOn' => $row->starts_on,
                'endsOn' => $row->ends_on,
                'renewedFromId' => $row->renewed_from_id,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{plans: int, totalRupees: float, commissionRupees: float, districtShareRupees: float}
     */
    public function totals(array $rows): array
    {
        return [
            'plans' => count($rows),
            'totalRupees' => array_sum(array_column($rows, 'totalRupees')),
            'commissionRupees' => array_sum(array_column($rows, 'commissionRupees')),
            'districtShareRupees' => array_sum(array_map(fn ($row) => (float) ($row['districtHeadRupees'] ?? 0), $rows)),
        ];
    }

    /**
     * @return list<array{id: int, label: string, category: string}>
     */
    public function drivers(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('drivers') || ! Schema::connection('platform')->hasTable('users')) {
            return [];
        }
        $q = $this->db()->table('drivers')->join('users', 'users.id', '=', 'drivers.user_id')->select('drivers.id', 'users.name', 'users.phone', 'drivers.fleet_owner_id');
        $role = TerritoryScope::effectiveRole($operator);
        if ($role === OperatorRole::STATE_HEAD && $operator->state_id && Schema::connection('platform')->hasColumn('users', 'state_id')) {
            $q->where('users.state_id', $operator->state_id);
        }
        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true) && $operator->district_id && Schema::connection('platform')->hasColumn('users', 'district_id')) {
            $q->where('users.district_id', $operator->district_id);
        }
        $vehicles = Schema::connection('platform')->hasTable('vehicles')
            ? $this->db()->table('vehicles')->orderByDesc('id')->get()->groupBy('driver_id')
            : collect();

        return $q->orderBy('users.name')->limit(400)->get()->map(function ($row) use ($vehicles) {
            $vehicle = ($vehicles->get($row->id) ?? collect())->first();
            $category = strtoupper((string) ($vehicle->category ?? ''));
            $label = trim(implode(' · ', array_filter([
                trim((string) $row->name) !== '' ? trim((string) $row->name) : 'Driver',
                trim((string) $row->phone),
                $category,
                (string) ($vehicle->registration_no ?? ''),
            ])));

            return [
                'id' => (int) $row->id,
                'label' => $label,
                'category' => $category,
                'phone' => (string) ($row->phone ?? ''),
                'vehicle' => trim($category.' '.(string) ($vehicle->registration_no ?? '')),
            ];
        })->all();
    }

    public function approve(int $id): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'Only a requested corporate plan can be approved.');
        $this->db()->table('corporate_travel_bookings')->where('id', $id)->update($this->only('corporate_travel_bookings', [
            'status' => 'APPROVED',
            'approved_at' => now(),
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Corporate plan approved', 'Pay 10% of '.$row->public_ref.' to continue.', 'corporate_approved');
    }

    public function reject(int $id, string $reason): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_unless($row->status === 'REQUESTED', 422, 'This corporate plan can no longer be rejected.');
        $reason = trim($reason);
        abort_if($reason === '', 422, 'A reject reason is required.');
        $this->db()->table('corporate_travel_bookings')->where('id', $id)->update($this->only('corporate_travel_bookings', [
            'status' => 'REJECTED',
            'reject_reason' => $reason,
            'updated_at' => now(),
        ]));
        $this->notify((int) $row->customer_id, 'Corporate plan not approved', $reason, 'corporate_rejected');
    }

    public function assign(int $id, int $driverId): void
    {
        $this->ensure();
        $row = $this->must($id);
        if ((string) $row->status === 'ASSIGNED' && (int) $row->driver_id === $driverId) {
            return;
        }
        abort_unless((string) $row->status === 'PAID_100', 422, 'Assign the driver after the customer has paid 100% of the plan.');
        $driver = $this->db()->table('drivers')->where('id', $driverId)->first();
        abort_unless($driver, 422, 'Choose a driver from the list.');
        $user = $this->db()->table('users')->where('id', $driver->user_id)->first();
        abort_unless($user, 422, 'That driver has no login.');
        $vehicles = Schema::connection('platform')->hasTable('vehicles')
            ? $this->db()->table('vehicles')->where('driver_id', $driverId)->get()
            : collect();
        $match = $vehicles->first(fn ($vehicle) => strtoupper((string) ($vehicle->category ?? '')) === strtoupper((string) $row->category)) ?: $vehicles->first();
        abort_unless($match, 422, trim(($user->name ?? 'Driver').' has no vehicle.'));
        $this->assertFree($driverId, $row);
        $fleetId = (int) ($match->fleet_owner_id ?? $driver->fleet_owner_id ?? 0);
        $fleetUser = $fleetId && Schema::connection('platform')->hasTable('fleet_owners')
            ? (int) ($this->db()->table('fleet_owners')->where('id', $fleetId)->value('user_id') ?? 0)
            : 0;
        $otp = $row->start_otp ?: (string) random_int(1000, 9999);
        $this->db()->table('corporate_travel_bookings')->where('id', $id)->update($this->only('corporate_travel_bookings', [
            'status' => 'ASSIGNED',
            'driver_id' => $driverId,
            'driver_user_id' => $user->id,
            'vehicle_id' => $match->id ?? null,
            'fleet_owner_id' => $fleetId ?: null,
            'fleet_owner_user_id' => $fleetUser ?: null,
            'driver_name' => $user->name,
            'driver_phone' => $user->phone,
            'plate' => $match->registration_no ?? null,
            'start_otp' => $otp,
            'assigned_at' => now(),
            'updated_at' => now(),
        ]));
        if (Schema::connection('platform')->hasTable('corporate_daily_trips')) {
            $this->db()->table('corporate_daily_trips')->where('corporate_booking_id', $id)->where('status', 'scheduled')->update([
                'driver_id' => $driverId,
                'updated_at' => now(),
            ]);
        }
        $fresh = $this->must($id);
        $this->credit((int) $user->id, 'DRIVER', (int) $fresh->driver_share_paise, 'CORP'.$id.'-DRIVER', 'Corporate plan '.$fresh->public_ref.' driver share. Plan id '.$id.'.', (int) $fresh->quote_paise);
        $this->debitPlatform((int) $fresh->driver_share_paise, 'CORP'.$id.'-DRIVER-OUT', 'Driver share for corporate plan '.$id, (int) $fresh->quote_paise);
        $this->postShares($fresh);
        $this->notify((int) $fresh->customer_id, 'Corporate driver assigned', ($user->name ?: 'Your driver').' will pick you up daily at '.$fresh->pickup_time.'.', 'corporate_assigned');
        $this->notify((int) $user->id, 'Corporate plan assigned', 'Daily pickup at '.$fresh->pickup_time.' for '.$fresh->public_ref.'. The plan is prepaid.', 'corporate_assigned');
    }

    public function shares(int $id, int $districtPaise, int $fleetPaise): void
    {
        $this->ensure();
        $row = $this->must($id);
        $commission = (int) $row->commission_paise;
        abort_if($districtPaise + $fleetPaise > $commission, 422, 'District and fleet shares cannot exceed the platform commission.');
        if ($fleetPaise > 0) {
            abort_unless((int) ($row->fleet_owner_user_id ?? 0) > 0, 422, 'This car is not a district fleet vehicle.');
        }
        $head = null;
        if ($districtPaise > 0) {
            abort_unless($row->district_id && Schema::connection('platform')->hasColumn('users', 'district_id'), 422, 'This district has no District Head.');
            $head = $this->db()->table('users')->where('role', 'DISTRICT_HEAD')->where('district_id', $row->district_id)->value('id');
            abort_unless($head, 422, 'This district has no District Head.');
        }
        $this->db()->table('corporate_travel_bookings')->where('id', $id)->update($this->only('corporate_travel_bookings', [
            'district_head_paise' => $districtPaise,
            'fleet_owner_paise' => $fleetPaise,
            'district_head_user_id' => $head,
            'updated_at' => now(),
        ]));
        $fresh = $this->must($id);
        if (in_array((string) $fresh->status, ['ASSIGNED', 'LIVE', 'EXPIRED', 'COMPLETED'], true)) {
            $this->postShares($fresh);
        }
    }

    public function timetable(int $id, string $time, string $startsOn, string $endsOn): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_if(in_array((string) $row->status, ['REJECTED', 'CANCELLED', 'COMPLETED'], true), 422, 'This plan can no longer be rescheduled.');
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();
        abort_if($end->lt($start), 422, 'The plan end date is before the start date.');
        $this->db()->table('corporate_travel_bookings')->where('id', $id)->update($this->only('corporate_travel_bookings', [
            'pickup_time' => substr($time, 0, 5),
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'travel_date' => $start->toDateString(),
            'duration_days' => $start->diffInDays($end) + 1,
            'updated_at' => now(),
        ]));
        $fresh = $this->must($id);
        $this->syncTrips($fresh);
        $this->notify((int) $fresh->customer_id, 'Corporate timetable updated', $fresh->public_ref.' now picks up at '.$fresh->pickup_time.'.', 'corporate_timetable');
        if ($fresh->driver_user_id) {
            $this->notify((int) $fresh->driver_user_id, 'Corporate timetable updated', $fresh->public_ref.' now picks up at '.$fresh->pickup_time.'.', 'corporate_timetable');
        }
    }

    public function renew(int $id, int $days, bool $comped): void
    {
        $this->ensure();
        $row = $this->must($id);
        abort_if(in_array((string) $row->status, ['REJECTED', 'CANCELLED', 'REQUESTED'], true), 422, 'Renew a plan after it has been approved.');
        $days = max(7, $days);
        $start = Carbon::parse((string) ($row->ends_on ?: now()->toDateString()))->addDay();
        $end = $start->copy()->addDays($days - 1);
        $oldDays = max(1, (int) ($row->duration_days ?? 1));
        $total = (int) round(((int) $row->quote_paise) * $days / $oldDays);
        $pay10 = (int) round($total * 0.10);
        $percent = (float) ($row->commission_percent ?? 10);
        $commission = (int) round($total * $percent / 100);
        $newId = $this->db()->table('corporate_travel_bookings')->insertGetId($this->only('corporate_travel_bookings', [
            'public_ref' => 'CORP'.random_int(1000000, 9999999),
            'customer_id' => $row->customer_id,
            'corporate_account_id' => $row->corporate_account_id ?? null,
            'plan_id' => $row->plan_id,
            'plan_key' => $days <= 7 ? 'DAY_7' : ($days <= 15 ? 'DAY_15' : 'DAY_30'),
            'category' => $row->category,
            'trip_kind' => $row->trip_kind,
            'quote_paise' => $total,
            'pay10_paise' => $pay10,
            'pay90_paise' => $total - $pay10,
            'paid_paise' => $comped ? $total : 0,
            'driver_share_paise' => max(0, $total - $commission),
            'commission_paise' => $commission,
            'commission_percent' => $percent,
            'pickup_text' => $row->pickup_text,
            'drop_text' => $row->drop_text,
            'pickup_lat' => $row->pickup_lat,
            'pickup_lng' => $row->pickup_lng,
            'drop_lat' => $row->drop_lat,
            'drop_lng' => $row->drop_lng,
            'travel_date' => $start->toDateString(),
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'duration_days' => $days,
            'pickup_time' => $row->pickup_time,
            'passengers' => $row->passengers,
            'stops_json' => $row->stops_json ?? null,
            'payment_status' => $comped ? 'paid' : 'unpaid',
            'payment_method' => $comped ? 'COMPED' : null,
            'status' => $comped ? 'PAID_100' : 'REQUESTED',
            'comped' => $comped,
            'state_id' => $row->state_id,
            'district_id' => $row->district_id,
            'contact_name' => $row->contact_name,
            'contact_phone' => $row->contact_phone,
            'renewed_from_id' => $row->id,
            'invoice_no' => $comped ? ('CINV'.strtoupper(Str::random(8))) : null,
            'approved_at' => $comped ? now() : null,
            'paid_full_at' => $comped ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->syncTrips($this->must($newId));
        $this->notify((int) $row->customer_id, $comped ? 'Corporate plan renewed' : 'Corporate renewal requested', 'A new plan period is linked to '.$row->public_ref.'.', 'corporate_renewed');
    }

    private function postShares(object $row): void
    {
        $district = (int) ($row->district_head_paise ?? 0);
        $fleet = (int) ($row->fleet_owner_paise ?? 0);
        if ($district > 0 && $row->district_head_user_id) {
            $ref = 'CORP'.$row->id.'-DISTRICT';
            $this->credit((int) $row->district_head_user_id, 'DISTRICT_HEAD', $district, $ref, 'District share of corporate plan '.$row->id, (int) $row->commission_paise);
            $this->debitPlatform($district, $ref.'-OUT', 'District share of corporate plan '.$row->id, (int) $row->commission_paise);
        }
        if ($fleet > 0 && $row->fleet_owner_user_id) {
            $ref = 'CORP'.$row->id.'-FLEET';
            $this->credit((int) $row->fleet_owner_user_id, 'FLEET_OWNER', $fleet, $ref, 'Fleet share of corporate plan '.$row->id, (int) $row->commission_paise);
            $this->debitPlatform($fleet, $ref.'-OUT', 'Fleet share of corporate plan '.$row->id, (int) $row->commission_paise);
        }
        if ($district > 0 || $fleet > 0) {
            $this->db()->table('corporate_travel_bookings')->where('id', $row->id)->update($this->only('corporate_travel_bookings', [
                'shares_posted_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function credit(int $userId, string $ownerType, int $amount, string $ref, string $note, int $gross): void
    {
        if ($userId < 1 || $amount < 1 || $this->ledgerExists($ref) || ! Schema::connection('platform')->hasTable('wallets')) {
            return;
        }
        $wallet = $this->wallet($userId, $ownerType);
        $before = (int) $wallet->balance_paise;
        $after = $before + $amount;
        $this->db()->table('wallets')->where('id', $wallet->id)->update(['balance_paise' => $after, 'updated_at' => now()]);
        $this->ledger($wallet, $userId, $ownerType, 'CREDIT', $amount, $before, $after, $note, $ref, $gross);
    }

    private function debitPlatform(int $amount, string $ref, string $note, int $gross): void
    {
        if ($amount < 1 || $this->ledgerExists($ref)) {
            return;
        }
        $userId = (int) ($this->db()->table('users')->whereIn('role', ['ADMIN', 'SUPER_ADMIN'])->orderBy('id')->value('id') ?? 0);
        if ($userId < 1 || ! Schema::connection('platform')->hasTable('wallets')) {
            return;
        }
        $wallet = $this->wallet($userId, 'PLATFORM');
        $before = (int) $wallet->balance_paise;
        abort_unless($before >= $amount, 422, 'Platform wallet cannot cover this corporate payout.');
        $after = $before - $amount;
        $this->db()->table('wallets')->where('id', $wallet->id)->update(['balance_paise' => $after, 'updated_at' => now()]);
        $this->ledger($wallet, $userId, 'PLATFORM', 'DEBIT', $amount, $before, $after, $note, $ref, $gross);
    }

    private function wallet(int $userId, string $ownerType): object
    {
        $q = $this->db()->table('wallets')->where('owner_user_id', $userId);
        if (Schema::connection('platform')->hasColumn('wallets', 'owner_type')) {
            $q->where(function ($inner) use ($ownerType) {
                $inner->where('owner_type', $ownerType)->orWhereNull('owner_type');
            });
        }
        $wallet = $q->orderByDesc('id')->first();
        if ($wallet) {
            return $wallet;
        }
        $id = $this->db()->table('wallets')->insertGetId($this->only('wallets', [
            'owner_user_id' => $userId,
            'owner_type' => $ownerType,
            'balance_paise' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $this->db()->table('wallets')->where('id', $id)->first();
    }

    private function ledger(object $wallet, int $userId, string $account, string $direction, int $amount, int $before, int $after, string $note, string $ref, int $gross): void
    {
        if (! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return;
        }
        $this->db()->table('wallet_ledger')->insert($this->only('wallet_ledger', [
            'public_ref' => $ref,
            'payment_ref' => $ref,
            'wallet_id' => $wallet->id,
            'owner_user_id' => $userId,
            'account' => $account,
            'owner_type' => $account,
            'direction' => $direction,
            'amount_paise' => $amount,
            'gross_paise' => $gross,
            'balance_before_paise' => $before,
            'balance_after_paise' => $after,
            'kind' => 'corporate_plan',
            'status' => 'posted',
            'note' => $note,
            'created_at' => now(),
        ]));
    }

    private function ledgerExists(string $ref): bool
    {
        if (! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return false;
        }
        $q = $this->db()->table('wallet_ledger')->where('public_ref', $ref);
        if (Schema::connection('platform')->hasColumn('wallet_ledger', 'payment_ref')) {
            $q = $this->db()->table('wallet_ledger')->where(function ($inner) use ($ref) {
                $inner->where('public_ref', $ref)->orWhere('payment_ref', $ref);
            });
        }

        return $q->exists();
    }

    private function syncTrips(object $plan): void
    {
        if (! Schema::connection('platform')->hasTable('corporate_daily_trips')) {
            return;
        }
        $start = Carbon::parse((string) ($plan->starts_on ?: $plan->travel_date))->startOfDay();
        $end = Carbon::parse((string) ($plan->ends_on ?: $plan->starts_on))->startOfDay();
        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }
        $existing = $this->db()->table('corporate_daily_trips')->where('corporate_booking_id', $plan->id)->get();
        $byDate = [];
        foreach ($existing as $trip) {
            $byDate[(string) $trip->trip_date] = $trip;
        }
        foreach ($byDate as $date => $trip) {
            if (! in_array($date, $dates, true)) {
                if ((string) $trip->status === 'scheduled') {
                    $this->db()->table('corporate_daily_trips')->where('id', $trip->id)->delete();
                }
                continue;
            }
            if ((string) $trip->status === 'scheduled') {
                $this->db()->table('corporate_daily_trips')->where('id', $trip->id)->update([
                    'pickup_time' => substr((string) $plan->pickup_time, 0, 5),
                    'driver_id' => $plan->driver_id,
                    'updated_at' => now(),
                ]);
            }
        }
        foreach ($dates as $date) {
            if (isset($byDate[$date])) {
                continue;
            }
            $this->db()->table('corporate_daily_trips')->insert([
                'corporate_booking_id' => $plan->id,
                'trip_date' => $date,
                'pickup_time' => substr((string) $plan->pickup_time, 0, 5),
                'status' => 'scheduled',
                'phase' => 'idle',
                'driver_id' => $plan->driver_id,
                'stop_index' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function assertFree(int $driverId, object $plan): void
    {
        $start = (string) ($plan->starts_on ?: $plan->travel_date);
        $end = (string) ($plan->ends_on ?: $start);
        $time = $this->minutes(substr((string) $plan->pickup_time, 0, 5));
        $hit = $this->db()->table('corporate_travel_bookings')
            ->where('driver_id', $driverId)
            ->where('id', '!=', $plan->id)
            ->whereIn('status', ['ASSIGNED', 'LIVE'])
            ->get();
        foreach ($hit as $row) {
            $otherStart = (string) ($row->starts_on ?: $row->travel_date);
            $otherEnd = (string) ($row->ends_on ?: $otherStart);
            if ($start <= $otherEnd && $end >= $otherStart && abs($time - $this->minutes(substr((string) $row->pickup_time, 0, 5))) < 120) {
                abort(422, 'This driver already has a corporate plan at that daily time.');
            }
        }
    }

    private function minutes(string $time): int
    {
        $parts = preg_split('/[:.]/', $time) ?: [];

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function fleetName(mixed $id): ?string
    {
        if (! $id || ! Schema::connection('platform')->hasTable('fleet_owners')) {
            return null;
        }
        $fleet = $this->db()->table('fleet_owners')->where('id', $id)->first();
        if (! $fleet) {
            return null;
        }
        if (! empty($fleet->user_id) && Schema::connection('platform')->hasTable('users')) {
            $name = $this->db()->table('users')->where('id', $fleet->user_id)->value('name');
            if ($name) {
                return (string) $name;
            }
        }

        return (string) ($fleet->company_name ?? $fleet->name ?? 'Fleet owner');
    }

    private function label(string $status): string
    {
        return match ($status) {
            'REQUESTED' => 'Waiting for approval',
            'APPROVED' => 'Pay 10%',
            'APPROVED_10' => 'Pay remaining 90%',
            'PAID_100' => 'Paid in full',
            'ASSIGNED' => 'Driver assigned',
            'LIVE' => 'Today\'s ride is live',
            'EXPIRED' => 'Plan expired',
            'COMPLETED' => 'Plan completed',
            'REJECTED' => 'Rejected',
            default => $status,
        };
    }

    private function territory($query, User $operator): void
    {
        if (TerritoryScope::isUnrestricted($operator)) {
            return;
        }
        $role = TerritoryScope::effectiveRole($operator);
        if ($role === OperatorRole::STATE_HEAD) {
            $operator->state_id
                ? $query->where('state_id', $operator->state_id)
                : $query->whereRaw('0 = 1');
        }
        if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
            $operator->district_id
                ? $query->where('district_id', $operator->district_id)
                : $query->whereRaw('0 = 1');
        }
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
        $row = $this->db()->table('corporate_travel_bookings')->where('id', $id)->first();
        abort_unless($row, 404, 'Corporate plan not found');

        return $row;
    }

    private function ensure(): void
    {
        CorporatePlanSchema::ensure('platform');
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
