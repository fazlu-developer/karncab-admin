<?php

namespace App\Services;

use App\Models\User;
use App\Platform\FleetDhShare;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeeklyCommissionSettle
{
    public function __construct(private readonly WalletLedgerService $wallets) {}

    /**
     * @return array<string, mixed>
     */
    public function board(User $operator, ?string $week = null): array
    {
        $this->ensure();
        $period = $this->period($week);

        return [
            'period' => $period,
            'canPayDistrict' => $this->canPayDistrict($operator),
            'canPayFleet' => $this->canPayFleet($operator),
            'districts' => $this->canPayDistrict($operator) ? $this->districtRows($operator, $period) : [],
            'fleets' => $this->canPayFleet($operator) ? $this->fleetRows($operator, $period) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settleDistrict(User $operator, int $userId, ?string $week = null): array
    {
        abort_unless($this->canPayDistrict($operator), 403);
        $period = $this->period($week);
        $row = collect($this->districtRows($operator, $period))->firstWhere('userId', $userId);
        abort_unless($row, 404, 'District head is outside this week or your territory.');

        return $this->credit($operator, 'district_head', $userId, 'DISTRICT_HEAD', $period, (int) $row['duePaise'], (int) $row['recordedPaise'], 'District Head week '.$period['label']);
    }

    /**
     * @return array<string, mixed>
     */
    public function settleFleet(User $operator, int $fleetId, ?string $week = null): array
    {
        abort_unless($this->canPayFleet($operator), 403);
        $period = $this->period($week);
        $row = collect($this->fleetRows($operator, $period))->firstWhere('fleetId', $fleetId);
        abort_unless($row, 404, 'Fleet is outside this week or your territory.');
        abort_unless((int) $row['userId'] > 0, 422, 'This fleet has no login user.');

        return $this->credit($operator, 'fleet_owner', (int) $row['userId'], 'FLEET_OWNER', $period, (int) $row['duePaise'], (int) $row['recordedPaise'], 'Fleet owner week '.$period['label'].' · '.$row['name']);
    }

    public function statement(User $operator, ?string $week = null): StreamedResponse
    {
        abort_unless($operator->can('payments.view') || $operator->can('platform.admin'), 403);
        $board = $this->board($operator, $week);
        $filename = 'commission-'.$board['period']['key'].'.csv';

        return response()->streamDownload(function () use ($board) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Name', 'Phone', 'Period', 'Recorded rupees', 'Already in wallet', 'Credited now']);
            foreach ($board['districts'] as $row) {
                fputcsv($out, ['District Head', $row['name'], $row['phone'], $board['period']['label'], $row['recordedRupees'], $row['alreadyRupees'], $row['dueRupees']]);
            }
            foreach ($board['fleets'] as $row) {
                fputcsv($out, ['Fleet owner', $row['name'], $row['phone'], $board['period']['label'], $row['recordedRupees'], $row['alreadyRupees'], $row['dueRupees']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string, key: string}  $period
     * @return array<string, mixed>
     */
    private function credit(User $operator, string $kind, int $userId, string $ownerType, array $period, int $duePaise, int $recordedPaise, string $note): array
    {
        $this->ensure();
        $existing = DB::connection('platform')->table('commission_settlements')
            ->where('kind', $kind)
            ->where('beneficiary_user_id', $userId)
            ->where('period_start', $period['key'])
            ->first();
        abort_unless(! $existing, 422, 'This week is already settled for this wallet.');
        $credited = 0;
        if ($duePaise > 0) {
            $this->wallets->postLedger([
                'ownerType' => $ownerType,
                'ownerUserId' => $userId,
                'direction' => 'CREDIT',
                'amountPaise' => $duePaise,
                'kind' => $kind === 'district_head' ? 'district_week_settlement' : 'fleet_week_settlement',
                'note' => $note,
                'paymentRef' => 'WK-'.$kind.'-'.$userId.'-'.$period['key'],
            ]);
            $credited = $duePaise;
        }
        DB::connection('platform')->table('commission_settlements')->insert([
            'kind' => $kind,
            'beneficiary_user_id' => $userId,
            'period_start' => $period['key'],
            'period_end' => $period['end']->toDateString(),
            'amount_paise' => $recordedPaise,
            'credited_paise' => $credited,
            'status' => 'settled',
            'settled_by' => $operator->id,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'creditedRupees' => $credited / 100,
            'recordedRupees' => $recordedPaise / 100,
            'label' => $period['label'],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string, key: string}
     */
    public function period(?string $week): array
    {
        $anchor = $week ? Carbon::parse($week, 'Asia/Kolkata') : now()->timezone('Asia/Kolkata')->startOfWeek(Carbon::MONDAY)->subWeek();
        $start = $anchor->copy()->timezone('Asia/Kolkata')->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => $start->format('j M').' – '.$end->format('j M Y'),
            'key' => $start->toDateString(),
        ];
    }

    private function canPayDistrict(User $operator): bool
    {
        return $operator->isPrivilegedOperator() || $operator->isSuperAdmin() || $operator->isStateHead();
    }

    private function canPayFleet(User $operator): bool
    {
        return $operator->isPrivilegedOperator() || $operator->isSuperAdmin() || $operator->isDistrictHead();
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string, key: string}  $period
     * @return list<array<string, mixed>>
     */
    private function districtRows(User $operator, array $period): array
    {
        $db = DB::connection('platform');
        if (! Schema::connection('platform')->hasTable('users')) {
            return [];
        }
        $q = $db->table('users')->where('role', OperatorRole::DISTRICT_HEAD)->orderBy('name');
        if ($operator->isStateHead() && $operator->state_id) {
            $q->where('state_id', $operator->state_id);
        } elseif (! TerritoryScope::isUnrestricted($operator) && ! $operator->isPrivilegedOperator()) {
            return [];
        }
        $rows = [];
        foreach ($q->get(['id', 'name', 'phone', 'district_id', 'state_id']) as $head) {
            $bookings = $this->completed($period)->where('district_id', (int) $head->district_id);
            $recorded = $this->sumColumn($bookings, 'district_head_commission_paise');
            $already = $this->alreadyCredited((int) $head->id, ['district_head_commission', 'district_week_settlement'], $period);
            $settled = $this->isSettled('district_head', (int) $head->id, $period['key']);
            $due = $settled ? 0 : max(0, $recorded - $already);
            $rows[] = $this->moneyRow((int) $head->id, 0, (string) ($head->name ?: 'District Head'), (string) ($head->phone ?? ''), $recorded, $already, $due, $settled);
        }

        return $rows;
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string, key: string}  $period
     * @return list<array<string, mixed>>
     */
    private function fleetRows(User $operator, array $period): array
    {
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('fleet_owners')) {
            return [];
        }
        FleetDhShare::ensure();
        $q = $db->table('fleet_owners')->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')->orderBy('fleet_owners.id');
        if ($operator->isDistrictHead() && $operator->district_id) {
            $districtId = (int) $operator->district_id;
            $q->where(function ($inner) use ($districtId, $schema) {
                $inner->where('users.district_id', $districtId);
                if ($schema->hasColumn('fleet_owners', 'district_id')) {
                    $inner->orWhere('fleet_owners.district_id', $districtId);
                }
            });
        } elseif (! TerritoryScope::isUnrestricted($operator) && ! $operator->isPrivilegedOperator()) {
            return [];
        }
        $rows = [];
        foreach ($q->get(['fleet_owners.id', 'fleet_owners.user_id', 'fleet_owners.trade_name', 'fleet_owners.dh_share_percent', 'users.name as owner_name', 'users.phone as owner_phone']) as $fleet) {
            $driverIds = $schema->hasTable('drivers') ? $db->table('drivers')->where('fleet_owner_id', $fleet->id)->pluck('id') : collect();
            $vehicleIds = $schema->hasTable('vehicles') ? $db->table('vehicles')->where('fleet_owner_id', $fleet->id)->pluck('id') : collect();
            $bookings = $this->completed($period)->where(function ($inner) use ($driverIds, $vehicleIds) {
                $matched = false;
                if ($vehicleIds->isNotEmpty() && Schema::connection('platform')->hasColumn('bookings', 'vehicle_id')) {
                    $inner->orWhereIn('vehicle_id', $vehicleIds);
                    $matched = true;
                }
                if ($driverIds->isNotEmpty()) {
                    $inner->orWhereIn('driver_id', $driverIds);
                    $matched = true;
                }
                if (! $matched) {
                    $inner->whereRaw('0 = 1');
                }
            });
            $recorded = $this->sumColumn($bookings, 'fleet_commission_paise');
            $userId = (int) ($fleet->user_id ?? 0);
            $already = $userId > 0 ? $this->alreadyCredited($userId, ['fleet_commission', 'fleet_week_settlement'], $period) : 0;
            $settled = $userId > 0 && $this->isSettled('fleet_owner', $userId, $period['key']);
            $due = $settled ? 0 : max(0, $recorded - $already);
            $share = $fleet->dh_share_percent === null || $fleet->dh_share_percent === '' ? '50% default' : ((float) $fleet->dh_share_percent).'% custom';
            $row = $this->moneyRow($userId, (int) $fleet->id, (string) ($fleet->trade_name ?: $fleet->owner_name ?: 'Fleet'), (string) ($fleet->owner_phone ?? ''), $recorded, $already, $due, $settled);
            $row['share'] = $share;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string, key: string}  $period
     */
    private function completed(array $period)
    {
        return DB::connection('platform')->table('bookings')
            ->where('status', 'COMPLETED')
            ->where('updated_at', '>=', $period['start'])
            ->where('updated_at', '<=', $period['end']);
    }

    private function sumColumn($query, string $column): int
    {
        if (! Schema::connection('platform')->hasColumn('bookings', $column)) {
            return 0;
        }

        return (int) (clone $query)->sum($column);
    }

    /**
     * @param  list<string>  $kinds
     * @param  array{start: Carbon, end: Carbon, label: string, key: string}  $period
     */
    private function alreadyCredited(int $userId, array $kinds, array $period): int
    {
        if (! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return 0;
        }

        return (int) DB::connection('platform')->table('wallet_ledger')
            ->where('owner_user_id', $userId)
            ->where('direction', 'CREDIT')
            ->whereIn('kind', $kinds)
            ->where('created_at', '>=', $period['start'])
            ->where('created_at', '<=', $period['end']->copy()->addDay())
            ->sum('amount_paise');
    }

    private function isSettled(string $kind, int $userId, string $key): bool
    {
        if (! Schema::connection('platform')->hasTable('commission_settlements')) {
            return false;
        }

        return DB::connection('platform')->table('commission_settlements')
            ->where('kind', $kind)
            ->where('beneficiary_user_id', $userId)
            ->where('period_start', $key)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function moneyRow(int $userId, int $fleetId, string $name, string $phone, int $recorded, int $already, int $due, bool $settled): array
    {
        return [
            'userId' => $userId,
            'fleetId' => $fleetId,
            'name' => $name,
            'phone' => $phone,
            'recordedPaise' => $recorded,
            'alreadyPaise' => $already,
            'duePaise' => $due,
            'recordedRupees' => $recorded / 100,
            'alreadyRupees' => $already / 100,
            'dueRupees' => $due / 100,
            'settled' => $settled,
        ];
    }

    private function ensure(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('commission_settlements')) {
            return;
        }
        $schema->create('commission_settlements', function ($table) {
            $table->id();
            $table->string('kind', 32);
            $table->unsignedBigInteger('beneficiary_user_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount_paise')->default(0);
            $table->unsignedBigInteger('credited_paise')->default(0);
            $table->string('status', 24)->default('settled');
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'beneficiary_user_id', 'period_start'], 'commission_settlement_period');
        });
    }
}
