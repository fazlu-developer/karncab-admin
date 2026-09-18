<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformDriverDocument;
use App\Models\Platform\PlatformUser;
use App\Platform\GeoCatalog;
use App\Platform\RideCatalog;
use App\Platform\TerritoryScope;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DriversController extends Controller
{
    private const REQUIRED_DOCS = [
        'AADHAAR_FRONT', 'AADHAAR_BACK', 'PAN', 'LICENSE_FRONT', 'LICENSE_BACK',
        'RC', 'INSURANCE', 'SELFIE',
    ];

    private const DOC_TYPES = [
        'AADHAAR_FRONT', 'AADHAAR_BACK', 'PAN', 'LICENSE_FRONT', 'LICENSE_BACK',
        'RC', 'INSURANCE', 'VEHICLE_PHOTO', 'VEHICLE_DRIVER_PHOTO', 'SELFIE',
        'POLLUTION', 'PERMIT', 'FITNESS', 'PUC',
    ];

    private const MULTI_DOCS = ['VEHICLE_PHOTO', 'VEHICLE_DRIVER_PHOTO'];

    private const DATED_DOCS = ['LICENSE', 'LICENSE_FRONT', 'LICENSE_BACK', 'INSURANCE', 'POLLUTION', 'PUC', 'PERMIT'];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('drivers.view'), 403);

        $query = PlatformDriver::query()->with('user')->orderByDesc('id');
        TerritoryScope::applyDrivers($query, $request->user());
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('license_no', 'like', "%{$search}%")
                    ->orWhere('kyc_status', 'like', "%{$search}%");
            });
        }
        if ($status = $request->get('kyc')) {
            $query->where('kyc_status', $status);
        }
        if ($request->get('online') === '1') {
            $query->where('online', true);
        }
        if ($request->get('online') === '0') {
            $query->where('online', false);
        }

        return view('drivers.index', [
            'drivers' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['q', 'kyc', 'online']),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('drivers.create') || $request->user()?->can('drivers.edit'), 403);

        return view('drivers.form', $this->formMeta() + [
            'driver' => new PlatformDriver(['kyc_status' => 'pending', 'online' => false]),
            'user' => new PlatformUser(['status' => 'ACTIVE']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.create') || $request->user()?->can('drivers.edit'), 403);
        $data = $this->validated($request, true);
        $this->assertCanSetKyc($request, $data['kyc_status']);
        $actor = $request->user();
        $geo = $this->resolvedGeo($request, $data);
        $user = PlatformUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => ($data['phone'] ?? null) ?: null,
            'role' => 'DRIVER',
            'status' => $data['status'],
            'password_hash' => Hash::make($data['password']),
            'state_id' => $geo['state_id'],
            'district_id' => $geo['district_id'],
        ]);
        $type = $data['driver_type'] ?? (($actor?->fleet_owner_id && ($data['driver_type'] ?? '') !== 'individual_driver') ? 'fleet_driver' : 'individual_driver');
        $fleetOwnerId = $type === 'fleet_driver'
            ? ($data['fleet_owner_id'] ?? $actor?->fleet_owner_id)
            : null;
        if ($type === 'fleet_driver' && $actor?->isFleetOwner()) {
            $fleetOwnerId = $actor->fleet_owner_id;
        }
        $driverPayload = [
            'user_id' => $user->id,
            'license_no' => $data['license_no'],
            'kyc_status' => $data['kyc_status'],
            'online' => (bool) ($data['online'] ?? false),
            'fleet_owner_id' => $fleetOwnerId,
        ];
        if (Schema::connection('platform')->hasColumn('drivers', 'driver_type')) {
            $driverPayload['driver_type'] = $fleetOwnerId ? 'fleet_driver' : 'individual_driver';
            $driverPayload['state_id'] = $user->state_id;
            $driverPayload['district_id'] = $user->district_id;
        }
        $driver = PlatformDriver::query()->create($driverPayload);
        \App\Services\OrganizationAudit::record($actor, 'driver.created', 'driver', $driver->id, null, [
            'driver_type' => $fleetOwnerId ? 'fleet_driver' : 'individual_driver',
            'fleet_owner_id' => $fleetOwnerId,
        ]);

        return redirect()->route('drivers.show', $driver)->with('status', 'Driver created.');
    }

    public function show(Request $request, PlatformDriver $driver): View
    {
        abort_unless($request->user()?->can('drivers.view'), 403);
        $this->assertVisible($request, $driver);
        $driver->load(['user', 'documents', 'vehicles']);

        return view('drivers.show', $this->formMeta() + [
            'driver' => $driver,
            'requiredDocs' => self::REQUIRED_DOCS,
            'docTypes' => self::DOC_TYPES,
            'stateName' => GeoCatalog::stateName((int) ($driver->user?->state_id ?: $driver->state_id)),
            'districtName' => GeoCatalog::districtName((int) ($driver->user?->district_id ?: $driver->district_id)),
        ]);
    }

    public function edit(Request $request, PlatformDriver $driver): View
    {
        abort_unless($request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $driver->load('user');

        return view('drivers.form', $this->formMeta() + [
            'driver' => $driver,
            'user' => $driver->user,
        ]);
    }

    public function update(Request $request, PlatformDriver $driver): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $driver->load('user');
        $data = $this->validated($request, false, $driver->user?->id);
        $this->assertCanSetKyc($request, $data['kyc_status'], $driver->kyc_status);
        $geo = $this->resolvedGeo($request, $data);
        $userPatch = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => ($data['phone'] ?? null) ?: null,
            'status' => $data['status'],
            'state_id' => $geo['state_id'],
            'district_id' => $geo['district_id'],
        ];
        if (! empty($data['password'])) {
            $userPatch['password_hash'] = Hash::make($data['password']);
        }
        $driver->user?->update($userPatch);
        $driverPatch = [
            'license_no' => $data['license_no'],
            'kyc_status' => $data['kyc_status'],
            'online' => (bool) ($data['online'] ?? false),
        ];
        if (array_key_exists('fleet_owner_id', $data) && Schema::connection('platform')->hasColumn('drivers', 'fleet_owner_id')) {
            $type = $data['driver_type'] ?? ($data['fleet_owner_id'] ? 'fleet_driver' : 'individual_driver');
            $driverPatch['fleet_owner_id'] = $type === 'fleet_driver' ? ($data['fleet_owner_id'] ?: null) : null;
            if (Schema::connection('platform')->hasColumn('drivers', 'driver_type')) {
                $driverPatch['driver_type'] = $driverPatch['fleet_owner_id'] ? 'fleet_driver' : 'individual_driver';
            }
        }
        if (Schema::connection('platform')->hasColumn('drivers', 'state_id')) {
            $driverPatch['state_id'] = $geo['state_id'];
            $driverPatch['district_id'] = $geo['district_id'];
        }
        $driver->update($driverPatch);

        return redirect()->route('drivers.show', $driver)->with('status', 'Driver updated.');
    }

    public function updateOnboarding(Request $request, PlatformDriver $driver): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $driver->load('user');
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'last_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'license_no' => ['nullable', 'string', 'max:40'],
            'license_expires_at' => ['nullable', 'date'],
            'vehicle_family' => ['nullable', Rule::in(['BIKE', 'AUTO', 'CAR', 'CAB'])],
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'aadhaar' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:16'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'upi_id' => ['nullable', 'string', 'max:80'],
            'registration_no' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', Rule::in(array_keys(RideCatalog::VEHICLES))],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'fuel' => ['nullable', 'string', 'max:20'],
            'driver_type' => ['nullable', Rule::in(['fleet_driver', 'individual_driver'])],
            'fleet_owner_id' => ['nullable', 'integer'],
        ]);
        $geo = $this->resolvedGeo($request, $data);
        if ($geo['district_id'] && ! ($data['city'] ?? null)) {
            $data['city'] = GeoCatalog::districtName((int) $geo['district_id']) ?: ($data['city'] ?? null);
        }
        $userPatch = array_filter([
            'name' => $data['name'] ?? null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'email' => $data['email'] ?? null,
            'last_address' => $data['last_address'] ?? null,
            'state_id' => $geo['state_id'],
            'district_id' => $geo['district_id'],
        ], fn ($value) => $value !== null && $value !== '');
        $this->assignExisting($driver->user, $userPatch + $this->onlyExisting('users', [
            'gender' => isset($data['gender']) ? strtoupper((string) $data['gender']) : null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'emergency_name' => $data['emergency_name'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
        ]));

        $driverPatch = $this->onlyExisting('drivers', [
            'license_no' => $data['license_no'] ?? null,
            'license_expires_at' => $data['license_expires_at'] ?? null,
            'vehicle_family' => $data['vehicle_family'] ?? null,
            'city' => $data['city'] ?? null,
            'emergency_name' => $data['emergency_name'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'bank_account_holder' => $data['bank_account_holder'] ?? null,
            'bank_ifsc' => isset($data['bank_ifsc']) ? strtoupper((string) $data['bank_ifsc']) : null,
            'upi_id' => $data['upi_id'] ?? null,
            'state_id' => $geo['state_id'],
            'district_id' => $geo['district_id'],
            'fleet_owner_id' => (($data['driver_type'] ?? '') === 'fleet_driver') ? ($data['fleet_owner_id'] ?? null) : ((($data['driver_type'] ?? '') === 'individual_driver') ? null : ($data['fleet_owner_id'] ?? null)),
            'driver_type' => $data['driver_type'] ?? null,
        ]);
        if (! empty($data['aadhaar'])) {
            $aadhaar = preg_replace('/\D+/', '', (string) $data['aadhaar']) ?? '';
            abort_unless(preg_match('/^\d{12}$/', $aadhaar), 422, 'Enter a valid 12-digit Aadhaar number');
            $driverPatch = array_merge($driverPatch, $this->onlyExisting('drivers', [
                'aadhaar_last4' => substr($aadhaar, -4),
                'aadhaar_hash' => hash('sha256', $aadhaar),
                'id_type' => 'AADHAAR',
                'id_last4' => substr($aadhaar, -4),
            ]));
        }
        if (! empty($data['pan'])) {
            $pan = strtoupper(preg_replace('/\s+/', '', (string) $data['pan']) ?? '');
            abort_unless(preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan), 422, 'Enter a valid PAN');
            $driverPatch = array_merge($driverPatch, $this->onlyExisting('drivers', [
                'pan_last4' => substr($pan, -4),
                'pan_hash' => hash('sha256', $pan),
            ]));
        }
        if (! empty($data['account_number'])) {
            $acct = preg_replace('/\D+/', '', (string) $data['account_number']) ?? '';
            $driverPatch = array_merge($driverPatch, $this->onlyExisting('drivers', [
                'bank_account_last4' => substr($acct, -4),
                'bank_account_hash' => hash('sha256', $acct),
            ]));
        }
        if ($driverPatch) {
            $driver->update($driverPatch);
        }
        $this->upsertVehicle($driver, $data, $geo);

        return redirect()->route('drivers.show', $driver)->with('status', 'Onboarding information updated.');
    }

    public function storeDocument(Request $request, PlatformDriver $driver): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $data = $request->validate([
            'type' => ['required', Rule::in(self::DOC_TYPES)],
            'file' => ['required', 'file', 'max:8192'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $type = strtoupper($data['type']);
        if (in_array($type, self::DATED_DOCS, true) && blank($data['expires_at'] ?? null)) {
            return back()->withErrors(['expires_at' => 'Add the expiry date for this document.']);
        }
        $file = $request->file('file');
        abort_unless($file, 422, 'Upload a file.');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $ext = $file->getClientOriginalExtension() ?: (str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'pdf') ? 'pdf' : 'jpg'));
        $path = 'kyc/'.$driver->id.'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()) ?: '');
        if (! in_array($type, self::MULTI_DOCS, true)) {
            PlatformDriverDocument::query()->where('driver_id', $driver->id)->where('type', $type)->delete();
        }
        PlatformDriverDocument::query()->create([
            'driver_id' => $driver->id,
            'type' => $type,
            'status' => 'pending',
            'storage_key' => $path,
            'original_name' => substr($file->getClientOriginalName() ?: $type, 0, 180),
            'mime' => substr($mime, 0, 80),
            'size_bytes' => $file->getSize() ?: 0,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return redirect()->route('drivers.show', $driver)->with('status', 'Attachment uploaded.');
    }

    public function documentFile(Request $request, PlatformDriver $driver, PlatformDriverDocument $document): Response|RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.view'), 403);
        $this->assertVisible($request, $driver);
        abort_unless((int) $document->driver_id === (int) $driver->id, 404);
        $key = (string) $document->storage_key;
        abort_if($key === '', 404);
        if (Storage::disk('public')->exists($key)) {
            return response()->file(Storage::disk('public')->path($key), [
                'Content-Type' => $document->mime ?: 'application/octet-stream',
            ]);
        }
        $url = $document->externalUrl();
        abort_unless($url, 404);

        return redirect()->away($url);
    }

    public function reviewDocument(Request $request, PlatformDriver $driver, PlatformDriverDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.edit') || $request->user()?->can('drivers.approve'), 403);
        $this->assertVisible($request, $driver);
        abort_unless((int) $document->driver_id === (int) $driver->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'under_review'])],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['status'] === 'rejected' && blank($data['rejection_reason'] ?? null)) {
            return back()->withErrors(['rejection_reason' => 'Add a rejection reason.']);
        }
        $document->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? trim((string) $data['rejection_reason']) : null,
            'reviewed_at' => now(),
        ]);
        $this->syncApplication($driver);

        return redirect()->route('drivers.show', $driver)->with('status', 'Document '.$data['status'].'.');
    }

    public function reviewApplication(Request $request, PlatformDriver $driver): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.approve') || $request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'under_review'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'force' => ['nullable', 'boolean'],
        ]);
        if ($data['status'] === 'verified') {
            abort_unless($request->user()?->can('drivers.approve'), 403);
            $driver->load('documents');
            $missing = collect(self::REQUIRED_DOCS)->filter(function (string $type) use ($driver) {
                $doc = $driver->documents->firstWhere('type', $type);

                return ! $doc || ! in_array($doc->status, ['verified', 'approved'], true);
            });
            if ($missing->isNotEmpty() && ! $request->boolean('force')) {
                return back()->withErrors([
                    'status' => 'Missing verified files: '.$missing->implode(', ').'. Tick “Approve anyway” if you have reviewed the profile.',
                ]);
            }
            $driver->update([
                'kyc_status' => 'approved',
                'kyc_rejected_reason' => null,
            ]);
            $driver->user?->update(['status' => 'ACTIVE']);
            try {
                app(NotificationService::class)->dispatch((int) $driver->user_id, 'kyc_approval', [], [
                    'entity' => ['type' => 'kyc', 'id' => (string) $driver->user_id],
                ]);
            } catch (\Throwable) {
                /* notification delivery is optional */
            }
        } elseif ($data['status'] === 'rejected') {
            $driver->update([
                'kyc_status' => 'rejected',
                'kyc_rejected_reason' => $data['reason'] ?: 'Application rejected',
                'online' => false,
            ]);
            $driver->user?->update(['status' => 'SUSPENDED']);
            try {
                app(NotificationService::class)->dispatch((int) $driver->user_id, 'kyc_rejection', [
                    'reason' => $data['reason'] ?: 'Application rejected',
                ], ['entity' => ['type' => 'kyc', 'id' => (string) $driver->user_id]]);
            } catch (\Throwable) {
                /* notification delivery is optional */
            }
        } else {
            $driver->update(['kyc_status' => 'under_review']);
        }

        return redirect()->route('drivers.show', $driver)->with('status', 'Driver KYC updated. The driver app will refresh to home after approval.');
    }

    public function destroy(Request $request, PlatformDriver $driver): RedirectResponse
    {
        abort_unless($request->user()?->can('drivers.delete') || $request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $user = $driver->user;
        $driver->delete();
        $user?->update(['role' => 'CUSTOMER']);

        return redirect()->route('drivers.index')->with('status', 'Driver profile removed.');
    }

    private function assertVisible(Request $request, PlatformDriver $driver): void
    {
        $visible = PlatformDriver::query()->whereKey($driver->id);
        TerritoryScope::applyDrivers($visible, $request->user());
        abort_unless($visible->exists(), 404);
    }

    private function syncApplication(PlatformDriver $driver): void
    {
        $driver->load('documents');
        $byType = $driver->documents->keyBy('type');
        $allVerified = collect(self::REQUIRED_DOCS)->every(
            fn (string $type) => ($byType[$type]->status ?? null) === 'verified' || ($byType[$type]->status ?? null) === 'approved',
        );
        if ($allVerified) {
            $driver->update([
                'kyc_status' => 'approved',
                'kyc_rejected_reason' => null,
            ]);
            $driver->user?->update(['status' => 'ACTIVE']);
            try {
                app(NotificationService::class)->dispatch((int) $driver->user_id, 'kyc_approval', [], [
                    'entity' => ['type' => 'kyc', 'id' => (string) $driver->user_id],
                ]);
            } catch (\Throwable) {
                /* notification delivery is optional */
            }
        }
    }

    private function assertCanSetKyc(Request $request, string $kyc, ?string $previous = null): void
    {
        if ($kyc === 'verified' || $kyc === 'approved') {
            abort_unless($request->user()?->can('drivers.approve'), 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating, ?int $userId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique(PlatformUser::class, 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique(PlatformUser::class, 'phone')->ignore($userId)],
            'status' => ['required', Rule::in(['ACTIVE', 'PENDING', 'SUSPENDED'])],
            'license_no' => ['required', 'string', 'max:40'],
            'kyc_status' => ['required', Rule::in(['pending', 'under_review', 'verified', 'approved', 'rejected'])],
            'online' => ['nullable', 'boolean'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'driver_type' => ['nullable', Rule::in(['fleet_driver', 'individual_driver'])],
            'fleet_owner_id' => ['nullable', 'integer'],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{state_id: ?int, district_id: ?int}
     */
    private function resolvedGeo(Request $request, array $data): array
    {
        $actor = $request->user();
        if ($actor && ! $actor->isPrivilegedOperator()) {
            return [
                'state_id' => $actor->state_id ? (int) $actor->state_id : null,
                'district_id' => $actor->district_id ? (int) $actor->district_id : (($data['district_id'] ?? null) ? (int) $data['district_id'] : null),
            ];
        }
        $stateId = ($data['state_id'] ?? null) !== null && $data['state_id'] !== '' ? (int) $data['state_id'] : null;
        $districtId = ($data['district_id'] ?? null) !== null && $data['district_id'] !== '' ? (int) $data['district_id'] : null;
        if ($stateId && $districtId) {
            $district = DB::connection('platform')->table('districts')->where('id', $districtId)->first();
            abort_unless($district && (int) $district->state_id === $stateId, 422, 'District does not belong to the selected state.');
        }

        return ['state_id' => $stateId, 'district_id' => $districtId];
    }

    /**
     * @return array<string, mixed>
     */
    private function formMeta(): array
    {
        $fleets = [];
        if (Schema::connection('platform')->hasTable('fleet_owners')) {
            $fleets = DB::connection('platform')->table('fleet_owners')
                ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
                ->orderBy('fleet_owners.trade_name')
                ->limit(300)
                ->get(['fleet_owners.id', 'fleet_owners.trade_name', 'users.name as owner_name']);
        }

        return [
            'fleetOwners' => $fleets,
            'vehicleCatalog' => RideCatalog::VEHICLES,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function onlyExisting(string $table, array $values): array
    {
        $out = [];
        foreach ($values as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (Schema::connection('platform')->hasColumn($table, $column)) {
                $out[$column] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assignExisting(?PlatformUser $user, array $values): void
    {
        if (! $user || $values === []) {
            return;
        }
        $user->update($this->onlyExisting('users', $values));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{state_id: ?int, district_id: ?int}  $geo
     */
    private function upsertVehicle(PlatformDriver $driver, array $data, array $geo): void
    {
        $reg = strtoupper(preg_replace('/\s+/', '', (string) ($data['registration_no'] ?? '')) ?? '');
        $category = strtoupper((string) ($data['category'] ?? ''));
        if ($reg === '' && $category === '') {
            return;
        }
        $vehicle = $driver->vehicles()->first();
        $payload = $this->onlyExisting('vehicles', [
            'registration_no' => $reg !== '' ? $reg : ($vehicle->registration_no ?? null),
            'category' => $category !== '' ? $category : ($vehicle->category ?? null),
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'fuel' => $data['fuel'] ?? null,
            'driver_id' => $driver->id,
            'state_id' => $geo['state_id'],
            'district_id' => $geo['district_id'],
            'individual_driver_id' => $driver->fleet_owner_id ? null : $driver->id,
            'status' => $vehicle->status ?? 'ACTIVE',
        ]);
        if ($vehicle) {
            $vehicle->update($payload);
        } else {
            abort_unless(! empty($payload['registration_no']), 422, 'Registration number is required to add a vehicle.');
            \App\Models\Platform\PlatformVehicle::query()->create($payload);
        }
    }
}
