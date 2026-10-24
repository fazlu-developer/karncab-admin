<?php

namespace App\Services;

use App\Models\User;
use App\Support\PublicMedia;
use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeadInfoService
{
    public function ensure(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('head_info')) {
            return;
        }
        $schema->create('head_info', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 32);
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('connect_phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('place_name', 160)->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->timestamps();
            $table->unique(['role', 'state_id', 'district_id'], 'head_info_territory');
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $this->ensure();
        $db = DB::connection('platform');

        return $db->table('head_info')
            ->leftJoin('users', 'users.id', '=', 'head_info.user_id')
            ->leftJoin('states', 'states.id', '=', 'head_info.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'head_info.district_id')
            ->orderByDesc('head_info.id')
            ->get([
                'head_info.*',
                'users.name as head_name',
                'users.phone as head_phone',
                'states.name as state_name',
                'districts.name as district_name',
            ])
            ->map(fn ($row) => $this->present($row))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(User $operator, array $data, ?UploadedFile $photo = null, ?int $id = null): void
    {
        abort_unless($operator->can('platform.admin') || $operator->isSuperAdmin(), 403);
        $this->ensure();
        $db = DB::connection('platform');
        $head = $db->table('users')->where('id', (int) $data['user_id'])->first();
        abort_unless($head && in_array($head->role, ['STATE_HEAD', 'DISTRICT_HEAD'], true), 422, 'Choose a State Head or District Head.');
        $role = (string) $head->role;
        $stateId = (int) ($head->state_id ?? 0);
        $districtId = $role === 'DISTRICT_HEAD' ? (int) ($head->district_id ?? 0) : null;
        abort_unless($stateId > 0, 422, 'This head has no state assigned.');
        if ($role === 'DISTRICT_HEAD') {
            abort_unless($districtId > 0, 422, 'This district head has no district assigned.');
        }
        $existing = $id
            ? $db->table('head_info')->where('id', $id)->first()
            : $db->table('head_info')
                ->where('role', $role)
                ->where('state_id', $stateId)
                ->where('district_id', $districtId)
                ->first();
        $photoUrl = $existing->photo_url ?? null;
        if ($photo) {
            $photoUrl = PublicMedia::storagePath(StoredUpload::replace($photo, 'heads', is_string($photoUrl) ? $photoUrl : null, 'head'));
        } elseif (! empty($data['remove_photo'])) {
            StoredUpload::forget(is_string($photoUrl) ? $photoUrl : null);
            $photoUrl = null;
        }
        $payload = [
            'user_id' => (int) $head->id,
            'role' => $role,
            'state_id' => $stateId,
            'district_id' => $districtId,
            'support_phone' => $this->blank($data['support_phone'] ?? null),
            'email' => $this->blank($data['email'] ?? null),
            'connect_phone' => $this->blank($data['connect_phone'] ?? null),
            'address' => $this->blank($data['address'] ?? null),
            'lat' => $this->coord($data['lat'] ?? null),
            'lng' => $this->coord($data['lng'] ?? null),
            'place_name' => $this->blank($data['place_name'] ?? null),
            'photo_url' => $photoUrl,
            'updated_at' => now(),
        ];
        if ($existing) {
            $db->table('head_info')->where('id', $existing->id)->update($payload);

            return;
        }
        $payload['created_at'] = now();
        $db->table('head_info')->insert($payload);
    }

    public function destroy(User $operator, int $id): void
    {
        abort_unless($operator->can('platform.admin') || $operator->isSuperAdmin(), 403);
        $this->ensure();
        $row = DB::connection('platform')->table('head_info')->where('id', $id)->first();
        abort_unless($row, 404);
        StoredUpload::forget($row->photo_url ?? null);
        DB::connection('platform')->table('head_info')->where('id', $id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $name = trim((string) ($row->head_name ?? ''));
        $phone = trim((string) ($row->head_phone ?? ''));

        return [
            'id' => (int) $row->id,
            'userId' => (int) ($row->user_id ?? 0),
            'role' => $row->role,
            'roleLabel' => $row->role === 'STATE_HEAD' ? 'State Head' : 'District Head',
            'name' => $name !== '' ? $name : 'Head',
            'phone' => $phone,
            'stateName' => $row->state_name,
            'districtName' => $row->district_name,
            'supportPhone' => $row->support_phone,
            'email' => $row->email,
            'connectPhone' => $row->connect_phone,
            'address' => $row->address,
            'lat' => $row->lat,
            'lng' => $row->lng,
            'placeName' => $row->place_name,
            'photoUrl' => PublicMedia::presentMediaUrl($row->photo_url ?? null, $row->updated_at ?? null),
        ];
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function coord(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }
}
