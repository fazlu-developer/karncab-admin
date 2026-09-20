<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class LeadService
{
    public const STATUSES = ['NEW', 'IN_PROGRESS', 'CLOSED'];

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless($operator->can('customers.view') || $operator->can('platform.admin'), 403);

        return [
            'leads' => $this->rows($query),
            'catalog' => [
                'statuses' => self::STATUSES,
                'types' => $this->types(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        abort_unless($operator->can('customers.view') || $operator->can('platform.admin'), 403);
        $row = $this->table()->where('id', $id)->first();
        abort_if($row === null, 404);

        return $this->map($row);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(User $operator, int $id, array $data): void
    {
        abort_unless($operator->can('customers.view') || $operator->can('platform.admin'), 403);
        $status = strtoupper((string) ($data['status'] ?? ''));
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown lead status');
        }
        $updated = $this->table()->where('id', $id)->update(['status' => $status]);
        abort_if($updated === 0, 404);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function rows(array $query): array
    {
        if (! Schema::connection('platform')->hasTable('leads')) {
            return [];
        }
        $q = $this->table()->orderByDesc('id')->limit(200);
        if (! empty($query['status'])) {
            $q->where('status', $query['status']);
        }
        if (! empty($query['type'])) {
            $q->where('type', $query['type']);
        }
        if (! empty($query['q'])) {
            $term = '%'.$query['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        return $q->get()->map(fn ($row) => $this->map($row))->all();
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        if (! Schema::connection('platform')->hasTable('leads')) {
            return [];
        }

        return $this->table()->distinct()->orderBy('type')->pluck('type')->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function map(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'type' => $row->type,
            'status' => $row->status,
            'name' => $row->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'district' => $row->district,
            'message' => $row->message,
            'payload' => $row->payload,
            'createdAt' => $row->created_at,
        ];
    }

    private function table()
    {
        return DB::connection('platform')->table('leads');
    }
}
