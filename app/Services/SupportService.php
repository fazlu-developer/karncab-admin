<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\SupportPolicy;
use App\Platform\TerritoryScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportService
{
    public function catalog(): array
    {
        return SupportPolicy::catalog();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless($operator->can('safety.view'), 403);

        return [
            'catalog' => $this->catalog(),
            'tickets' => $this->list($operator, $query),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(User $operator, array $query = []): array
    {
        $q = $this->ticketQuery($operator);
        if (! empty($query['status'])) {
            $q->where('support_tickets.status', SupportPolicy::normalize((string) $query['status']));
        }
        if (! empty($query['category'])) {
            $q->where('support_tickets.category', $query['category']);
        }
        if (! empty($query['priority'])) {
            $q->where('support_tickets.priority', $query['priority']);
        }

        return $q->orderByDesc('support_tickets.id')->limit(100)->get()->map(fn ($row) => $this->present($row, $operator, false))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        $row = $this->ticketQuery($operator)->where('support_tickets.id', $id)->first();
        abort_if($row === null, 404, 'Ticket not found');

        return $this->present($row, $operator, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $operator, array $data): array
    {
        $this->assertUser($operator);
        $subject = trim((string) ($data['subject'] ?? ''));
        abort_unless(strlen($subject) >= 4, 400, 'Enter a subject');
        $description = trim((string) ($data['description'] ?? $data['message'] ?? $subject));
        abort_unless(strlen($description) >= 4, 400, 'Enter a description');
        $category = (string) ($data['category'] ?? 'other');
        abort_unless(isset(SupportPolicy::CATEGORIES[$category]), 422, 'Unknown category');
        $priority = (string) ($data['priority'] ?? 'medium');
        abort_unless(in_array($priority, SupportPolicy::PRIORITIES, true), 422);
        $kind = (string) ($data['kind'] ?? 'support');
        abort_unless(in_array($kind, SupportPolicy::KINDS, true), 422);
        $booking = $this->ownedBooking($operator, isset($data['booking_id']) ? (int) $data['booking_id'] : null);
        $userId = $this->actorId($operator);
        $publicRef = 'ST'.strtoupper(Str::random(10));
        $id = $this->db()->table('support_tickets')->insertGetId($this->ticketInsert([
            'public_ref' => $publicRef,
            'user_id' => $userId,
            'kind' => $kind,
            'status' => 'open',
            'subject' => $subject,
            'description' => substr($description, 0, 2000),
            'category' => $category,
            'priority' => $priority,
            'booking_id' => $booking->id ?? null,
            'district_id' => $operator->district_id ?: ($booking->district_id ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->addMessageRow($id, $userId, $description, false);
        $this->event($id, $userId, 'created', null, 'open', $subject);
        app(NotificationService::class)->dispatch($userId, 'support_update', [
            'ref' => $publicRef,
            'status' => 'open',
        ], ['entity' => ['type' => 'ticket', 'id' => (string) $id]]);
        if (! empty($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $this->storeUpload($operator, $id, $data['attachment']);
        }

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function assign(User $operator, int $id, ?int $agentId = null): array
    {
        abort_unless($operator->can('safety.edit'), 403);
        $ticket = $this->requireTicket($operator, $id);
        $status = SupportPolicy::normalize((string) $ticket->status);
        $agent = $agentId ?: $this->actorId($operator);
        $patch = ['assigned_agent_id' => $agent, 'updated_at' => now()];
        $next = $status;
        if ($status === 'open') {
            abort_unless(SupportPolicy::canAdvance('open', 'assigned'), 400);
            $patch['status'] = 'assigned';
            $next = 'assigned';
        }
        $this->db()->table('support_tickets')->where('id', $id)->update($patch);
        $this->event($id, $this->actorId($operator), 'assigned', $status, $next, 'Agent '.$agent);

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transition(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('safety.edit'), 403);
        $ticket = $this->requireTicket($operator, $id);
        $from = SupportPolicy::normalize((string) $ticket->status);
        $to = SupportPolicy::normalize((string) $data['status']);
        abort_unless(SupportPolicy::canAdvance($from, $to), 400, 'Next status must follow Open → Assigned → In Progress → Resolved → Closed');
        $patch = ['status' => $to, 'updated_at' => now()];
        if ($to === 'assigned' && empty($ticket->assigned_agent_id)) {
            $patch['assigned_agent_id'] = $this->actorId($operator);
        }
        if ($to === 'resolved') {
            $resolution = trim((string) ($data['resolution'] ?? $data['message'] ?? ''));
            abort_unless($resolution !== '', 400, 'Record a resolution before resolving');
            $patch['resolution'] = substr($resolution, 0, 2000);
            $patch['resolved_at'] = now();
            $this->addMessageRow($id, $this->actorId($operator), $resolution, true);
        }
        if ($to === 'closed') {
            $patch['closed_at'] = now();
            if (empty($ticket->resolved_at) && Schema::connection('platform')->hasColumn('support_tickets', 'resolved_at')) {
                $patch['resolved_at'] = $ticket->resolved_at ?? now();
            }
        }
        $this->db()->table('support_tickets')->where('id', $id)->update($patch);
        $this->event($id, $this->actorId($operator), 'status', $from, $to, $data['resolution'] ?? $data['message'] ?? null);
        app(NotificationService::class)->dispatch((int) $ticket->user_id, 'support_update', [
            'ref' => $ticket->public_ref,
            'status' => $to,
        ], ['entity' => ['type' => 'ticket', 'id' => (string) $id]]);

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function reply(User $operator, int $id, string $body, bool $staff = false): array
    {
        $ticket = $this->requireTicket($operator, $id);
        $asStaff = $staff || $operator->can('safety.edit');
        if ($staff) {
            abort_unless($operator->can('safety.edit'), 403);
        }
        $status = SupportPolicy::normalize((string) $ticket->status);
        abort_if(in_array($status, ['resolved', 'closed'], true) && ! $asStaff, 400, 'This ticket is already resolved');
        $this->addMessageRow($id, $this->actorId($operator), trim($body), $asStaff);
        $this->event($id, $this->actorId($operator), 'message', $status, $status, $asStaff ? 'staff' : 'user');
        if ($asStaff && $status === 'assigned') {
            $this->db()->table('support_tickets')->where('id', $id)->update(['status' => 'in_progress', 'updated_at' => now()]);
            $this->event($id, $this->actorId($operator), 'status', 'assigned', 'in_progress', 'Reply');
        }
        if ($asStaff && $status === 'open') {
            $this->assign($operator, $id);
        }

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function attach(User $operator, int $id, UploadedFile $file): array
    {
        $this->requireTicket($operator, $id);

        return $this->storeUpload($operator, $id, $file);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attachBase64(User $operator, int $id, array $data): array
    {
        $this->requireTicket($operator, $id);
        $mime = (string) ($data['mime'] ?? '');
        abort_unless(in_array($mime, SupportPolicy::MIMES, true), 400, 'Attach a JPEG, PNG, WebP, or PDF');
        $raw = str_contains((string) $data['fileBase64'], ',') ? explode(',', (string) $data['fileBase64'], 2)[1] : (string) $data['fileBase64'];
        $buffer = base64_decode($raw, true);
        abort_if($buffer === false || $buffer === '', 400, 'Invalid attachment');
        abort_if(strlen($buffer) > 2 * 1024 * 1024, 400, 'Attachment must be under 2 MB');
        $ext = $mime === 'application/pdf' ? 'pdf' : (explode('/', $mime)[1] ?? 'bin');
        $name = (string) ($data['fileName'] ?? 'attachment.'.$ext);

        return $this->writeFile($operator, $id, $buffer, $mime, $name, $ext);
    }

    /**
     * @return array{bytes: string, mime: string, name: string}
     */
    public function attachmentFile(User $operator, int $ticketId, int $attachmentId): array
    {
        $this->requireTicket($operator, $ticketId);
        $row = $this->db()->table('support_attachments')->where('id', $attachmentId)->where('ticket_id', $ticketId)->first();
        abort_if($row === null, 404, 'Attachment not found');
        $path = storage_path('app/'.$row->storage_key);
        abort_unless(is_file($path), 404, 'Attachment file missing');

        return ['bytes' => file_get_contents($path), 'mime' => $row->mime, 'name' => $row->original_name];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row, User $operator, bool $detail): array
    {
        $staff = $operator->can('safety.view') && ! in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER], true);
        $status = SupportPolicy::normalize((string) $row->status);
        $view = [
            'id' => (int) $row->id,
            'ticketId' => $row->public_ref,
            'publicRef' => $row->public_ref,
            'user' => [
                'id' => (int) $row->user_id,
                'name' => $row->user_name ?? null,
            ],
            'booking' => $row->booking_id ? ['id' => (int) $row->booking_id, 'publicRef' => $row->booking_ref] : null,
            'bookingId' => $row->booking_id ? (int) $row->booking_id : null,
            'category' => $row->category ?? 'other',
            'kind' => $row->kind,
            'subject' => $row->subject,
            'description' => $row->description ?? $row->subject,
            'priority' => $row->priority ?? 'medium',
            'status' => $status,
            'assignedAgent' => $row->assigned_agent_id ? [
                'id' => (int) $row->assigned_agent_id,
                'name' => $row->agent_name ?? null,
            ] : null,
            'resolution' => $staff || $status === 'resolved' || $status === 'closed' ? ($row->resolution ?? null) : null,
            'createdAt' => $row->created_at,
            'closedAt' => $row->closed_at ?? null,
            'resolvedAt' => $row->resolved_at ?? null,
        ];
        if (! $detail) {
            return $view;
        }
        $view['messages'] = $this->messages((int) $row->id);
        $view['attachments'] = $this->attachments((int) $row->id);
        $view['history'] = $this->history((int) $row->id, $view['messages'], $view['attachments']);
        $view['nextStatuses'] = $staff ? (SupportPolicy::transitions()[$status] ?? []) : [];

        return $view;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(int $ticketId): array
    {
        if (! Schema::connection('platform')->hasTable('support_messages')) {
            return [];
        }

        return $this->db()->table('support_messages')->where('ticket_id', $ticketId)->orderBy('id')->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'fromStaff' => (bool) $row->from_staff,
                'body' => $row->body,
                'createdAt' => $row->created_at,
                'kind' => 'message',
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachments(int $ticketId): array
    {
        if (! Schema::connection('platform')->hasTable('support_attachments')) {
            return [];
        }

        return $this->db()->table('support_attachments')->where('ticket_id', $ticketId)->orderBy('id')->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->original_name,
                'mime' => $row->mime,
                'bytes' => (int) $row->bytes,
                'createdAt' => $row->created_at,
                'kind' => 'attachment',
            ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $attachments
     * @return list<array<string, mixed>>
     */
    private function history(int $ticketId, array $messages, array $attachments): array
    {
        $events = [];
        if (Schema::connection('platform')->hasTable('support_ticket_events')) {
            $events = $this->db()->table('support_ticket_events')->where('ticket_id', $ticketId)->orderBy('id')->get()
                ->map(fn ($row) => [
                    'id' => 'e'.(int) $row->id,
                    'kind' => 'event',
                    'action' => $row->action,
                    'fromStatus' => $row->from_status,
                    'toStatus' => $row->to_status,
                    'note' => $row->note,
                    'createdAt' => $row->created_at,
                ])->all();
        }
        $all = array_merge($events, $messages, $attachments);
        usort($all, fn ($a, $b) => strcmp((string) ($a['createdAt'] ?? ''), (string) ($b['createdAt'] ?? '')));

        return $all;
    }

    private function ticketQuery(User $operator)
    {
        $q = $this->db()->table('support_tickets')
            ->leftJoin('users as ticket_users', 'ticket_users.id', '=', 'support_tickets.user_id')
            ->leftJoin('users as agents', 'agents.id', '=', 'support_tickets.assigned_agent_id')
            ->leftJoin('bookings', 'bookings.id', '=', 'support_tickets.booking_id')
            ->select(
                'support_tickets.*',
                'ticket_users.name as user_name',
                'agents.name as agent_name',
                'bookings.public_ref as booking_ref',
            );
        if (in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER], true)) {
            return $q->where('support_tickets.user_id', $this->actorId($operator));
        }
        abort_unless($operator->can('safety.view'), 403);
        TerritoryScope::applySafetyIncidents($q, $operator, 'support_tickets');

        return $q;
    }

    private function requireTicket(User $operator, int $id): object
    {
        $row = $this->ticketQuery($operator)->where('support_tickets.id', $id)->first();
        abort_if($row === null, 404, 'Ticket not found');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function ticketInsert(array $row): array
    {
        $allowed = [
            'public_ref', 'user_id', 'kind', 'status', 'subject', 'booking_id', 'district_id',
            'created_at', 'updated_at',
        ];
        foreach (['category', 'description', 'priority', 'assigned_agent_id', 'resolution', 'closed_at', 'resolved_at'] as $col) {
            if (Schema::connection('platform')->hasColumn('support_tickets', $col)) {
                $allowed[] = $col;
            }
        }

        return array_intersect_key($row, array_flip($allowed));
    }

    private function addMessageRow(int $ticketId, int $authorId, string $body, bool $staff): void
    {
        if (! Schema::connection('platform')->hasTable('support_messages')) {
            return;
        }
        $this->db()->table('support_messages')->insert([
            'ticket_id' => $ticketId,
            'author_id' => $authorId,
            'from_staff' => $staff,
            'body' => substr($body, 0, 2000),
            'created_at' => now(),
        ]);
    }

    private function event(int $ticketId, ?int $actorId, string $action, ?string $from, ?string $to, ?string $note): void
    {
        if (! Schema::connection('platform')->hasTable('support_ticket_events')) {
            return;
        }
        $this->db()->table('support_ticket_events')->insert([
            'ticket_id' => $ticketId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note ? substr($note, 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    private function storeUpload(User $operator, int $ticketId, UploadedFile $file): array
    {
        abort_unless(in_array($file->getMimeType(), SupportPolicy::MIMES, true), 400, 'Attach a JPEG, PNG, WebP, or PDF');
        abort_if($file->getSize() > 2 * 1024 * 1024, 400, 'Attachment must be under 2 MB');
        $ext = $file->getClientOriginalExtension() ?: 'bin';

        return $this->writeFile($operator, $ticketId, file_get_contents($file->getRealPath()), $file->getMimeType(), $file->getClientOriginalName(), $ext);
    }

    /**
     * @return array<string, mixed>
     */
    private function writeFile(User $operator, int $ticketId, string $buffer, string $mime, string $name, string $ext): array
    {
        $key = 'support/'.$ticketId.'/'.Str::uuid().'.'.$ext;
        $full = storage_path('app/'.$key);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $buffer);
        $id = $this->db()->table('support_attachments')->insertGetId([
            'ticket_id' => $ticketId,
            'storage_key' => $key,
            'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
            'original_name' => substr($name, 0, 160),
            'bytes' => strlen($buffer),
            'created_at' => now(),
        ]);
        $this->event($ticketId, $this->actorId($operator), 'attachment', null, null, $name);

        return ['id' => $id, 'name' => substr($name, 0, 160), 'mime' => $mime, 'bytes' => strlen($buffer)];
    }

    private function ownedBooking(User $operator, ?int $bookingId): ?object
    {
        if (! $bookingId) {
            return null;
        }
        $row = $this->db()->table('bookings')->where('id', $bookingId)->first();
        abort_if($row === null, 404, 'Booking not found');
        $owns = (int) $row->customer_id === $this->actorId($operator);
        $driverId = $this->db()->table('drivers')->where('user_id', $this->actorId($operator))->value('id');
        if ($driverId && (int) $row->driver_id === (int) $driverId) {
            $owns = true;
        }
        abort_unless($owns || $operator->can('safety.edit'), 403, 'You can only link your own booking');

        return $row;
    }

    private function assertUser(User $operator): void
    {
        abort_unless(in_array($operator->role, [
            OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER,
        ], true), 403, 'Customer or driver account required');
    }

    private function actorId(User $operator): int
    {
        return (int) ($operator->nest_user_id ?: $operator->id);
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
