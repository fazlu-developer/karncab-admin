<?php

namespace App\Services;

use App\Models\User;
use App\Platform\NotificationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $catalog = NotificationPolicy::catalog();
        foreach ($this->savedTemplates() as $event => $row) {
            $channels = json_decode((string) $row->channels, true);
            $catalog['templates'][$event] = [
                'title' => $row->title,
                'body' => $row->body,
                'channels' => is_array($channels) && $channels !== [] ? array_values($channels) : ['in_app', 'push'],
            ];
        }

        return $catalog;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTemplate(array $data): void
    {
        $this->ensureTemplates();
        $event = (string) $data['event'];
        abort_unless(isset(NotificationPolicy::EVENTS[$event]), 422, 'Unknown event');
        $channels = array_values(array_intersect((array) ($data['channels'] ?? []), NotificationPolicy::CHANNELS));
        abort_unless($channels !== [], 422, 'Pick at least one channel');
        $now = now();
        $payload = [
            'event_key' => $event,
            'title' => $data['title'],
            'body' => $data['body'],
            'channels' => json_encode($channels),
            'updated_at' => $now,
        ];
        $exists = $this->db()->table('notification_event_templates')->where('event_key', $event)->exists();
        if ($exists) {
            $this->db()->table('notification_event_templates')->where('event_key', $event)->update($payload);
        } else {
            $payload['created_at'] = $now;
            $this->db()->table('notification_event_templates')->insert($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless(
            $operator->can('platform.admin')
            || $operator->can('notifications.send')
            || $operator->can('notifications.view'),
            403
        );

        $expired = [];
        try {
            $expired = $this->db()->table('driver_documents as dd')
                ->join('drivers as d', 'd.id', '=', 'dd.driver_id')
                ->join('users as u', 'u.id', '=', 'd.user_id')
                ->where(function ($q) {
                    $q->where('dd.status', 'expired')
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('dd.expires_at')->whereDate('dd.expires_at', '<=', now()->toDateString());
                        });
                })
                ->orderByDesc('dd.id')
                ->limit(40)
                ->get(['dd.id', 'dd.type', 'dd.expires_at', 'dd.status', 'u.name', 'u.phone', 'd.id as driver_id'])
                ->map(fn ($row) => [
                    'driverId' => $row->driver_id,
                    'driver' => $row->name,
                    'phone' => $row->phone,
                    'type' => $row->type,
                    'status' => $row->status,
                    'expiresAt' => $row->expires_at,
                ])
                ->all();
        } catch (\Throwable) {
            $expired = [];
        }

        return [
            'catalog' => $this->catalog(),
            'deliveries' => $this->deliveries($operator, $query),
            'expiredDocuments' => $expired,
        ];
    }

    /**
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $opts
     * @return array<string, mixed>
     */
    public function dispatch(?int $userId, string $event, array $vars = [], array $opts = []): array
    {
        $rendered = NotificationPolicy::render($event, $vars + [
            'title' => $opts['title'] ?? ($vars['title'] ?? ''),
            'body' => $opts['body'] ?? ($vars['body'] ?? ''),
        ]);
        $saved = $this->savedTemplates()[$event] ?? null;
        if ($saved) {
            $channels = json_decode((string) $saved->channels, true);
            $rendered = [
                'title' => NotificationPolicy::fill((string) $saved->title, $vars),
                'body' => NotificationPolicy::fill((string) $saved->body, $vars),
                'channels' => is_array($channels) && $channels !== [] ? array_values($channels) : $rendered['channels'],
            ];
        }
        $title = substr((string) ($opts['title'] ?? $rendered['title'] ?: $event), 0, 160);
        $body = substr((string) ($opts['body'] ?? $rendered['body'] ?: $title), 0, 500);
        $channels = $opts['channels'] ?? $rendered['channels'];
        $user = $userId ? $this->db()->table('users')->where('id', $userId)->first() : null;
        if (! $user && ! empty($opts['phone'])) {
            $user = $this->db()->table('users')->where('phone', $opts['phone'])->first();
            $userId = $user->id ?? $userId;
        }
        $phone = $opts['phone'] ?? ($user->phone ?? null);
        $email = $opts['email'] ?? ($user->email ?? null);
        $entity = $opts['entity'] ?? null;
        $deliveries = [];
        foreach ($channels as $channel) {
            if (! in_array($channel, NotificationPolicy::CHANNELS, true)) {
                continue;
            }
            $deliveries[] = $this->sendChannel($channel, $event, $userId, $phone, $email, $title, $body, $entity);
        }

        return ['event' => $event, 'userId' => $userId, 'title' => $title, 'body' => $body, 'deliveries' => $deliveries];
    }

    /**
     * @param  list<int>  $userIds
     */
    public function broadcast(array $userIds, string $title, string $body, string $event = 'announcement'): int
    {
        foreach ($userIds as $id) {
            $this->dispatch((int) $id, $event, ['title' => $title, 'body' => $body], ['title' => $title, 'body' => $body]);
        }

        return count($userIds);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inbox(User $operator): array
    {
        $userId = (int) ($operator->nest_user_id ?: $operator->id);
        if (! Schema::connection('platform')->hasTable('user_notifications')) {
            return [];
        }

        return $this->db()->table('user_notifications')->where('user_id', $userId)->orderByDesc('id')->limit(80)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'kind' => $row->kind,
                'event' => $row->kind,
                'read' => ! empty($row->read_at),
                'createdAt' => $row->created_at,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function deliveries(User $operator, array $query = []): array
    {
        abort_unless($operator->can('platform.admin') || $operator->can('notifications.send'), 403);
        if (! Schema::connection('platform')->hasTable('notification_deliveries')) {
            return [];
        }
        $q = $this->db()->table('notification_deliveries');
        if (! empty($query['event'])) {
            $q->where('event', $query['event']);
        }
        if (! empty($query['channel'])) {
            $q->where('channel', $query['channel']);
        }
        if (! $operator->isPrivilegedOperator() && $operator->district_id) {
            $ids = $this->db()->table('users');
            TerritoryScope::applyUsers($ids, $operator);
            $q->whereIn('user_id', $ids->pluck('id'));
        }

        return $q->orderByDesc('id')->limit(120)->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'userId' => $row->user_id ? (int) $row->user_id : null,
            'event' => $row->event,
            'channel' => $row->channel,
            'title' => $row->title,
            'body' => $row->body,
            'status' => $row->status,
            'note' => $row->provider_note,
            'createdAt' => $row->created_at,
        ])->all();
    }

    /**
     * @param  array<string, mixed>|null  $entity
     * @return array{channel: string, status: string}
     */
    private function sendChannel(string $channel, string $event, ?int $userId, ?string $phone, ?string $email, string $title, string $body, ?array $entity): array
    {
        $status = 'skipped';
        $note = null;
        $logBody = $event === 'otp' ? 'OTP dispatched' : $body;
        if ($channel === 'in_app') {
            if ($userId && $event !== 'otp' && Schema::connection('platform')->hasTable('user_notifications')) {
                $row = [
                    'user_id' => $userId,
                    'title' => $title,
                    'body' => $body,
                    'kind' => substr($event, 0, 48),
                    'created_at' => now(),
                ];
                if (Schema::connection('platform')->hasColumn('user_notifications', 'entity_type') && $entity) {
                    $row['entity_type'] = $entity['type'] ?? null;
                    $row['entity_id'] = $entity['id'] ?? null;
                }
                $this->db()->table('user_notifications')->insert($row);
                $status = 'sent';
            } else {
                $note = $event === 'otp' ? 'otp_not_in_app' : 'no_user';
            }
        } elseif ($channel === 'push') {
            if (! $userId) {
                $note = 'no_user';
            } else {
                $sent = $this->sendPush($userId, $title, $body, ['event' => $event]);
                $status = $sent ? 'sent' : 'skipped';
                $note = $sent ? 'fcm' : 'no_device_or_fcm';
            }
        } elseif ($channel === 'sms') {
            if (! $phone) {
                $note = 'no_phone';
            } else {
                Log::info('notification.sms', ['phone' => $phone, 'event' => $event]);
                $status = 'sent';
                $note = 'log_adapter';
            }
        } elseif ($channel === 'email') {
            if (! $email || str_ends_with((string) $email, '@otp.karnacab.local')) {
                $note = 'no_email';
            } else {
                try {
                    Mail::html($this->brandedHtml($title, $body), function ($message) use ($email, $title) {
                        $message->to($email)->subject($title);
                    });
                    $status = 'sent';
                    $note = 'smtp';
                } catch (\Throwable $e) {
                    $status = 'failed';
                    $note = substr($e->getMessage(), 0, 180);
                    Log::warning('notification.email_failed', ['email' => $email, 'error' => $e->getMessage()]);
                }
            }
        }
        if (Schema::connection('platform')->hasTable('notification_deliveries')) {
            $this->db()->table('notification_deliveries')->insert([
                'user_id' => $userId,
                'event' => $event,
                'channel' => $channel,
                'title' => $title,
                'body' => $logBody,
                'status' => $status,
                'provider_note' => $note,
                'entity_type' => $entity['type'] ?? null,
                'entity_id' => $entity['id'] ?? null,
                'created_at' => now(),
            ]);
        }

        return ['channel' => $channel, 'status' => $status];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendPush(int $userId, string $title, string $body, array $data): bool
    {
        if (! Schema::connection('platform')->hasTable('push_devices')) {
            return false;
        }
        $tokens = $this->db()->table('push_devices')->where('user_id', $userId)->pluck('token');
        $creds = $this->firebaseCredentials();
        if (! $creds || $tokens->isEmpty()) {
            return false;
        }
        $access = $this->firebaseAccessToken($creds);
        if (! $access) {
            return false;
        }
        $project = $creds['project_id'] ?? 'karnacab-bf930';
        $ok = false;
        foreach ($tokens as $token) {
            if (! is_string($token) || strlen($token) < 20) {
                continue;
            }
            $payload = [];
            foreach ($data + ['title' => $title, 'body' => $body] as $key => $value) {
                if ($value !== null) {
                    $payload[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
                }
            }
            $response = Http::withToken($access)->acceptJson()->timeout(8)->post(
                'https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send',
                ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $payload,
                    'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'karnacab_default']],
                ]],
            );
            $ok = $ok || $response->successful();
        }

        return $ok;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firebaseCredentials(): ?array
    {
        $path = env('FCM_CREDENTIALS', base_path('../api/firebase-service-account.json'));
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $creds
     */
    private function firebaseAccessToken(array $creds): ?string
    {
        $header = $this->b64(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claim = $this->b64([
            'iss' => $creds['client_email'],
            'sub' => $creds['client_email'],
            'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3500,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]);
        $private = openssl_pkey_get_private((string) $creds['private_key']);
        if (! $private || ! openssl_sign($header.'.'.$claim, $signature, $private, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = $header.'.'.$claim.'.'.$this->b64Raw($signature);
        try {
            $response = Http::asForm()->timeout(8)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return is_string($response->json('access_token')) ? $response->json('access_token') : null;
        } catch (\Throwable $e) {
            Log::warning('notification.fcm_token_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function b64(array $data): string
    {
        return $this->b64Raw((string) json_encode($data));
    }

    private function b64Raw(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function brandedHtml(string $title, string $body): string
    {
        $safeTitle = e($title);
        $safeBody = e($body);

        return <<<HTML
<!DOCTYPE html>
<html><body style="margin:0;background:#f4f1ea;font-family:Segoe UI,Arial,sans-serif;color:#10231c">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px">
    <tr><td align="center">
      <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#fff;border-radius:20px;overflow:hidden;border:1px solid #eadfcb">
        <tr><td style="background:#123328;color:#fff;padding:22px 28px;font-size:22px;font-weight:800">Karna<span style="color:#f5a623">Cab</span></td></tr>
        <tr><td style="padding:28px"><h1 style="margin:0 0 12px;font-size:24px">{$safeTitle}</h1><p style="margin:0;font-size:15px;line-height:1.6">{$safeBody}</p></td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }

    /**
     * @return array<string, object>
     */
    private function savedTemplates(): array
    {
        $this->ensureTemplates();
        $rows = [];
        foreach ($this->db()->table('notification_event_templates')->get() as $row) {
            $rows[(string) $row->event_key] = $row;
        }

        return $rows;
    }

    private function ensureTemplates(): void
    {
        if (Schema::connection('platform')->hasTable('notification_event_templates')) {
            return;
        }
        Schema::connection('platform')->create('notification_event_templates', function ($table) {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('title', 180);
            $table->text('body');
            $table->json('channels');
            $table->timestamps();
        });
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
