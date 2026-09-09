<?php

namespace App\Services;

use App\Models\User;
use App\Platform\NotificationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return NotificationPolicy::catalog();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless($operator->can('platform.admin') || $operator->can('notifications.send'), 403);

        return [
            'catalog' => $this->catalog(),
            'deliveries' => $this->deliveries($operator, $query),
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
            } elseif (! Schema::connection('platform')->hasTable('push_devices') || ! $this->db()->table('push_devices')->where('user_id', $userId)->exists()) {
                $note = 'no_device';
            } else {
                $status = 'skipped';
                $note = 'fcm_unconfigured';
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
            if (! $email) {
                $note = 'no_email';
            } else {
                Log::info('notification.email', ['email' => $email, 'event' => $event, 'title' => $title]);
                $status = 'sent';
                $note = 'email_log';
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

    private function db()
    {
        return DB::connection('platform');
    }
}
