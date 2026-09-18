<?php

namespace App\Platform;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class GeoCatalog
{
    /**
     * @return array{states: list<array{id:int,name:string}>, districts: array<string, list<array{id:int,name:string}>>}
     */
    public static function payload(?User $operator = null): array
    {
        try {
            if (! Schema::connection('platform')->hasTable('states') || ! Schema::connection('platform')->hasTable('districts')) {
                return ['states' => [], 'districts' => []];
            }
            $states = DB::connection('platform')->table('states')->orderBy('name')->get(['id', 'name']);
            $districts = DB::connection('platform')->table('districts')->orderBy('name')->get(['id', 'name', 'state_id']);
            if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
                if ($operator->district_id) {
                    $districts = $districts->where('id', (int) $operator->district_id)->values();
                    $stateIds = $districts->pluck('state_id')->unique()->all();
                    $states = $states->whereIn('id', $stateIds)->values();
                } elseif ($operator->state_id) {
                    $states = $states->where('id', (int) $operator->state_id)->values();
                    $districts = $districts->where('state_id', (int) $operator->state_id)->values();
                }
            }
            $grouped = [];
            foreach ($districts as $row) {
                $grouped[(string) $row->state_id][] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ];
            }

            return [
                'states' => $states->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ])->values()->all(),
                'districts' => $grouped,
            ];
        } catch (Throwable) {
            return ['states' => [], 'districts' => []];
        }
    }

    public static function stateName(?int $id): string
    {
        if (! $id) {
            return '';
        }
        try {
            return (string) (DB::connection('platform')->table('states')->where('id', $id)->value('name') ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    public static function districtName(?int $id): string
    {
        if (! $id) {
            return '';
        }
        try {
            return (string) (DB::connection('platform')->table('districts')->where('id', $id)->value('name') ?? '');
        } catch (Throwable) {
            return '';
        }
    }
}
