<?php

namespace Database\Seeders;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            ['email' => 'admin@karnacab.local', 'name' => 'KarnaCab Admin', 'role' => OperatorRole::ADMIN],
            ['email' => 'super@karnacab.local', 'name' => 'KarnaCab Super Admin', 'role' => OperatorRole::SUPER_ADMIN],
            ['email' => 'statehead@karnacab.local', 'name' => 'Bihar State Head', 'role' => OperatorRole::STATE_HEAD],
            ['email' => 'district@karnacab.local', 'name' => 'Patna District Head', 'role' => OperatorRole::DISTRICT_HEAD],
            ['email' => 'fleet@karnacab.local', 'name' => 'Patna Fleet', 'role' => OperatorRole::FLEET_OWNER],
            ['email' => 'franchise@karnacab.local', 'name' => 'Gaya Franchise Applicant', 'role' => OperatorRole::FRANCHISE],
            ['email' => 'corporate@karnacab.local', 'name' => 'Bihar Industries Corp', 'role' => OperatorRole::CORPORATE],
            ['email' => 'ads@karnacab.local', 'name' => 'Patna Hotels Ads', 'role' => OperatorRole::ADVERTISER],
        ];

        foreach ($operators as $row) {
            $values = [
                'name' => $row['name'],
                'password' => 'ChangeMe@123',
                'role' => $row['role'],
                'status' => 'ACTIVE',
            ];
            if ($row['role'] === OperatorRole::STATE_HEAD) {
                try {
                    $stateId = DB::connection('platform')->table('states')->orderBy('id')->value('id');
                    if ($stateId) {
                        $values['state_id'] = $stateId;
                    }
                } catch (\Throwable) {
                    // Platform states table may not exist yet.
                }
            }
            if ($row['role'] === OperatorRole::FLEET_OWNER) {
                try {
                    $fleetId = DB::connection('platform')->table('fleet_owners')->orderBy('id')->value('id');
                    if ($fleetId) {
                        $values['fleet_owner_id'] = $fleetId;
                    }
                } catch (\Throwable) {
                    // Platform fleet_owners table may not exist yet.
                }
            }
            User::query()->updateOrCreate(
                ['email' => $row['email']],
                $values,
            );
        }
    }
}
