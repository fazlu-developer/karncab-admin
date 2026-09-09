<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformCustomerBinder
{
    public function fromWebsiteRequest(Request $request): User
    {
        $this->assertSignature($request);
        $email = strtolower(trim((string) $request->header('X-Karnacab-Customer-Email', '')));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, 'Website booking needs a customer email');
        $name = trim((string) $request->header('X-Karnacab-Customer-Name', 'Website customer')) ?: 'Website customer';
        $phone = preg_replace('/\D+/', '', (string) $request->header('X-Karnacab-Customer-Phone', '')) ?: null;
        $platformId = $this->ensurePlatformUser($email, $name, $phone);

        $actor = new User;
        $actor->forceFill([
            'name' => $name,
            'email' => $email,
            'role' => OperatorRole::CUSTOMER,
            'status' => 'ACTIVE',
            'nest_user_id' => $platformId,
        ]);
        $actor->id = 0;
        $actor->exists = true;

        return $actor;
    }

    public function ensurePlatformUser(string $email, string $name, ?string $phone = null): int
    {
        $existing = DB::connection('platform')->table('users')->where('email', $email)->first();
        if ($existing) {
            $patch = [];
            if ($phone && empty($existing->phone)) {
                $patch['phone'] = $phone;
            }
            if ($patch !== []) {
                $patch['updated_at'] = now();
                DB::connection('platform')->table('users')->where('id', $existing->id)->update($patch);
            }

            return (int) $existing->id;
        }

        return (int) DB::connection('platform')->table('users')->insertGetId([
            'role' => OperatorRole::CUSTOMER,
            'status' => 'ACTIVE',
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => Hash::make(Str::random(32)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function customerId(User $operator): int
    {
        if ($operator->nest_user_id) {
            return (int) $operator->nest_user_id;
        }

        $id = $this->ensurePlatformUser((string) $operator->email, (string) $operator->name, null);
        if ($operator->exists && $operator->getConnectionName() !== 'platform') {
            $operator->nest_user_id = $id;
            $operator->save();
        }

        return $id;
    }

    private function assertSignature(Request $request): void
    {
        $secret = (string) config('karnacab.website_booking_secret');
        abort_unless($secret !== '', 503, 'Website booking secret is not configured');
        $ts = (string) $request->header('X-Karnacab-Website-Timestamp', '');
        $sig = (string) $request->header('X-Karnacab-Website-Signature', '');
        abort_unless(ctype_digit($ts) && abs(time() - (int) $ts) <= 300, 401, 'Website booking signature expired');
        $email = strtolower(trim((string) $request->header('X-Karnacab-Customer-Email', '')));
        $body = (string) $request->getContent();
        $canonical = $ts."\n".strtoupper($request->method())."\n".$request->getPathInfo()."\n".$body."\n".$email;
        $expected = hash_hmac('sha256', $canonical, $secret);
        abort_unless(hash_equals($expected, $sig), 401, 'Website booking signature invalid');
    }
}
