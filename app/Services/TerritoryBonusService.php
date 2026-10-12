<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TerritoryBonusService
{
    public function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('territory_bonuses')) {
            return;
        }
        $schema->create('territory_bonuses', function ($table) {
            $table->id();
            $table->string('audience', 16);
            $table->string('kind', 16);
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('amount_rupees')->default(0);
            $table->unsignedInteger('required_rides')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return list<object>
     */
    public function list(): array
    {
        $this->ensureTable();

        return $this->db()->table('territory_bonuses')
            ->leftJoin('states', 'states.id', '=', 'territory_bonuses.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'territory_bonuses.district_id')
            ->orderByDesc('territory_bonuses.id')
            ->get([
                'territory_bonuses.*',
                'states.name as state_name',
                'districts.name as district_name',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): int
    {
        $this->ensureTable();
        $stateId = ! empty($data['state_id']) ? (int) $data['state_id'] : null;
        $districtId = ! empty($data['district_id']) ? (int) $data['district_id'] : null;
        if ($districtId) {
            $district = $this->db()->table('districts')->where('id', $districtId)->first();
            abort_if($district === null, 422, 'Select a valid district.');
            $stateId = (int) $district->state_id;
        }

        return $this->db()->table('territory_bonuses')->insertGetId([
            'audience' => strtoupper((string) $data['audience']),
            'kind' => strtolower((string) $data['kind']),
            'state_id' => $stateId,
            'district_id' => $districtId,
            'enabled' => ! empty($data['enabled']) ? 1 : 0,
            'amount_rupees' => (int) ($data['amount_rupees'] ?? 0),
            'required_rides' => isset($data['required_rides']) ? (int) $data['required_rides'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): void
    {
        $this->ensureTable();
        $this->db()->table('territory_bonuses')->where('id', $id)->update([
            'enabled' => ! empty($data['enabled']) ? 1 : 0,
            'amount_rupees' => (int) ($data['amount_rupees'] ?? 0),
            'required_rides' => isset($data['required_rides']) ? (int) $data['required_rides'] : null,
            'updated_at' => now(),
        ]);
    }

    public function destroy(int $id): void
    {
        $this->ensureTable();
        $this->db()->table('territory_bonuses')->where('id', $id)->delete();
    }

    /**
     * @return array{enabled: bool, amountRupees: int, requiredRides: int, source: string}
     */
    public function resolve(string $audience, string $kind, ?int $stateId, ?int $districtId): array
    {
        $this->ensureTable();
        $fallbackOn = $this->db()->table('system_settings')->where('key', 'driver_welcome_bonus_enabled')->value('value');
        $fallbackAmt = $this->db()->table('system_settings')->where('key', 'driver_welcome_bonus_rupees')->value('value');
        $fallback = [
            'enabled' => strtoupper($audience) === 'DRIVER' && strtolower($kind) === 'joining'
                && ($fallbackOn === null || $fallbackOn === '' || in_array(strtolower((string) $fallbackOn), ['1', 'true', 'yes'], true))
                && (int) $fallbackAmt > 0,
            'amountRupees' => is_numeric($fallbackAmt) ? (int) $fallbackAmt : 0,
            'requiredRides' => 0,
            'source' => 'global',
        ];
        $rows = $this->db()->table('territory_bonuses')
            ->where('audience', strtoupper($audience))
            ->where('kind', strtolower($kind))
            ->orderByDesc('id')
            ->get();
        $district = $districtId
            ? $rows->first(fn ($row) => (int) $row->district_id === (int) $districtId)
            : null;
        $state = $stateId
            ? $rows->first(fn ($row) => (int) ($row->state_id ?? 0) === (int) $stateId && empty($row->district_id))
            : null;
        $global = $rows->first(fn ($row) => empty($row->state_id) && empty($row->district_id));
        $pick = $district ?: ($state ?: $global);
        if (! $pick) {
            return $fallback;
        }
        $enabled = (bool) $pick->enabled && (int) $pick->amount_rupees > 0;

        return [
            'enabled' => $enabled,
            'amountRupees' => $enabled ? (int) $pick->amount_rupees : 0,
            'requiredRides' => (int) ($pick->required_rides ?: 0),
            'source' => $district ? 'district' : ($state ? 'state' : 'territory_global'),
        ];
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
