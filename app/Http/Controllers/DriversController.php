<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformDriverDocument;
use App\Models\Platform\PlatformUser;
use App\Platform\TerritoryScope;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DriversController extends Controller
{
    private const REQUIRED_DOCS = ['LICENSE', 'RC', 'INSURANCE', 'SELFIE', 'ID_PROOF'];

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

        return view('drivers.form', [
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
        $user = PlatformUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => ($data['phone'] ?? null) ?: null,
            'role' => 'DRIVER',
            'status' => $data['status'],
            'password_hash' => Hash::make($data['password']),
            'state_id' => $actor && ! $actor->isPrivilegedOperator() ? $actor->state_id : null,
            'district_id' => $actor && ! $actor->isPrivilegedOperator() ? $actor->district_id : null,
        ]);
        $driver = PlatformDriver::query()->create([
            'user_id' => $user->id,
            'license_no' => $data['license_no'],
            'kyc_status' => $data['kyc_status'],
            'online' => (bool) ($data['online'] ?? false),
            'fleet_owner_id' => $actor?->fleet_owner_id,
        ]);

        return redirect()->route('drivers.show', $driver)->with('status', 'Driver created.');
    }

    public function show(Request $request, PlatformDriver $driver): View
    {
        abort_unless($request->user()?->can('drivers.view'), 403);
        $this->assertVisible($request, $driver);
        $driver->load(['user', 'documents', 'vehicles']);

        return view('drivers.show', [
            'driver' => $driver,
            'requiredDocs' => self::REQUIRED_DOCS,
        ]);
    }

    public function edit(Request $request, PlatformDriver $driver): View
    {
        abort_unless($request->user()?->can('drivers.edit'), 403);
        $this->assertVisible($request, $driver);
        $driver->load('user');

        return view('drivers.form', [
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
        $driver->user?->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => ($data['phone'] ?? null) ?: null,
            'status' => $data['status'],
        ] + (! empty($data['password']) ? ['password_hash' => Hash::make($data['password'])] : []));
        $driver->update([
            'license_no' => $data['license_no'],
            'kyc_status' => $data['kyc_status'],
            'online' => (bool) ($data['online'] ?? false),
        ]);

        return redirect()->route('drivers.show', $driver)->with('status', 'Driver updated.');
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
        ]);
        if ($data['status'] === 'verified') {
            abort_unless($request->user()?->can('drivers.approve'), 403);
            $driver->load('documents');
            $missing = collect(self::REQUIRED_DOCS)->filter(function (string $type) use ($driver) {
                $doc = $driver->documents->firstWhere('type', $type);

                return ! $doc || $doc->status !== 'verified';
            });
            if ($missing->isNotEmpty()) {
                return back()->withErrors([
                    'status' => 'Approve every required file first: '.$missing->implode(', '),
                ]);
            }
            $driver->update([
                'kyc_status' => 'verified',
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
            fn (string $type) => ($byType[$type]->status ?? null) === 'verified',
        );
        if ($allVerified) {
            $driver->update([
                'kyc_status' => 'verified',
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
        if ($kyc === 'verified' && $previous !== 'verified') {
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
            'kyc_status' => ['required', Rule::in(['pending', 'under_review', 'verified', 'rejected'])],
            'online' => ['nullable', 'boolean'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
        ]);
    }
}
