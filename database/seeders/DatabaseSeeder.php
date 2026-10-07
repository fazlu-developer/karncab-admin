<?php

namespace Database\Seeders;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->truncateBookings();

        $platform = DB::connection('platform');
        $biharId = $this->stateId($platform, 'Bihar', 'BR');
        $delhiId = $this->stateId($platform, 'Delhi', 'DL');

        $core = [
            ['email' => 'super@karnaride.local', 'name' => 'KarnaRide Super Admin', 'role' => OperatorRole::SUPER_ADMIN],
            ['email' => 'admin@karnaride.local', 'name' => 'KarnaRide Admin', 'role' => OperatorRole::ADMIN],
            ['email' => 'manager@karnaride.local', 'name' => 'KarnaRide Manager', 'role' => OperatorRole::MANAGER],
            ['email' => 'super@karnacab.local', 'name' => 'KarnaRide Super Admin', 'role' => OperatorRole::SUPER_ADMIN],
            ['email' => 'manager@karnacab.local', 'name' => 'KarnaRide Manager', 'role' => OperatorRole::MANAGER],
        ];

        foreach ($core as $row) {
            $this->upsertOperator($row['email'], $row['name'], $row['role']);
        }

        $this->upsertOperator('bihar.head@karnaride.local', 'Bihar State Head', OperatorRole::STATE_HEAD, $biharId);
        $this->upsertOperator('delhi.head@karnaride.local', 'Delhi State Head', OperatorRole::STATE_HEAD, $delhiId);
        $this->upsertOperator('statehead@karnacab.local', 'Bihar State Head', OperatorRole::STATE_HEAD, $biharId);
        $this->upsertOperator('delhihead@karnacab.local', 'Delhi State Head', OperatorRole::STATE_HEAD, $delhiId);

        foreach ([$biharId, $delhiId] as $stateId) {
            if (! $stateId) {
                continue;
            }
            $districts = $platform->table('districts')->where('state_id', $stateId)->orderBy('id')->get();
            foreach ($districts as $district) {
                $slug = Str::slug((string) $district->name, '.');
                $email = $slug.'@karnaride.local';
                $this->upsertOperator(
                    $email,
                    $district->name.' District Head',
                    OperatorRole::DISTRICT_HEAD,
                    (int) $stateId,
                    (int) $district->id,
                );
            }
        }
    }

    private function upsertOperator(string $email, string $name, string $role, ?int $stateId = null, ?int $districtId = null): void
    {
        $values = [
            'name' => $name,
            'password' => 'ChangeMe@123',
            'role' => $role,
            'status' => 'ACTIVE',
            'state_id' => $stateId,
            'district_id' => $districtId,
        ];
        User::query()->updateOrCreate(['email' => $email], $values);

        try {
            $platform = DB::connection('platform');
            if (! Schema::connection('platform')->hasTable('users')) {
                return;
            }
            $row = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'status' => 'ACTIVE',
            ];
            if (Schema::connection('platform')->hasColumn('users', 'password_hash')) {
                $row['password_hash'] = Hash::make('ChangeMe@123');
            }
            if (Schema::connection('platform')->hasColumn('users', 'state_id')) {
                $row['state_id'] = $stateId;
            }
            if (Schema::connection('platform')->hasColumn('users', 'district_id')) {
                $row['district_id'] = $districtId;
            }
            if (Schema::connection('platform')->hasColumn('users', 'updated_at')) {
                $row['updated_at'] = now();
            }
            $existing = $platform->table('users')->where('email', $email)->first();
            if ($existing) {
                $platform->table('users')->where('id', $existing->id)->update($row);
            } else {
                if (Schema::connection('platform')->hasColumn('users', 'created_at')) {
                    $row['created_at'] = now();
                }
                $platform->table('users')->insert($row);
            }
        } catch (\Throwable) {
        }
    }

    private function stateId($platform, string $name, string $code): ?int
    {
        try {
            $id = $platform->table('states')->where('name', 'like', $name.'%')->value('id')
                ?: $platform->table('states')->where('code', $code)->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function truncateBookings(): void
    {
        try {
            $db = DB::connection('platform');
            foreach (['booking_stops', 'booking_ratings', 'booking_offers', 'invoices', 'bookings'] as $table) {
                if (Schema::connection('platform')->hasTable($table)) {
                    $db->table($table)->delete();
                }
            }
        } catch (\Throwable) {
        }
    }
}
