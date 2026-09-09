<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\SafetyPolicy;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SafetyService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return SafetyPolicy::catalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless($operator->can('safety.view'), 403);

        return [
            'catalog' => $this->catalog(),
            'incidents' => $this->listIncidents($operator, $query),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function me(User $operator): array
    {
        $this->assertRiderOrDriver($operator);
        $emergency = $this->primaryEmergency($operator);
        $police = $this->setting('driver_safety_sos', '112');
        $helpline = $this->setting('driver_safety_helpline', '08041234500');

        return [
            'catalog' => $this->catalog(),
            'sos' => ['phone' => $police, 'tel' => 'tel:'.preg_replace('/\D+/', '', $police)],
            'support' => ['phone' => $helpline, 'tel' => 'tel:'.preg_replace('/\D+/', '', $helpline)],
            'emergency' => $emergency,
            'contacts' => $this->contacts($operator),
            'tools' => $operator->role === OperatorRole::DRIVER ? $this->catalog()['driver'] : $this->catalog()['customer'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(User $operator): array
    {
        $this->assertRiderOrDriver($operator);
        if (! Schema::connection('platform')->hasTable('emergency_contacts')) {
            $primary = $this->primaryEmergency($operator);

            return $primary['phone'] ? [array_merge($primary, ['id' => 0, 'primary' => true])] : [];
        }
        $rows = $this->db()->table('emergency_contacts')->where('user_id', $this->actorId($operator))->orderByDesc('is_primary')->orderByDesc('id')->get();
        if ($rows->isEmpty()) {
            $primary = $this->primaryEmergency($operator);

            return $primary['phone'] ? [[
                'id' => 0,
                'name' => $primary['name'],
                'phone' => $primary['phone'],
                'relation' => null,
                'primary' => true,
            ]] : [];
        }

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'phone' => $row->phone,
            'relation' => $row->relation,
            'primary' => (bool) $row->is_primary,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveEmergency(User $operator, array $data): array
    {
        $this->assertRiderOrDriver($operator);
        $phone = SafetyPolicy::indianMobile($data['phone'] ?? $data['emergency_phone'] ?? null);
        abort_if($phone === null, 400, 'Enter a valid 10-digit Indian mobile number');
        $name = trim((string) ($data['name'] ?? $data['emergency_name'] ?? ''));
        abort_unless(strlen($name) >= 2, 400, 'Emergency contact name is required');
        $relation = isset($data['relation']) ? (trim((string) $data['relation']) ?: null) : null;
        $userId = $this->actorId($operator);
        $this->db()->table('users')->where('id', $userId)->update([
            'emergency_name' => $name,
            'emergency_phone' => $phone,
            'updated_at' => now(),
        ]);
        $driverId = $this->driverId($operator);
        if ($driverId && Schema::connection('platform')->hasColumn('drivers', 'emergency_phone')) {
            $this->db()->table('drivers')->where('id', $driverId)->update([
                'emergency_name' => $name,
                'emergency_phone' => $phone,
                'updated_at' => now(),
            ]);
        }
        if (Schema::connection('platform')->hasTable('emergency_contacts')) {
            $this->db()->table('emergency_contacts')->where('user_id', $userId)->update(['is_primary' => false]);
            $existing = $this->db()->table('emergency_contacts')->where('user_id', $userId)->where('phone', $phone)->first();
            $payload = [
                'user_id' => $userId,
                'name' => $name,
                'phone' => $phone,
                'relation' => $relation,
                'is_primary' => true,
                'updated_at' => now(),
            ];
            if ($existing) {
                $this->db()->table('emergency_contacts')->where('id', $existing->id)->update($payload);
            } else {
                $this->db()->table('emergency_contacts')->insert($payload + ['created_at' => now()]);
            }
        }

        return $this->me($operator);
    }

    public function removeContact(User $operator, int $id): void
    {
        $this->assertRiderOrDriver($operator);
        abort_unless(Schema::connection('platform')->hasTable('emergency_contacts'), 404);
        $deleted = $this->db()->table('emergency_contacts')->where('id', $id)->where('user_id', $this->actorId($operator))->delete();
        abort_unless($deleted > 0, 404, 'Emergency contact not found');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sos(User $operator, array $data = []): array
    {
        $this->assertRiderOrDriver($operator);
        $kind = $data['kind'] ?? null;
        $booking = $this->resolveLiveBooking($operator, isset($data['booking_id']) ? (int) $data['booking_id'] : null);
        $profile = $this->me($operator);
        $lat = $this->coord($data['lat'] ?? null) ?? $this->lastLat($operator);
        $lng = $this->coord($data['lng'] ?? null) ?? $this->lastLng($operator);
        $map = SafetyPolicy::mapsUrl($lat, $lng);
        $share = $booking ? $this->ensureShare((int) $booking->id) : null;
        $shareText = $this->shareSms($booking->public_ref ?? null, $map, $share['url'] ?? null);
        $payload = [
            'police' => $profile['sos'],
            'karnacab' => $profile['support'] + ['chatUrl' => $this->setting('driver_support_chat_url', '') ?: null],
            'emergency' => $profile['emergency'],
            'location' => ['lat' => $lat, 'lng' => $lng, 'mapsUrl' => $map],
            'trip' => [
                'bookingId' => $booking->id ?? null,
                'publicRef' => $booking->public_ref ?? null,
                'shareUrl' => $share['url'] ?? null,
                'shareText' => $shareText,
                'smsUrl' => 'sms:?body='.rawurlencode($shareText),
                'mapsUrl' => $map,
            ],
            'incident' => null,
        ];
        if ($kind && $kind !== 'share') {
            abort_unless(in_array($kind, SafetyPolicy::SOS_KINDS, true), 422, 'Unknown SOS kind');
            $payload['incident'] = $this->createIncident($operator, [
                'type' => 'sos',
                'kind' => $kind,
                'description' => trim((string) ($data['description'] ?? '')) ?: 'SOS '.$kind,
                'bookingId' => $booking->id ?? null,
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }
        if ($kind) {
            $this->audit($operator, 'sos', $booking->id ?? null, ['kind' => $kind, 'lat' => $lat, 'lng' => $lng]);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function share(User $operator, ?int $bookingId): array
    {
        $this->assertRiderOrDriver($operator);
        $booking = $this->resolveLiveBooking($operator, $bookingId);
        abort_if($booking === null, 400, 'Live trip sharing is available during an active ride');
        $token = $this->ensureShare((int) $booking->id);
        $map = SafetyPolicy::mapsUrl($booking->pickup_lat ?? null, $booking->pickup_lng ?? null);
        $shareText = $this->shareSms($booking->public_ref, $map, $token['url']);

        return $token + [
            'publicRef' => $booking->public_ref,
            'shareText' => $shareText,
            'smsUrl' => 'sms:?body='.rawurlencode($shareText),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicShare(string $token): array
    {
        $booking = $this->db()->table('bookings')
            ->where('share_token', $token)
            ->where('share_expires_at', '>', now())
            ->first();
        abort_if($booking === null, 404, 'Share link expired or not found');
        $customer = $this->db()->table('users')->where('id', $booking->customer_id)->first();
        $driver = $booking->driver_id ? $this->db()->table('drivers')->where('id', $booking->driver_id)->first() : null;
        $driverUser = $driver ? $this->db()->table('users')->where('id', $driver->user_id)->first() : null;
        $vehicle = $booking->vehicle_id ? $this->db()->table('vehicles')->where('id', $booking->vehicle_id)->first() : null;
        $lat = $driverUser->last_lat ?? null;
        $lng = $driverUser->last_lng ?? null;

        return [
            'publicRef' => $booking->public_ref,
            'status' => $booking->status,
            'rider' => ['firstName' => SafetyPolicy::firstName($customer->name ?? null)],
            'driver' => $driverUser ? [
                'firstName' => SafetyPolicy::firstName($driverUser->name),
                'kycVerified' => ($driver->kyc_status ?? '') === 'verified',
                'rating' => (float) ($driver->rating_avg ?? 0),
            ] : null,
            'vehicle' => $vehicle ? [
                'category' => $vehicle->category,
                'color' => $vehicle->color ?? null,
                'plateHint' => SafetyPolicy::plateHint($vehicle->registration_no ?? null),
            ] : null,
            'lastLocation' => ['lat' => $lat, 'lng' => $lng, 'mapsUrl' => SafetyPolicy::mapsUrl($lat, $lng)],
            'expiresAt' => $booking->share_expires_at,
            'otp' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(User $operator, int $bookingId): array
    {
        $this->assertRiderOrDriver($operator);
        $booking = $this->ownedBooking($operator, $bookingId);
        $isCustomer = (int) $booking->customer_id === $this->actorId($operator);
        $isDriver = $this->driverId($operator) && (int) $booking->driver_id === $this->driverId($operator);
        $driver = $booking->driver_id ? $this->db()->table('drivers')->where('id', $booking->driver_id)->first() : null;
        $driverUser = $driver ? $this->db()->table('users')->where('id', $driver->user_id)->first() : null;
        $vehicle = $booking->vehicle_id ? $this->db()->table('vehicles')->where('id', $booking->vehicle_id)->first() : null;
        $photo = $driver && Schema::connection('platform')->hasTable('driver_documents')
            ? $this->db()->table('driver_documents')->where('driver_id', $driver->id)->whereIn('type', ['PHOTO', 'SELFIE'])->orderByDesc('id')->first()
            : null;
        $started = in_array(strtoupper((string) $booking->status), ['STARTED', 'ONGOING', 'COMPLETED'], true);

        return [
            'publicRef' => $booking->public_ref,
            'status' => $booking->status,
            'driver' => $driverUser ? [
                'name' => $isCustomer ? $driverUser->name : SafetyPolicy::firstName($driverUser->name),
                'photoUrl' => $photo && ($isCustomer || $isDriver) ? url('/api/v1/safety/bookings/'.$bookingId.'/driver-photo') : null,
                'rating' => (float) ($driver->rating_avg ?? 0),
                'verificationStatus' => $driver->kyc_status,
                'kycVerified' => ($driver->kyc_status ?? '') === 'verified',
                'phone' => $isCustomer ? ($driverUser->phone ?? null) : null,
            ] : null,
            'vehicle' => $vehicle ? [
                'number' => ($isCustomer || $isDriver) ? $vehicle->registration_no : SafetyPolicy::plateHint($vehicle->registration_no),
                'type' => $vehicle->category,
                'color' => $vehicle->color ?? null,
                'brand' => $vehicle->brand ?? null,
                'model' => $vehicle->model ?? null,
            ] : null,
            'otp' => $isCustomer ? [
                'start' => $booking->start_otp ?? null,
                'end' => $started ? ($booking->end_otp ?? null) : null,
            ] : ['start' => null, 'end' => null],
            'share' => ($isCustomer || $isDriver) ? $this->ensureShare((int) $booking->id) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmOtp(User $operator, int $bookingId, string $action, ?string $otp): array
    {
        abort_unless($operator->role === OperatorRole::DRIVER, 403, 'Only the assigned driver can confirm trip PIN');
        abort_unless(in_array($action, ['start', 'complete'], true), 422);
        $booking = $this->ownedBooking($operator, $bookingId);
        abort_unless((int) $booking->driver_id === $this->driverId($operator), 403, 'This booking is assigned to another driver');
        $status = strtoupper((string) $booking->status);
        if ($action === 'start') {
            abort_unless(in_array($status, ['DRIVER_ARRIVED'], true), 400, 'Arrive at pickup before starting with the customer PIN');
            SafetyPolicy::assertPin('start', $booking->start_otp ?? null, $otp);
            $this->db()->table('bookings')->where('id', $booking->id)->update([
                'status' => 'STARTED',
                'trip_started_at' => now(),
                'end_otp' => $booking->end_otp ?: (string) random_int(1000, 9999),
                'updated_at' => now(),
            ]);
        } else {
            abort_unless(in_array($status, ['STARTED', 'ONGOING'], true), 400, 'Trip is not in progress');
            SafetyPolicy::assertPin('end', $booking->end_otp ?? null, $otp);
            $this->db()->table('bookings')->where('id', $booking->id)->update([
                'status' => 'COMPLETED',
                'trip_ended_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->audit($operator, 'trip_otp', $bookingId, ['action' => $action]);

        return $this->verify($operator, $bookingId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listIncidents(User $operator, array $query = []): array
    {
        $q = $this->incidentQuery($operator);
        if (! empty($query['type'])) {
            $q->where('safety_incidents.type', $query['type']);
        }
        if (! empty($query['status'])) {
            $q->where('safety_incidents.status', $query['status']);
        }

        return $q->orderByDesc('safety_incidents.id')->limit(100)->get()->map(fn ($row) => $this->presentIncident($row, $operator))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function oneIncident(User $operator, int $id): array
    {
        $row = $this->incidentQuery($operator)->where('safety_incidents.id', $id)->first();
        abort_if($row === null, 404, 'Incident not found');

        return $this->presentIncident($row, $operator);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function review(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('safety.edit'), 403);
        $this->oneIncident($operator, $id);
        abort_unless(in_array($data['status'], SafetyPolicy::STATUSES, true), 422);
        $this->db()->table('safety_incidents')->where('id', $id)->update([
            'status' => $data['status'],
            'admin_note' => isset($data['admin_note']) ? trim((string) $data['admin_note']) : null,
            'updated_at' => now(),
        ]);

        return $this->oneIncident($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createIncident(User $operator, array $input): array
    {
        $this->assertRiderOrDriver($operator);
        $emergency = $this->primaryEmergency($operator);
        $booking = isset($input['bookingId']) ? $this->db()->table('bookings')->where('id', $input['bookingId'])->first() : null;
        $id = $this->db()->table('safety_incidents')->insertGetId([
            'public_ref' => 'KCS'.strtoupper(Str::random(10)),
            'type' => $input['type'],
            'kind' => $input['kind'] ?? null,
            'status' => 'open',
            'description' => substr((string) $input['description'], 0, 1000),
            'booking_id' => $booking->id ?? null,
            'reporter_user_id' => $this->actorId($operator),
            'driver_id' => $this->driverId($operator) ?: ($booking->driver_id ?? null),
            'district_id' => $operator->district_id ?: ($booking->district_id ?? null),
            'lat' => $input['lat'] ?? null,
            'lng' => $input['lng'] ?? null,
            'location_text' => $booking->pickup_text ?? $this->platformUser($operator)->last_address ?? null,
            'actor_role' => $operator->role,
            'emergency_name' => $emergency['name'],
            'emergency_phone' => $emergency['phone'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->oneIncident($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function primaryEmergency(User $operator): array
    {
        $row = $this->platformUser($operator);
        $driver = $this->driverId($operator)
            ? $this->db()->table('drivers')->where('id', $this->driverId($operator))->first()
            : null;
        $name = $driver->emergency_name ?? $row->emergency_name ?? null;
        $phone = $driver->emergency_phone ?? $row->emergency_phone ?? null;

        return [
            'name' => $name,
            'phone' => $phone,
            'tel' => $phone ? 'tel:'.preg_replace('/\D+/', '', $phone) : null,
        ];
    }

    private function incidentQuery(User $operator)
    {
        $q = $this->db()->table('safety_incidents')
            ->leftJoin('users as reporters', 'reporters.id', '=', 'safety_incidents.reporter_user_id')
            ->leftJoin('bookings', 'bookings.id', '=', 'safety_incidents.booking_id')
            ->select('safety_incidents.*', 'reporters.name as reporter_name', 'reporters.phone as reporter_phone', 'bookings.public_ref as booking_ref');
        if (in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER], true)) {
            $q->where(function ($inner) use ($operator) {
                $inner->where('safety_incidents.reporter_user_id', $this->actorId($operator));
                $driverId = $this->driverId($operator);
                if ($driverId) {
                    $inner->orWhere('safety_incidents.driver_id', $driverId);
                }
            });

            return $q;
        }
        abort_unless($operator->can('safety.view'), 403);
        TerritoryScope::applySafetyIncidents($q, $operator);

        return $q;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentIncident(object $row, User $operator): array
    {
        $ops = $operator->can('safety.view') && ! in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER], true);

        return [
            'id' => (int) $row->id,
            'incidentId' => $row->public_ref,
            'bookingId' => $row->booking_id ? (int) $row->booking_id : null,
            'bookingRef' => $row->booking_ref ?? null,
            'user' => [
                'id' => (int) $row->reporter_user_id,
                'firstName' => SafetyPolicy::firstName($row->reporter_name ?? null),
                'phoneLast4' => SafetyPolicy::phoneLast4($row->reporter_phone ?? null),
            ],
            'booking' => $row->booking_id ? ['id' => (int) $row->booking_id, 'publicRef' => $row->booking_ref] : null,
            'location' => [
                'lat' => $row->lat !== null ? (float) $row->lat : null,
                'lng' => $row->lng !== null ? (float) $row->lng : null,
                'text' => $row->location_text ?? null,
                'mapsUrl' => SafetyPolicy::mapsUrl($row->lat, $row->lng),
            ],
            'timestamp' => $row->created_at,
            'emergencyContact' => [
                'name' => $row->emergency_name ?? null,
                'phone' => $ops ? ($row->emergency_phone ?? null) : SafetyPolicy::phoneLast4($row->emergency_phone ?? null),
            ],
            'status' => $row->status,
            'type' => $row->type,
            'kind' => $row->kind ?? null,
            'description' => $row->description,
            'adminNote' => $ops ? ($row->admin_note ?? null) : null,
        ];
    }

    private function resolveLiveBooking(User $operator, ?int $bookingId): ?object
    {
        if ($bookingId) {
            $row = $this->ownedBooking($operator, $bookingId);

            return in_array(strtoupper((string) $row->status), SafetyPolicy::LIVE, true) ? $row : null;
        }
        $q = $this->db()->table('bookings')->whereIn('status', SafetyPolicy::LIVE);
        $this->scopeOwnBookings($q, $operator);

        return $q->orderByDesc('updated_at')->first();
    }

    private function ownedBooking(User $operator, int $id): object
    {
        $q = $this->db()->table('bookings')->where('id', $id);
        $this->scopeOwnBookings($q, $operator);
        $row = $q->first();
        abort_if($row === null, 404, 'Booking not found');

        return $row;
    }

    private function scopeOwnBookings($q, User $operator): void
    {
        $q->where(function ($inner) use ($operator) {
            $inner->where('customer_id', $this->actorId($operator));
            $driverId = $this->driverId($operator);
            if ($driverId) {
                $inner->orWhere('driver_id', $driverId);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureShare(int $bookingId): array
    {
        $row = $this->db()->table('bookings')->where('id', $bookingId)->first();
        if (! $row->share_token || ! $row->share_expires_at || $row->share_expires_at <= now()) {
            $this->db()->table('bookings')->where('id', $bookingId)->update([
                'share_token' => Str::random(32),
                'share_expires_at' => now()->addHours(48),
                'updated_at' => now(),
            ]);
            $row = $this->db()->table('bookings')->where('id', $bookingId)->first();
        }

        return [
            'token' => $row->share_token,
            'url' => url('/safety/share/'.$row->share_token),
            'expiresAt' => $row->share_expires_at,
        ];
    }

    private function shareSms(?string $publicRef, ?string $map, ?string $shareUrl): string
    {
        $parts = ['KarnaCab live trip'];
        if ($publicRef) {
            $parts[] = 'ref '.$publicRef;
        }
        $parts[] = $shareUrl ?: $map;

        return implode('. ', array_filter($parts));
    }

    private function assertRiderOrDriver(User $operator): void
    {
        abort_unless(in_array($operator->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE, OperatorRole::DRIVER], true), 403, 'Customer or driver account required');
    }

    private function actorId(User $operator): int
    {
        return (int) ($operator->nest_user_id ?: $operator->id);
    }

    private function driverId(User $operator): ?int
    {
        if ($operator->role !== OperatorRole::DRIVER) {
            return null;
        }
        $id = $this->db()->table('drivers')->where('user_id', $this->actorId($operator))->value('id');

        return $id ? (int) $id : null;
    }

    private function platformUser(User $operator): object
    {
        return $this->db()->table('users')->where('id', $this->actorId($operator))->first()
            ?? (object) ['emergency_name' => null, 'emergency_phone' => null, 'last_address' => null, 'last_lat' => null, 'last_lng' => null];
    }

    private function lastLat(User $operator): ?float
    {
        $v = $this->platformUser($operator)->last_lat ?? null;

        return $v !== null ? (float) $v : null;
    }

    private function lastLng(User $operator): ?float
    {
        $v = $this->platformUser($operator)->last_lng ?? null;

        return $v !== null ? (float) $v : null;
    }

    private function coord(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function setting(string $key, string $fallback): string
    {
        $row = Schema::connection('platform')->hasTable('system_settings')
            ? $this->db()->table('system_settings')->where('key', $key)->value('value')
            : null;

        return trim((string) ($row ?: $fallback)) ?: $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(User $operator, string $action, mixed $entityId, array $payload): void
    {
        if (! Schema::connection('platform')->hasTable('platform_audit_events')) {
            return;
        }
        $this->db()->table('platform_audit_events')->insert([
            'actor_user_id' => $this->actorId($operator),
            'domain' => 'safety',
            'action' => $action,
            'entity_type' => 'booking',
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'created_at' => now(),
        ]);
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
