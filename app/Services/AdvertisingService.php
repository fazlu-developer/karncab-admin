<?php

namespace App\Services;

use App\Models\User;
use App\Platform\AdPolicy;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdvertisingService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return AdPolicy::catalog();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        $campaigns = $this->list($operator, $query);
        $totals = [
            'impressions' => 0,
            'clicks' => 0,
            'revenuePaise' => 0,
        ];
        foreach ($campaigns as $row) {
            $totals['impressions'] += $row['impressions'];
            $totals['clicks'] += $row['clicks'];
            $totals['revenuePaise'] += $row['revenuePaise'];
        }

        return [
            'catalog' => $this->catalog(),
            'campaigns' => $campaigns,
            'queue' => array_values(array_filter($campaigns, fn (array $row) => $row['status'] === 'pending')),
            'totals' => $totals,
            'states' => $this->states(),
            'districts' => $this->districts(),
            'query' => $query,
            'canReview' => $operator->isPrivilegedOperator(),
        ];
    }

    /**
     * @return list<object>
     */
    public function states(): array
    {
        return $this->db()->table('states')->orderBy('name')->get(['id', 'name'])->all();
    }

    /**
     * @return list<object>
     */
    public function districts(): array
    {
        return $this->db()->table('districts')->orderBy('name')->get(['id', 'state_id', 'name'])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(User $operator, array $query = []): array
    {
        abort_unless($operator->can('advertising.view'), 403);
        $q = $this->scoped($operator);
        if (! empty($query['status'])) {
            $q->where('ad_campaigns.status', $query['status']);
        }
        if (! empty($query['category'])) {
            $q->where('ad_campaigns.category', $query['category']);
        }
        if (! empty($query['q'])) {
            $term = '%'.$query['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('ad_campaigns.title', 'like', $term)
                    ->orWhere('ad_campaigns.business_name', 'like', $term);
            });
        }

        return $this->map($q->orderByDesc('ad_campaigns.id')->limit(200)->get());
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        return $this->present($this->load($operator, $id), true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $operator, array $data): array
    {
        abort_unless($operator->can('advertising.edit'), 403);
        abort_unless(
            $operator->role === OperatorRole::ADVERTISER || $operator->isPrivilegedOperator(),
            403,
            'Advertiser account required',
        );
        $this->assertWindow($data['starts_on'], $data['ends_on']);
        $ids = $this->assertTerritory($data['state_id'] ?? null, $data['district_id'] ?? null);
        $now = now();
        $id = $this->db()->table('ad_campaigns')->insertGetId([
            'advertiser_user_id' => $this->actorId($operator),
            'business_name' => trim((string) $data['business_name']),
            'business_info' => isset($data['business_info']) ? trim((string) $data['business_info']) : null,
            'title' => trim((string) $data['title']),
            'category' => $data['category'],
            'campaign_type' => $data['campaign_type'] ?? 'banner',
            'target_city' => isset($data['target_city']) ? (trim((string) $data['target_city']) ?: null) : null,
            'state_id' => $ids['state_id'],
            'district_id' => $ids['district_id'],
            'starts_on' => Carbon::parse($data['starts_on']),
            'ends_on' => Carbon::parse($data['ends_on']),
            'budget_paise' => $this->budgetPaise($data),
            'budget_used_paise' => 0,
            'status' => 'pending',
            'impressions' => 0,
            'clicks' => 0,
            'cta_url' => isset($data['cta_url']) ? (trim((string) $data['cta_url']) ?: null) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('advertising.edit'), 403);
        $row = $this->load($operator, $id);
        if ($row->status === 'published' && ! $operator->isPrivilegedOperator()) {
            abort(400, 'Published campaigns must be paused before editing');
        }
        if (! $this->owns($operator, $row) && ! $operator->isPrivilegedOperator()) {
            abort(403, 'Cannot edit this campaign');
        }
        $starts = $data['starts_on'] ?? $row->starts_on;
        $ends = $data['ends_on'] ?? $row->ends_on;
        $this->assertWindow($starts, $ends);
        $stateId = array_key_exists('state_id', $data) ? $data['state_id'] : $row->state_id;
        $districtId = array_key_exists('district_id', $data) ? $data['district_id'] : $row->district_id;
        $ids = $this->assertTerritory($stateId, $districtId);
        $patch = [
            'updated_at' => now(),
        ];
        foreach ([
            'business_name' => fn ($v) => trim((string) $v),
            'business_info' => fn ($v) => $v === null || $v === '' ? null : trim((string) $v),
            'title' => fn ($v) => trim((string) $v),
            'category' => fn ($v) => $v,
            'campaign_type' => fn ($v) => $v,
            'target_city' => fn ($v) => $v === null || $v === '' ? null : trim((string) $v),
            'cta_url' => fn ($v) => $v === null || $v === '' ? null : trim((string) $v),
        ] as $field => $cast) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $cast($data[$field]);
            }
        }
        $patch['state_id'] = $ids['state_id'];
        $patch['district_id'] = $ids['district_id'];
        if (isset($data['starts_on'])) {
            $patch['starts_on'] = Carbon::parse($data['starts_on']);
        }
        if (isset($data['ends_on'])) {
            $patch['ends_on'] = Carbon::parse($data['ends_on']);
        }
        if (isset($data['budget_rupees']) || isset($data['budget_paise'])) {
            $patch['budget_paise'] = $this->budgetPaise($data);
        }
        if ($operator->isPrivilegedOperator() && isset($data['status']) && in_array($data['status'], AdPolicy::STATUSES, true)) {
            $patch['status'] = $data['status'];
        }
        $this->db()->table('ad_campaigns')->where('id', $id)->update($patch);

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadBanner(User $operator, int $id, UploadedFile $file): array
    {
        abort_unless($operator->can('advertising.edit'), 403);
        $row = $this->load($operator, $id);
        if (! $this->owns($operator, $row) && ! $operator->isPrivilegedOperator()) {
            abort(403, 'Cannot upload a banner for this campaign');
        }
        $mime = strtolower((string) $file->getMimeType());
        abort_unless(in_array($mime, AdPolicy::BANNER_MIMES, true), 400, 'Upload a JPEG, PNG, or WebP banner');
        abort_unless($file->getSize() >= 32 && $file->getSize() <= 2 * 1024 * 1024, 400, 'Banner must be between 32 bytes and 2 MB');
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $dir = storage_path('app/ads');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $key = 'ads/'.$id.'.'.$ext;
        $file->move($dir, $id.'.'.$ext);
        $this->db()->table('ad_campaigns')->where('id', $id)->update([
            'banner_key' => $key,
            'banner_mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
            'updated_at' => now(),
        ]);

        return $this->one($operator, $id);
    }

    public function bannerFile(User $operator, int $id): array
    {
        $row = $this->load($operator, $id, true);
        abort_unless($row->banner_key, 404, 'Banner not found');
        $path = storage_path('app/'.$row->banner_key);
        abort_unless(is_file($path), 404, 'Banner not found');

        return [
            'path' => $path,
            'mime' => $row->banner_mime ?: 'image/jpeg',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function review(User $operator, int $id, array $data): array
    {
        $this->assertAdmin($operator);
        $row = $this->db()->table('ad_campaigns')->where('id', $id)->first();
        abort_if($row === null, 404, 'Campaign not found');
        abort_unless(in_array($row->status, ['pending', 'approved'], true), 400, 'Only pending campaigns can be reviewed');
        $now = now();
        if (($data['status'] ?? '') === 'rejected') {
            $this->db()->table('ad_campaigns')->where('id', $id)->update([
                'status' => 'rejected',
                'rejected_reason' => trim((string) ($data['reason'] ?? '')) ?: 'Rejected by admin',
                'reviewed_by_id' => $this->actorId($operator),
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->one($operator, $id);
        }
        $live = AdPolicy::isLive(
            'published',
            $row->starts_on,
            $row->ends_on,
            (int) $row->budget_paise,
            (int) $row->budget_used_paise,
            $now,
        );
        $this->db()->table('ad_campaigns')->where('id', $id)->update([
            'status' => $live ? 'published' : 'approved',
            'rejected_reason' => null,
            'reviewed_by_id' => $this->actorId($operator),
            'reviewed_at' => $now,
            'published_at' => $live ? $now : null,
            'updated_at' => $now,
        ]);

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function pause(User $operator, int $id): array
    {
        $this->assertAdmin($operator);
        $row = $this->db()->table('ad_campaigns')->where('id', $id)->first();
        abort_if($row === null, 404, 'Campaign not found');
        abort_unless(in_array($row->status, ['published', 'approved'], true), 400, 'Only live or approved campaigns can be paused');
        $this->db()->table('ad_campaigns')->where('id', $id)->update([
            'status' => 'paused',
            'updated_at' => now(),
        ]);

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function resume(User $operator, int $id): array
    {
        $this->assertAdmin($operator);
        $row = $this->db()->table('ad_campaigns')->where('id', $id)->first();
        abort_if($row === null, 404, 'Campaign not found');
        abort_unless($row->status === 'paused', 400, 'Only paused campaigns can be resumed');
        $now = now();
        $live = AdPolicy::isLive(
            'published',
            $row->starts_on,
            $row->ends_on,
            (int) $row->budget_paise,
            (int) $row->budget_used_paise,
            $now,
        );
        $this->db()->table('ad_campaigns')->where('id', $id)->update([
            'status' => $live ? 'published' : 'approved',
            'published_at' => $live ? ($row->published_at ?: $now) : $row->published_at,
            'updated_at' => $now,
        ]);

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function serve(User $operator, array $query): array
    {
        foreach (['districtId', 'district_id', 'city', 'target_city'] as $forbidden) {
            if (array_key_exists($forbidden, $query) && $query[$forbidden] !== null && $query[$forbidden] !== '') {
                throw ValidationException::withMessages([
                    $forbidden => 'Client '.$forbidden.' is not accepted. Targeting uses the signed-in location.',
                ]);
            }
        }
        $placement = (string) ($query['placement'] ?? '');
        $suppressed = $this->suppressionReason($operator);
        $gate = AdPolicy::canServe($placement, $suppressed);
        if (! $gate['ok']) {
            return ['ads' => [], 'suppressed' => $gate['reason'], 'placement' => $placement];
        }
        $actor = $this->actorLocation($operator);
        $now = now();
        $rows = $this->db()->table('ad_campaigns as ad_campaigns')
            ->where('ad_campaigns.status', 'published')
            ->where('ad_campaigns.starts_on', '<=', $now)
            ->where('ad_campaigns.ends_on', '>=', $now)
            ->orderByDesc('ad_campaigns.id')
            ->limit(50)
            ->get();
        $ads = [];
        foreach ($rows as $row) {
            if (! AdPolicy::isLive($row->status, $row->starts_on, $row->ends_on, (int) $row->budget_paise, (int) $row->budget_used_paise, $now)) {
                continue;
            }
            if (! AdPolicy::matchesLocation(
                $row->state_id !== null ? (int) $row->state_id : null,
                $row->district_id !== null ? (int) $row->district_id : null,
                $row->target_city,
                $actor['state_id'],
                $actor['district_id'],
                $actor['city'],
            )) {
                continue;
            }
            $view = $this->present($row);
            $ads[] = [
                'id' => $view['id'],
                'business' => $view['business'],
                'campaign' => $view['campaign'],
                'category' => $view['category'],
                'campaignType' => $view['campaignType'],
                'bannerUrl' => $view['bannerUrl'],
                'ctaUrl' => $view['ctaUrl'],
                'placement' => $placement,
                'targetCity' => $view['targetCity'],
                'targetDistrict' => $view['targetDistrict'],
                'targetState' => $view['targetState'],
            ];
            if (count($ads) >= 5) {
                break;
            }
        }

        return ['ads' => $ads, 'suppressed' => null, 'placement' => $placement];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function track(User $operator, int $id, string $kind, array $data): array
    {
        $placement = (string) ($data['placement'] ?? '');
        $gate = AdPolicy::canServe($placement, $this->suppressionReason($operator));
        if (! $gate['ok']) {
            abort(403, 'Ads are not tracked on this screen');
        }
        abort_unless(in_array($kind, ['impression', 'click'], true), 400);

        return $this->db()->transaction(function () use ($operator, $id, $kind, $placement) {
            $row = $this->db()->table('ad_campaigns')->where('id', $id)->lockForUpdate()->first();
            abort_if($row === null, 404, 'Campaign is not live');
            abort_unless(
                AdPolicy::isLive($row->status, $row->starts_on, $row->ends_on, (int) $row->budget_paise, (int) $row->budget_used_paise),
                404,
                'Campaign is not live',
            );
            $recent = $this->db()->table('ad_events')
                ->where('campaign_id', $id)
                ->where('user_id', $this->actorId($operator))
                ->where('kind', $kind)
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();
            if ($recent) {
                return $this->present($row);
            }
            $costKey = $kind === 'click' ? 'ad_click_paise' : 'ad_impression_paise';
            $setting = $this->db()->table('system_settings')->where('key', $costKey)->value('value');
            $cost = (int) ($setting ?? ($kind === 'click' ? 100 : 10));
            $used = (int) $row->budget_used_paise + $cost;
            $this->db()->table('ad_events')->insert([
                'campaign_id' => $id,
                'user_id' => $this->actorId($operator),
                'kind' => $kind,
                'placement' => $placement,
                'created_at' => now(),
            ]);
            $this->db()->table('ad_campaigns')->where('id', $id)->update([
                'impressions' => $kind === 'impression' ? (int) $row->impressions + 1 : $row->impressions,
                'clicks' => $kind === 'click' ? (int) $row->clicks + 1 : $row->clicks,
                'budget_used_paise' => $used,
                'status' => $used >= (int) $row->budget_paise ? 'completed' : $row->status,
                'updated_at' => now(),
            ]);

            $updated = $this->db()->table('ad_campaigns as ad_campaigns')
                ->leftJoin('districts', 'districts.id', '=', 'ad_campaigns.district_id')
                ->leftJoin('states', 'states.id', '=', 'ad_campaigns.state_id')
                ->select('ad_campaigns.*', 'districts.name as district_name', 'states.name as state_name')
                ->where('ad_campaigns.id', $id)
                ->first();

            return $this->present($updated);
        });
    }

    private function scoped(User $operator)
    {
        $q = $this->db()->table('ad_campaigns as ad_campaigns')
            ->leftJoin('districts', 'districts.id', '=', 'ad_campaigns.district_id')
            ->leftJoin('states', 'states.id', '=', 'ad_campaigns.state_id')
            ->select('ad_campaigns.*', 'districts.name as district_name', 'states.name as state_name');
        if (TerritoryScope::isUnrestricted($operator)) {
            return $q;
        }
        if ($operator->role === OperatorRole::ADVERTISER) {
            return $q->where('ad_campaigns.advertiser_user_id', $this->actorId($operator));
        }
        abort(403);
    }

    private function load(User $operator, int $id, bool $forCreative = false): object
    {
        $row = $this->db()->table('ad_campaigns as ad_campaigns')
            ->leftJoin('districts', 'districts.id', '=', 'ad_campaigns.district_id')
            ->leftJoin('states', 'states.id', '=', 'ad_campaigns.state_id')
            ->select('ad_campaigns.*', 'districts.name as district_name', 'states.name as state_name')
            ->where('ad_campaigns.id', $id)
            ->first();
        abort_if($row === null, 404, 'Campaign not found');
        if ($forCreative && $row->status === 'published') {
            return $row;
        }
        if (TerritoryScope::isUnrestricted($operator) || $this->owns($operator, $row)) {
            return $row;
        }
        abort(404, 'Campaign not found');
    }

    private function owns(User $operator, object $row): bool
    {
        return $row->advertiser_user_id !== null && (int) $row->advertiser_user_id === $this->actorId($operator);
    }

    private function assertAdmin(User $operator): void
    {
        abort_unless($operator->isPrivilegedOperator() && $operator->can('advertising.edit'), 403, 'Admin approval required');
    }

    private function assertWindow(mixed $startsOn, mixed $endsOn): void
    {
        $start = Carbon::parse($startsOn);
        $end = Carbon::parse($endsOn);
        abort_if($end->lte($start), 400, 'End date must be after start date');
    }

    /**
     * @return array{state_id: ?int, district_id: ?int}
     */
    private function assertTerritory(mixed $stateId, mixed $districtId): array
    {
        $stateId = $stateId === null || $stateId === '' ? null : (int) $stateId;
        $districtId = $districtId === null || $districtId === '' ? null : (int) $districtId;
        if ($stateId !== null) {
            abort_unless($this->db()->table('states')->where('id', $stateId)->exists(), 400, 'Unknown target state');
        }
        if ($districtId !== null) {
            $district = $this->db()->table('districts')->where('id', $districtId)->first();
            abort_unless($district !== null, 400, 'Unknown target district');
            if ($stateId !== null && (int) $district->state_id !== $stateId) {
                abort(400, 'District is not in the selected state');
            }
            $stateId = $stateId ?? (int) $district->state_id;
        }

        return ['state_id' => $stateId, 'district_id' => $districtId];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function budgetPaise(array $data): int
    {
        if (isset($data['budget_paise'])) {
            return max(1, (int) $data['budget_paise']);
        }

        return max(1, (int) $data['budget_rupees'] * 100);
    }

    private function actorId(User $operator): int
    {
        return (int) ($operator->nest_user_id ?: $operator->id);
    }

    /**
     * @return array{state_id: ?int, district_id: ?int, city: ?string}
     */
    private function actorLocation(User $operator): array
    {
        $platform = $this->db()->table('users')->where('id', $this->actorId($operator))->first();
        $stateId = $operator->state_id ?? $platform->state_id ?? null;
        $districtId = $operator->district_id ?? $platform->district_id ?? null;

        return [
            'state_id' => $stateId !== null ? (int) $stateId : null,
            'district_id' => $districtId !== null ? (int) $districtId : null,
            'city' => $platform->last_address ?? null,
        ];
    }

    private function suppressionReason(User $operator): ?string
    {
        $actorId = $this->actorId($operator);
        $since = now()->subMinutes(30);
        if ($this->db()->table('platform_audit_events')
            ->where('actor_user_id', $actorId)
            ->where('action', 'sos')
            ->where('created_at', '>=', $since)
            ->exists()) {
            return 'sos';
        }
        if (Schema::connection('platform')->hasTable('safety_incidents')
            && $this->db()->table('safety_incidents')
                ->where('reporter_user_id', $actorId)
                ->where('type', 'sos')
                ->whereIn('status', ['open', 'investigating'])
                ->where('created_at', '>=', $since)
                ->exists()) {
            return 'sos';
        }
        $driver = $this->db()->table('drivers')->where('user_id', $actorId)->first();
        if ($driver && ($driver->duty_status ?? null) === 'on_trip') {
            return 'active_trip';
        }
        $bookingQ = $this->db()->table('bookings')->whereIn('status', [
            'REQUESTED', 'DRIVER_SEARCHING', 'ASSIGNED', 'DRIVER_ASSIGNED',
            'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'ONGOING', 'STARTED',
        ]);
        $bookingQ->where(function ($inner) use ($actorId, $driver) {
            $inner->where('customer_id', $actorId);
            if ($driver) {
                $inner->orWhere('driver_id', $driver->id);
            }
        });
        $booking = $bookingQ->first();
        if ($booking) {
            if (in_array($booking->status, ['DRIVER_ARRIVING', 'DRIVER_ARRIVED'], true)) {
                return $booking->status === 'DRIVER_ARRIVED' ? 'otp' : 'driver_arrival';
            }
            if (in_array($booking->status, ['STARTED', 'ONGOING'], true)) {
                return 'active_trip';
            }

            return 'active_booking';
        }
        if ($this->db()->table('payments')
            ->where('customer_id', $actorId)
            ->whereNull('parent_id')
            ->whereIn('status', ['created', 'pending', 'initiated'])
            ->exists()) {
            return 'payment';
        }

        return null;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function map(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row, bool $withEvents = false): array
    {
        $impressions = (int) ($row->impressions ?? 0);
        $clicks = (int) ($row->clicks ?? 0);
        $used = (int) ($row->budget_used_paise ?? 0);
        $view = [
            'id' => (int) $row->id,
            'business' => $row->business_name,
            'businessInfo' => $row->business_info ?? null,
            'campaign' => $row->title,
            'category' => $row->category,
            'categoryLabel' => AdPolicy::CATEGORIES[$row->category] ?? $row->category,
            'campaignType' => $row->campaign_type ?? 'banner',
            'campaignTypeLabel' => AdPolicy::TYPES[$row->campaign_type ?? 'banner'] ?? ($row->campaign_type ?? 'banner'),
            'bannerUrl' => $row->banner_key ? '/ads/'.$row->id.'/banner' : null,
            'targetCity' => $row->target_city,
            'targetState' => isset($row->state_id) && $row->state_id
                ? ['id' => (int) $row->state_id, 'name' => $row->state_name ?? null]
                : null,
            'targetDistrict' => isset($row->district_id) && $row->district_id
                ? ['id' => (int) $row->district_id, 'name' => $row->district_name ?? null]
                : null,
            'startDate' => $row->starts_on ? Carbon::parse($row->starts_on)->toDateString() : null,
            'endDate' => $row->ends_on ? Carbon::parse($row->ends_on)->toDateString() : null,
            'budgetPaise' => (int) ($row->budget_paise ?? 0),
            'budgetRupees' => ((int) ($row->budget_paise ?? 0)) / 100,
            'budgetUsedPaise' => $used,
            'revenuePaise' => $used,
            'revenueRupees' => $used / 100,
            'status' => $row->status,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => AdPolicy::ctr($impressions, $clicks),
            'ctaUrl' => $row->cta_url ?? null,
            'rejectedReason' => $row->rejected_reason ?? null,
            'publishedAt' => $row->published_at ?? null,
        ];
        if ($withEvents) {
            $view['events'] = $this->db()->table('ad_events')->where('campaign_id', $row->id)->orderByDesc('id')->limit(50)->get()->all();
        }

        return $view;
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
