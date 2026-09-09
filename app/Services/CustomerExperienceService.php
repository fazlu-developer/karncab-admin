<?php

namespace App\Services;

use App\Models\User;
use App\Platform\CustomerDashboard;
use App\Platform\OperatorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerExperienceService
{
    public function __construct(private readonly SupportService $tickets) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return CustomerDashboard::catalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $operator): array
    {
        $this->assertCustomer($operator);
        $userId = $this->actorId($operator);

        return [
            'catalog' => $this->catalog(),
            'profile' => $this->profile($operator),
            'activeRide' => $this->bookings($operator, 'active')[0] ?? null,
            'upcoming' => $this->bookings($operator, 'upcoming'),
            'scheduled' => $this->bookings($operator, 'scheduled'),
            'history' => $this->bookings($operator, 'history'),
            'parcels' => $this->parcels($operator),
            'travel' => $this->travel($operator),
            'bulk' => $this->bulk($operator),
            'corporate' => $this->bookings($operator, 'corporate'),
            'wallet' => $this->wallet($operator),
            'coupons' => $this->coupons($operator),
            'offers' => $this->offers($operator),
            'notifications' => $this->notifications($operator),
            'ratings' => $this->ratings($operator),
            'complaints' => $this->complaints($operator),
            'invoices' => $this->invoices($operator),
            'places' => $this->places($operator),
            'family' => $this->family($operator),
            'guest' => $this->bookings($operator, 'guest'),
            'emergency' => $this->emergency($operator),
            'contacts' => app(SafetyService::class)->contacts($operator),
            'verify' => ($active = $this->bookings($operator, 'active')[0] ?? null)
                ? app(SafetyService::class)->verify($operator, (int) $active['id'])
                : null,
            'userId' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function section(User $operator, string $section): array
    {
        $this->assertCustomer($operator);
        $keys = array_column(CustomerDashboard::tools(), 'key');
        abort_unless(in_array($section, $keys, true), 404);
        $dash = $this->dashboard($operator);
        $map = [
            'profile' => ['profile' => $dash['profile']],
            'emergency' => ['emergency' => $dash['emergency'], 'contacts' => $dash['contacts']],
            'sos' => ['verify' => $dash['verify'], 'activeRide' => $dash['activeRide']],
            'share' => ['verify' => $dash['verify'], 'activeRide' => $dash['activeRide']],
            'verify' => ['verify' => $dash['verify'], 'activeRide' => $dash['activeRide']],
            'places' => ['places' => $dash['places']],
            'family' => ['family' => $dash['family']],
            'guest' => ['rows' => $dash['guest']],
            'history' => ['rows' => $dash['history']],
            'upcoming' => ['rows' => $dash['upcoming']],
            'scheduled' => ['rows' => $dash['scheduled']],
            'active' => ['rows' => array_values(array_filter([$dash['activeRide']]))],
            'parcels' => ['rows' => $dash['parcels']],
            'travel' => ['rows' => $dash['travel']],
            'bulk' => ['rows' => $dash['bulk']],
            'corporate' => ['rows' => $dash['corporate']],
            'wallet' => ['wallet' => $dash['wallet']],
            'coupons' => ['rows' => $dash['coupons']],
            'offers' => ['rows' => $dash['offers']],
            'invoices' => ['rows' => $dash['invoices']],
            'notifications' => ['rows' => $dash['notifications']],
            'ratings' => ['rows' => $dash['ratings']],
            'complaints' => ['rows' => $dash['complaints']],
        ];

        return [
            'section' => $section,
            'catalog' => $dash['catalog'],
            'label' => collect(CustomerDashboard::tools())->firstWhere('key', $section)['label'] ?? $section,
            'data' => $map[$section] ?? [],
            'profile' => $dash['profile'],
            'family' => $dash['family'],
            'places' => $dash['places'],
            'emergency' => $dash['emergency'],
            'contacts' => $dash['contacts'] ?? [],
            'verify' => $dash['verify'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateProfile(User $operator, array $data): array
    {
        $this->assertCustomer($operator);
        $patch = [];
        foreach (['name', 'phone', 'last_address'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $data[$field] ? trim((string) $data[$field]) : null;
            }
        }
        if ($patch !== []) {
            $patch['updated_at'] = now();
            $this->db()->table('users')->where('id', $this->actorId($operator))->update($patch);
        }

        return $this->profile($operator);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateEmergency(User $operator, array $data): array
    {
        $this->assertCustomer($operator);
        $this->db()->table('users')->where('id', $this->actorId($operator))->update([
            'emergency_name' => trim((string) ($data['emergency_name'] ?? '')) ?: null,
            'emergency_phone' => preg_replace('/\D+/', '', (string) ($data['emergency_phone'] ?? '')) ?: null,
            'updated_at' => now(),
        ]);

        return $this->emergency($operator);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addFamily(User $operator, array $data): array
    {
        $this->assertCustomer($operator);
        $phone = preg_replace('/\D+/', '', (string) $data['phone']);
        abort_unless(strlen($phone) === 10, 400, 'Enter a valid 10-digit passenger mobile');
        $id = $this->db()->table('family_members')->insertGetId([
            'user_id' => $this->actorId($operator),
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'relation' => isset($data['relation']) ? (trim((string) $data['relation']) ?: null) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'relation' => isset($data['relation']) ? (trim((string) $data['relation']) ?: null) : null,
        ];
    }

    public function removeFamily(User $operator, int $id): void
    {
        $this->assertCustomer($operator);
        $deleted = $this->db()->table('family_members')
            ->where('id', $id)
            ->where('user_id', $this->actorId($operator))
            ->delete();
        abort_unless($deleted > 0, 404, 'Family member not found');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addPlace(User $operator, array $data): array
    {
        $this->assertCustomer($operator);
        $id = $this->db()->table('user_places')->insertGetId([
            'user_id' => $this->actorId($operator),
            'kind' => $data['kind'] ?? 'SAVED',
            'title' => trim((string) $data['title']),
            'subtitle' => isset($data['subtitle']) ? (trim((string) $data['subtitle']) ?: null) : null,
            'address' => trim((string) $data['address']),
            'lat' => $data['lat'] ?? 0,
            'lng' => $data['lng'] ?? 0,
            'created_at' => now(),
        ]);

        return collect($this->places($operator))->firstWhere('id', $id) ?? ['id' => $id];
    }

    public function removePlace(User $operator, int $id): void
    {
        $this->assertCustomer($operator);
        $deleted = $this->db()->table('user_places')
            ->where('id', $id)
            ->where('user_id', $this->actorId($operator))
            ->delete();
        abort_unless($deleted > 0, 404, 'Saved location not found');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addComplaint(User $operator, array $data): array
    {
        $this->assertCustomer($operator);

        return $this->tickets->create($operator, array_merge($data, [
            'kind' => $data['kind'] ?? 'complaint',
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addRating(User $operator, array $data): array
    {
        $this->assertCustomer($operator);
        $booking = $this->db()->table('bookings')->where('id', (int) $data['booking_id'])->first();
        abort_if($booking === null, 404, 'Booking not found');
        abort_unless((int) $booking->customer_id === $this->actorId($operator), 404, 'Booking not found');
        abort_unless(in_array(strtoupper((string) $booking->status), ['COMPLETED'], true), 400, 'Rate a completed ride');
        $existing = $this->db()->table('booking_ratings')->where('booking_id', $booking->id)->where('from_role', 'CUSTOMER')->first();
        $payload = [
            'booking_id' => $booking->id,
            'stars' => max(1, min(5, (int) $data['stars'])),
            'from_role' => 'CUSTOMER',
            'comment' => isset($data['comment']) ? trim((string) $data['comment']) : null,
        ];
        if ($existing) {
            $this->db()->table('booking_ratings')->where('id', $existing->id)->update($payload);
        } else {
            $this->db()->table('booking_ratings')->insert($payload);
        }

        return $this->ratings($operator)[0] ?? $payload;
    }

    public function markNotificationRead(User $operator, int $id): void
    {
        $this->assertCustomer($operator);
        $updated = $this->db()->table('user_notifications')
            ->where('id', $id)
            ->where('user_id', $this->actorId($operator))
            ->update(['read_at' => now()]);
        abort_unless($updated > 0, 404, 'Notification not found');
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(User $operator): array
    {
        $row = $this->platformUser($operator);

        return [
            'id' => $this->actorId($operator),
            'name' => $row->name ?? $operator->name,
            'email' => $row->email ?? $operator->email,
            'phone' => $row->phone ?? null,
            'address' => $row->last_address ?? null,
            'stateId' => $row->state_id ?? $operator->state_id,
            'districtId' => $row->district_id ?? $operator->district_id,
            'role' => $row->role ?? $operator->role,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emergency(User $operator): array
    {
        $row = $this->platformUser($operator);

        return [
            'name' => $row->emergency_name ?? null,
            'phone' => $row->emergency_phone ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookings(User $operator, string $bucket): array
    {
        $q = $this->db()->table('bookings')->where('customer_id', $this->actorId($operator));
        if ($bucket === 'corporate') {
            $q->whereNotNull('corporate_account_id');
        }
        if ($bucket === 'guest') {
            $q->where('booked_for_other', true);
        }
        $rows = $q->orderByDesc('id')->limit(100)->get();
        $out = [];
        foreach ($rows as $row) {
            $computed = CustomerDashboard::bucket((string) $row->status, (string) ($row->product ?? 'LOCAL_CAB'), $row->scheduled_at ?? null);
            if (! in_array($bucket, ['corporate', 'guest'], true) && $computed !== $bucket) {
                continue;
            }
            $out[] = $this->presentBooking($row);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parcels(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('parcel_shipments')) {
            return [];
        }

        return $this->db()->table('parcel_shipments')->where('customer_id', $this->actorId($operator))->orderByDesc('id')->limit(50)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
                'pickup' => $row->pickup_text,
                'drop' => $row->drop_text,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function travel(User $operator): array
    {
        if (! Schema::connection('platform')->hasColumn('travel_bookings', 'customer_id')) {
            return [];
        }

        return $this->db()->table('travel_bookings')->where('customer_id', $this->actorId($operator))->orderByDesc('id')->limit(50)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bulk(User $operator): array
    {
        if (! Schema::connection('platform')->hasColumn('bulk_bookings', 'customer_id')) {
            return [];
        }

        return $this->db()->table('bulk_bookings')->where('customer_id', $this->actorId($operator))->orderByDesc('id')->limit(50)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
                'eventKey' => $row->event_key ?? null,
            ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function wallet(User $operator): ?array
    {
        $row = $this->db()->table('wallets')->where('owner_user_id', $this->actorId($operator))->where('owner_type', 'CUSTOMER')->first();
        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'balancePaise' => (int) $row->balance_paise,
            'balanceRupees' => ((int) $row->balance_paise) / 100,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coupons(User $operator): array
    {
        return app(CouponService::class)->offers($operator);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offers(User $operator): array
    {
        return array_map(fn (array $row) => $row['offer'] ?? [
            'title' => $row['title'] ?? $row['code'],
            'code' => $row['code'] ?? null,
            'cta' => 'Use '.($row['code'] ?? ''),
        ], $this->coupons($operator));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notifications(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('user_notifications')) {
            return [];
        }

        return $this->db()->table('user_notifications')->where('user_id', $this->actorId($operator))->orderByDesc('id')->limit(40)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'kind' => $row->kind,
                'read' => ! empty($row->read_at),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ratings(User $operator): array
    {
        return $this->db()->table('booking_ratings')
            ->join('bookings', 'bookings.id', '=', 'booking_ratings.booking_id')
            ->where('bookings.customer_id', $this->actorId($operator))
            ->orderByDesc('booking_ratings.id')
            ->limit(40)
            ->get(['booking_ratings.*', 'bookings.public_ref'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'bookingId' => (int) $row->booking_id,
                'publicRef' => $row->public_ref,
                'stars' => (int) $row->stars,
                'comment' => $row->comment,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function complaints(User $operator): array
    {
        return $this->db()->table('support_tickets')->where('user_id', $this->actorId($operator))->orderByDesc('id')->limit(40)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'ticketId' => $row->public_ref,
                'publicRef' => $row->public_ref,
                'kind' => $row->kind,
                'status' => $row->status,
                'subject' => $row->subject,
                'category' => $row->category ?? 'other',
                'priority' => $row->priority ?? 'medium',
                'description' => $row->description ?? $row->subject,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoices(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('invoices')) {
            return [];
        }

        return $this->db()->table('invoices')->where('customer_id', $this->actorId($operator))->orderByDesc('id')->limit(40)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref,
                'status' => $row->status,
                'totalPaise' => (int) ($row->total_paise ?? 0),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function places(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('user_places')) {
            return [];
        }

        return $this->db()->table('user_places')->where('user_id', $this->actorId($operator))->where('kind', 'SAVED')->orderByDesc('id')->limit(40)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'address' => $row->address,
                'kind' => $row->kind,
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function family(User $operator): array
    {
        if (! Schema::connection('platform')->hasTable('family_members')) {
            return [];
        }

        return $this->db()->table('family_members')->where('user_id', $this->actorId($operator))->orderByDesc('id')->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'phone' => $row->phone,
                'relation' => $row->relation,
            ])->all();
    }

    private function presentBooking(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'product' => $row->product ?? null,
            'pickup' => $row->pickup_text ?? null,
            'drop' => $row->drop_text ?? null,
            'bucket' => CustomerDashboard::bucket((string) $row->status, (string) ($row->product ?? 'LOCAL_CAB'), $row->scheduled_at ?? null),
            'bookedForOther' => (bool) ($row->booked_for_other ?? false),
            'passengerName' => $row->passenger_name ?? null,
            'corporate' => ! empty($row->corporate_account_id),
        ];
    }

    private function assertCustomer(User $operator): void
    {
        abort_unless(in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE], true), 403, 'Customer account required');
    }

    private function actorId(User $operator): int
    {
        return (int) ($operator->nest_user_id ?: $operator->id);
    }

    private function platformUser(User $operator): object
    {
        return $this->db()->table('users')->where('id', $this->actorId($operator))->first()
            ?? (object) ['name' => $operator->name, 'email' => $operator->email, 'phone' => null, 'last_address' => null, 'state_id' => $operator->state_id, 'district_id' => $operator->district_id, 'role' => $operator->role, 'emergency_name' => null, 'emergency_phone' => null];
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
