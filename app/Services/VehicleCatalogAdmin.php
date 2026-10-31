<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VehicleCatalogAdmin
{
    public const TABLE = 'catalog_vehicle_types';

    public function ensure(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable(self::TABLE)) {
            $schema->create(self::TABLE, function ($table) {
                $table->id();
                $table->string('family_key', 40);
                $table->string('family_label', 80);
                $table->string('key', 40);
                $table->string('label', 80);
                $table->unsignedTinyInteger('seats')->default(4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->string('blurb', 180)->nullable();
                $table->string('image_url', 255)->nullable();
                $table->string('family_image_url', 255)->nullable();
                $table->timestamps();
                $table->unique('key');
            });
        } else {
            if (! $schema->hasColumn(self::TABLE, 'image_url')) {
                $schema->table(self::TABLE, function ($table) {
                    $table->string('image_url', 255)->nullable();
                });
            }
            if (! $schema->hasColumn(self::TABLE, 'family_image_url')) {
                $schema->table(self::TABLE, function ($table) {
                    $table->string('family_image_url', 255)->nullable();
                });
            }
        }
        if ($this->db()->table(self::TABLE)->count() === 0) {
            $this->seed();
        }
        $this->ensureAmbulanceTypes();
    }

    /**
     * Adds missing ambulance types. Existing rows, including the original Ambulance type, are left as they are.
     */
    private function ensureAmbulanceTypes(): void
    {
        $now = now();
        $rows = [
            ['AMBULANCE', 'Ambulance', 2, 100, 'Emergency ambulance', null],
            ['BASIC', 'Basic Patient Transport', 2, 101, 'सामान्य मरीज ट्रांसफर', '/uploads/vehicles/ambulance_basic.png'],
            ['BLS', 'BLS Ambulance', 2, 102, 'बेसिक लाइफ सपोर्ट', '/uploads/vehicles/ambulance_bls.png'],
            ['ALS', 'ALS Ambulance', 2, 103, 'उन्नत चिकित्सा सहायता', '/uploads/vehicles/ambulance_als.png'],
        ];
        foreach ($rows as [$key, $label, $seats, $sort, $blurb, $image]) {
            if ($this->db()->table(self::TABLE)->where('key', $key)->exists()) {
                continue;
            }
            $this->db()->table(self::TABLE)->insert([
                'family_key' => 'AMBULANCE',
                'family_label' => 'Ambulance',
                'key' => $key,
                'label' => $label,
                'seats' => $seats,
                'sort_order' => $sort,
                'active' => 1,
                'blurb' => $blurb,
                'image_url' => $image,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->attachAmbulanceTypesToService();
    }

    private function attachAmbulanceTypesToService(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('catalog_services') || ! $schema->hasColumn('catalog_services', 'vehicle_categories')) {
            return;
        }
        $service = $this->db()->table('catalog_services')
            ->where(function ($query) {
                $query->where('category_key', 'AMBULANCE')
                    ->orWhere('slug', 'ambulance');
            })
            ->orderBy('id')
            ->first();
        if (! $service) {
            return;
        }
        $current = json_decode((string) ($service->vehicle_categories ?? ''), true);
        $keys = is_array($current) ? array_map(fn ($key) => strtoupper(trim((string) $key)), $current) : [];
        $keys = array_values(array_filter($keys, fn ($key) => $key !== ''));
        foreach (['AMBULANCE', 'BASIC', 'BLS', 'ALS'] as $key) {
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }
        $this->db()->table('catalog_services')->where('id', $service->id)->update([
            'vehicle_categories' => json_encode($keys),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, types: list<array{id: int, key: string, label: string, seats: int, sort_order: int, active: bool, blurb: ?string}>}>
     */
    public function families(bool $activeOnly = false): array
    {
        $this->ensure();
        $q = $this->db()->table(self::TABLE)->orderBy('sort_order')->orderBy('family_key')->orderBy('label');
        if ($activeOnly) {
            $q->where('active', 1);
        }
        $groups = [];
        foreach ($q->get() as $row) {
            $fk = strtoupper((string) $row->family_key);
            if (! isset($groups[$fk])) {
                $groups[$fk] = [
                    'key' => $fk,
                    'label' => (string) $row->family_label,
                    'image_url' => $row->family_image_url ?? null,
                    'types' => [],
                ];
            } elseif (empty($groups[$fk]['image_url']) && ! empty($row->family_image_url)) {
                $groups[$fk]['image_url'] = $row->family_image_url;
            }
            $groups[$fk]['types'][] = [
                'id' => (int) $row->id,
                'key' => strtoupper((string) $row->key),
                'label' => (string) $row->label,
                'seats' => (int) $row->seats,
                'sort_order' => (int) $row->sort_order,
                'active' => (bool) $row->active,
                'blurb' => $row->blurb,
                'image_url' => $row->image_url ?? null,
            ];
        }

        return array_values($groups);
    }

    /**
     * @return list<array{key: string, label: string, family_key: string, family_label: string, seats: int}>
     */
    public function options(bool $activeOnly = true): array
    {
        $out = [];
        foreach ($this->families($activeOnly) as $family) {
            foreach ($family['types'] as $type) {
                $out[] = [
                    'key' => $type['key'],
                    'label' => $type['label'],
                    'family_key' => $family['key'],
                    'family_label' => $family['label'],
                    'seats' => $type['seats'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        $out = [];
        foreach ($this->options(false) as $row) {
            $out[$row['key']] = $row['label'];
        }

        return $out;
    }

    public function store(array $data): int
    {
        $this->ensure();
        $key = $this->normalizeKey($data['key'] ?? $data['label'] ?? '');
        $familyKey = $this->normalizeKey($data['family_key'] ?? 'CAR');
        $payload = [
            'family_key' => $familyKey,
            'family_label' => trim((string) ($data['family_label'] ?? Str::title(strtolower($familyKey)))),
            'key' => $key,
            'label' => trim((string) ($data['label'] ?? $key)),
            'seats' => max(0, (int) ($data['seats'] ?? 4)),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => ! empty($data['active']),
            'blurb' => $data['blurb'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (! empty($data['image_url'])) {
            $payload['image_url'] = $data['image_url'];
        }
        $familyImage = $this->db()->table(self::TABLE)->where('family_key', $familyKey)->value('family_image_url');
        if ($familyImage) {
            $payload['family_image_url'] = $familyImage;
        }
        if ($this->db()->table(self::TABLE)->where('key', $key)->exists()) {
            $this->db()->table(self::TABLE)->where('key', $key)->update(array_diff_key($payload, ['created_at' => true, 'key' => true]));

            return (int) $this->db()->table(self::TABLE)->where('key', $key)->value('id');
        }

        return (int) $this->db()->table(self::TABLE)->insertGetId($payload);
    }

    public function update(int $id, array $data): void
    {
        $this->ensure();
        $key = $this->normalizeKey($data['key'] ?? '');
        $familyKey = $this->normalizeKey($data['family_key'] ?? 'CAR');
        $payload = [
            'family_key' => $familyKey,
            'family_label' => trim((string) ($data['family_label'] ?? Str::title(strtolower($familyKey)))),
            'key' => $key !== '' ? $key : $this->db()->table(self::TABLE)->where('id', $id)->value('key'),
            'label' => trim((string) ($data['label'] ?? $key)),
            'seats' => max(0, (int) ($data['seats'] ?? 4)),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => ! empty($data['active']),
            'blurb' => $data['blurb'] ?? null,
            'updated_at' => now(),
        ];
        if (array_key_exists('image_url', $data)) {
            $payload['image_url'] = $data['image_url'];
        }
        $this->db()->table(self::TABLE)->where('id', $id)->update($payload);
    }

    public function saveFamilyImage(string $familyKey, ?string $url): void
    {
        $this->ensure();
        $this->db()->table(self::TABLE)->where('family_key', strtoupper($familyKey))->update([
            'family_image_url' => $url,
            'updated_at' => now(),
        ]);
    }

    public function destroy(int $id): void
    {
        $this->ensure();
        $this->db()->table(self::TABLE)->where('id', $id)->delete();
    }

    public static function fareProduct(string $key, string $title = '', string $slug = ''): string
    {
        $blob = strtoupper(str_replace(['-', ' '], '_', trim($key.' '.$title.' '.$slug)));
        if (str_contains($blob, 'PARCEL')) {
            return 'PARCEL';
        }
        if (str_contains($blob, 'CORPORATE')) {
            return 'CORPORATE';
        }
        if (str_contains($blob, 'BULK')) {
            return 'BULK';
        }
        if (str_contains($blob, 'TRAVEL') && ! str_contains($blob, 'TRANSFER')) {
            return 'TRAVEL';
        }
        if (str_contains($blob, 'MULTI')) {
            return 'MULTI_STOP';
        }
        if (str_contains($blob, 'AIRPORT')) {
            return 'AIRPORT';
        }
        if (str_contains($blob, 'RAIL')) {
            return 'RAILWAY';
        }
        if (str_contains($blob, 'RENTAL') || str_contains($blob, 'PACKAGE')) {
            return 'RENTAL';
        }
        if (str_contains($blob, 'SCHEDULE') || str_contains($blob, 'PREBOOK')) {
            return 'SCHEDULE';
        }
        if (str_contains($blob, 'ROUND')) {
            return 'ROUND_WAY';
        }
        if (str_contains($blob, 'ONE_WAY') || str_contains($blob, 'INTERCITY')) {
            return 'ONE_WAY';
        }
        if (str_contains($blob, 'OUTSTATION') && ! str_contains($blob, 'ROUND')) {
            return 'OUTSTATION';
        }
        if (in_array(strtoupper($key), ['LOCAL_CAB', 'CITY_RIDE', 'CITY', 'TRIP', 'LOCAL', 'CAB'], true)) {
            return 'LOCAL_CAB';
        }

        $key = strtoupper(str_replace(['-', ' '], '_', $key));

        return $key !== '' ? $key : 'LOCAL_CAB';
    }

    private function seed(): void
    {
        $now = now();
        $rows = [
            ['BIKE', 'Bike', 'BIKE', 'Bike', 1, 10],
            ['BIKE', 'Bike', 'SCOOTY', 'Scooty', 1, 15],
            ['AUTO', 'Auto', 'AUTO', 'Auto', 3, 20],
            ['AUTO', 'Auto', 'E_RICKSHAW', 'E-Rickshaw', 3, 25],
            ['CAR', 'Car', 'MINI', 'Mini', 4, 30],
            ['CAR', 'Car', 'SEDAN', 'Sedan', 4, 40],
            ['CAR', 'Car', 'SUV', 'SUV', 6, 50],
            ['CAR', 'Car', 'TRAVELLER', 'Traveller', 12, 60],
            ['LOADING', 'Loading', 'PICKUP', 'Pickup', 2, 70],
            ['LOADING', 'Loading', 'VAN', 'Van', 2, 80],
            ['LOADING', 'Loading', 'TRUCK', 'Truck', 0, 90],
            ['AMBULANCE', 'Ambulance', 'AMBULANCE', 'Ambulance', 2, 100],
        ];
        foreach ($rows as [$familyKey, $familyLabel, $key, $label, $seats, $sort]) {
            $this->db()->table(self::TABLE)->insert([
                'family_key' => $familyKey,
                'family_label' => $familyLabel,
                'key' => $key,
                'label' => $label,
                'seats' => $seats,
                'sort_order' => $sort,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function normalizeKey(string $raw): string
    {
        $key = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $raw) ?: ''));

        return trim($key, '_') ?: 'VEHICLE';
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
