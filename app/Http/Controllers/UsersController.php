<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformUser;
use App\Models\User;
use App\Platform\ExclusiveDistrictSeat;
use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Platform\TerritoryScope;
use App\Services\DistrictHeadKycStore;
use App\Services\OperatorProvisioner;
use App\Services\StateHeadKycStore;
use App\Services\StateHeadSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('users.view'), 403);

        $query = PlatformUser::query()
            ->leftJoin('states', 'states.id', '=', 'users.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'users.district_id')
            ->select('users.*', 'states.name as state_name', 'districts.name as district_name')
            ->orderByDesc('users.id');
        TerritoryScope::applyUsers($query, $request->user());
        TerritoryScope::applyAdminGeo(
            $query,
            $request->user(),
            $request->integer('state_id') ?: null,
            $request->integer('district_id') ?: null,
        );
        $actor = $request->user();
        if ($actor?->isStateHead() && $request->integer('district_id')) {
            $districtId = $request->integer('district_id');
            if (in_array($districtId, TerritoryScope::districtIdsForState((int) $actor->state_id), true)) {
                $query->where('users.district_id', $districtId);
            }
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%");
            });
        }
        $allowedRoles = $this->roleChoices($actor);
        if ($actor?->isStateHead()) {
            $query->whereIn('users.role', $allowedRoles);
        }
        $picked = array_values(array_intersect(
            array_filter((array) $request->input('roles', [])),
            $allowedRoles,
        ));
        $picked = array_slice($picked, 0, 2);
        if ($picked === [] && $request->filled('role') && in_array((string) $request->get('role'), $allowedRoles, true)) {
            $picked = [(string) $request->get('role')];
        }
        if ($picked !== []) {
            $query->whereIn('users.role', $picked);
        }
        if ($status = $request->get('status')) {
            $query->where('users.status', $status);
        }

        return view('users.index', [
            'users' => $query->paginate(12)->withQueryString(),
            'filters' => [
                'q' => $request->get('q'),
                'roles' => $picked,
                'status' => $request->get('status'),
                'state_id' => $request->integer('state_id') ?: null,
                'district_id' => $request->integer('district_id') ?: null,
            ],
            'canSwitchGeo' => $actor?->isPrivilegedOperator() || $actor?->isStateHead(),
            'roles' => $allowedRoles,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('users.create'), 403);

        return view('users.form', [
            'user' => new PlatformUser(['status' => 'ACTIVE', 'role' => 'CUSTOMER']),
            'roles' => $this->roleChoices($request->user()),
            'permissionCatalog' => $this->permissionCatalog(),
            'navPermissions' => $this->navPermissions(),
            'granted' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.create'), 403);

        return $this->withSeatMessage(function () use ($request) {
            return $this->storeUser($request);
        });
    }

    private function storeUser(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['role'] = OperatorRole::normalize((string) $data['role']);
        $data['phone'] = ($data['phone'] ?? null) ?: null;
        $password = (string) $data['password'];
        $data['password_hash'] = Hash::make($password);
        unset($data['password']);
        $actor = $request->user();
        $this->lockActorTerritory($actor, $data);
        $this->assertTerritory($data, null);
        $user = PlatformUser::query()->create($data);
        $this->saveRoleKyc($request, $user);
        app(OperatorProvisioner::class)->sync($user, $password, $actor, (array) $request->input('abilities', []));

        return redirect()->route('users.show', $user)->with('status', $this->createdStatus($user));
    }

    public function storeStateHead(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.create'), 403);
        $request->merge(['role' => OperatorRole::STATE_HEAD]);
        $data = $this->validated($request, true);
        $data['phone'] = ($data['phone'] ?? null) ?: null;
        $password = (string) $data['password'];
        $data['password_hash'] = Hash::make($password);
        unset($data['password']);
        $data['district_id'] = null;
        $this->assertTerritory($data, null);
        $user = PlatformUser::query()->create($data);
        $this->saveRoleKyc($request, $user);
        app(OperatorProvisioner::class)->sync($user, $password, $request->user(), []);

        return redirect()->route('ops.module', 'state-heads')->with('status', 'State Head created. They can sign in with this email and password.');
    }

    public function storeDistrictHead(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.create'), 403);
        $request->merge(['role' => OperatorRole::DISTRICT_HEAD]);
        $data = $this->validated($request, true);
        $data['phone'] = ($data['phone'] ?? null) ?: null;
        $password = (string) $data['password'];
        $data['password_hash'] = Hash::make($password);
        unset($data['password']);
        $districtId = (int) ($data['district_id'] ?? 0);
        abort_unless($districtId > 0, 422, 'A District Head must be assigned one district.');
        if (Schema::connection('platform')->hasTable('districts')) {
            $stateId = DB::connection('platform')->table('districts')->where('id', $districtId)->value('state_id');
            if ($stateId) {
                $data['state_id'] = (int) $stateId;
            }
        }
        $this->assertTerritory($data, null);
        $user = PlatformUser::query()->create($data);
        $this->saveRoleKyc($request, $user);
        app(OperatorProvisioner::class)->sync($user, $password, $request->user(), []);

        return redirect()->route('ops.module', 'district-heads')->with('status', 'District Head created. They hold the exclusive district seat and can sign in with this email and password.');
    }

    public function show(Request $request, PlatformUser $platformUser): View
    {
        abort_unless($request->user()?->can('users.view'), 403);
        $this->assertVisible($request, $platformUser);
        $platformUser->load('driver');
        $operator = User::query()->where('nest_user_id', $platformUser->id)->orWhere('email', $platformUser->email)->first();

        return view('users.show', [
            'user' => $platformUser,
            'operator' => $operator,
            'kyc' => $this->kycFor($platformUser),
        ]);
    }

    public function edit(Request $request, PlatformUser $platformUser): View
    {
        abort_unless($request->user()?->can('users.edit'), 403);
        $this->assertVisible($request, $platformUser);

        $operator = User::query()->where('nest_user_id', $platformUser->id)->orWhere('email', $platformUser->email)->first();

        return view('users.form', [
            'user' => $platformUser,
            'roles' => $this->roleChoices($request->user(), (string) $platformUser->role),
            'permissionCatalog' => $this->permissionCatalog(),
            'navPermissions' => $this->navPermissions(),
            'granted' => $operator?->assignedAbilities() ?? [],
        ]);
    }

    public function update(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        abort_unless($request->user()?->can('users.edit'), 403);
        $this->assertVisible($request, $platformUser);

        return $this->withSeatMessage(function () use ($request, $platformUser) {
            return $this->updateUser($request, $platformUser);
        });
    }

    private function updateUser(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        $data = $this->validated($request, false, $platformUser->id);
        $data['role'] = OperatorRole::normalize((string) $data['role']);
        $data['phone'] = ($data['phone'] ?? null) ?: null;
        $password = $data['password'] ?? null;
        if (! empty($password)) {
            $data['password_hash'] = Hash::make($password);
        }
        unset($data['password']);
        $this->lockActorTerritory($request->user(), $data);
        $this->assertTerritory($data, $platformUser->id);
        $platformUser->update($data);
        $this->saveRoleKyc($request, $platformUser->fresh());
        app(OperatorProvisioner::class)->sync(
            $platformUser->fresh(),
            is_string($password) && $password !== '' ? $password : null,
            $request->user(),
            (array) $request->input('abilities', []),
        );

        return redirect()->route('users.show', $platformUser)->with('status', 'User updated. Console login uses this email and password.');
    }

    public function destroy(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        abort_unless($request->user()?->can('users.delete'), 403);
        abort_if($platformUser->role === OperatorRole::SUPER_ADMIN, 403, 'Super admin accounts cannot be deleted.');
        $this->assertVisible($request, $platformUser);
        $platformUser->driver?->delete();
        $platformUser->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    private function withSeatMessage(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'state_id' => $exception->getMessage(),
                'district_id' => $exception->getMessage(),
            ]);
        }
    }

    private function assertVisible(Request $request, PlatformUser $platformUser): void
    {
        $visible = PlatformUser::query()->whereKey($platformUser->id);
        TerritoryScope::applyUsers($visible, $request->user());
        abort_unless($visible->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique(PlatformUser::class, 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique(PlatformUser::class, 'phone')->ignore($id)],
            'role' => ['required', Rule::in($this->roleChoices($request->user(), $id ? (string) PlatformUser::query()->whereKey($id)->value('role') : null))],
            'status' => ['required', Rule::in(['ACTIVE', 'PENDING', 'SUSPENDED'])],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockActorTerritory(?User $actor, array &$data): void
    {
        if (! $actor || $actor->isPrivilegedOperator()) {
            return;
        }
        if ($actor->isDistrictHead()) {
            $data['state_id'] = $actor->state_id;
            $data['district_id'] = $actor->district_id;

            return;
        }
        if ($actor->isStateHead()) {
            $data['state_id'] = $actor->state_id;
            $districtId = (int) ($data['district_id'] ?? 0);
            if ($districtId > 0 && ! in_array($districtId, TerritoryScope::districtIdsForState((int) $actor->state_id), true)) {
                $data['district_id'] = null;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTerritory(array &$data, ?int $exceptId): void
    {
        $role = (string) ($data['role'] ?? '');
        if (in_array($role, [OperatorRole::FLEET_OWNER, OperatorRole::DISTRICT_HEAD], true)) {
            abort_unless((int) ($data['district_id'] ?? 0) > 0, 422, 'Select the district this account belongs to.');
        }
        if ($role === OperatorRole::STATE_HEAD) {
            $stateId = (int) ($data['state_id'] ?? 0);
            abort_unless($stateId > 0, 422, 'A State Head must be assigned one state.');
            if (($data['status'] ?? '') === 'ACTIVE') {
                $opsId = $exceptId
                    ? User::query()->where('nest_user_id', $exceptId)->value('id')
                    : null;
                app(StateHeadSeat::class)->assertAvailable($stateId, $opsId ? (int) $opsId : null);
            }
        }
        $this->assertDistrictHeadSeat($data, $exceptId);
    }

    private function assertDistrictHeadSeat(array &$data, ?int $exceptId): void
    {
        if (($data['role'] ?? '') !== OperatorRole::DISTRICT_HEAD) {
            return;
        }
        $districtId = (int) ($data['district_id'] ?? 0);
        abort_unless($districtId > 0, 422, 'Select a district. A District Head signs in for that district.');
        $stateId = (int) DB::connection('platform')->table('districts')->where('id', $districtId)->value('state_id');
        abort_unless($stateId > 0, 422, 'Select a district. A District Head signs in for that district.');
        $data['district_id'] = $districtId;
        $data['state_id'] = $stateId;
        if (($data['status'] ?? '') === 'ACTIVE') {
            ExclusiveDistrictSeat::assertAvailable($districtId, $exceptId);
        }
    }

    private function saveRoleKyc(Request $request, PlatformUser $user): void
    {
        if ($user->role === OperatorRole::DISTRICT_HEAD) {
            DistrictHeadKycStore::saveFromRequest($request, (int) $user->id, $request->user()?->id);
        }
        if ($user->role === OperatorRole::STATE_HEAD) {
            StateHeadKycStore::saveFromRequest($request, (int) $user->id, $request->user()?->id);
        }
    }

    private function createdStatus(PlatformUser $user): string
    {
        if (! in_array($user->role, OperatorProvisioner::CONSOLE_ROLES, true)) {
            return 'User created.';
        }

        return 'User created. They can sign in to the admin with this email and password.';
    }

    /**
     * Roles offered in the users list and form. Super Admin is not a choice.
     * A State Head only works with district heads, fleet owners, drivers, and customers.
     *
     * @return list<string>
     */
    private function roleChoices(?User $actor, ?string $current = null): array
    {
        if ($actor?->isDistrictHead()) {
            return [
                OperatorRole::FLEET_OWNER,
                OperatorRole::DRIVER,
                OperatorRole::CUSTOMER,
            ];
        }
        if ($actor?->isStateHead()) {
            return [
                OperatorRole::DISTRICT_HEAD,
                OperatorRole::FLEET_OWNER,
                OperatorRole::DRIVER,
                OperatorRole::CUSTOMER,
            ];
        }

        $roles = array_values(array_filter(
            OperatorRole::managed(),
            fn (string $role) => $role !== OperatorRole::SUPER_ADMIN,
        ));
        if ($current === OperatorRole::SUPER_ADMIN) {
            $roles[] = OperatorRole::SUPER_ADMIN;
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function permissionCatalog(): array
    {
        return array_values(array_filter(
            PlatformPermission::catalog(),
            fn (string $ability) => $ability !== 'platform.admin',
        ));
    }

    /**
     * @return array<string, string>
     */
    private function navPermissions(): array
    {
        $map = [];
        foreach (\App\Platform\OpsNav::coreItems() as $item) {
            $ability = (string) ($item['ability'] ?? '');
            $key = (string) ($item['key'] ?? '');
            if ($ability === '' || $ability === 'platform.admin' || isset(\App\Platform\PrivilegedModules::LABELS[$ability])) {
                continue;
            }
            if (in_array($key, \App\Platform\PrivilegedModules::HEAD_HIDDEN, true)) {
                continue;
            }
            if (! isset($map[$ability])) {
                $map[$ability] = (string) $item['label'];
            }
        }

        return $map;
    }

    /**
     * @return list<object>
     */
    private function kycFor(PlatformUser $user): array
    {
        $table = match ($user->role) {
            OperatorRole::STATE_HEAD => 'state_head_kyc',
            OperatorRole::DISTRICT_HEAD => 'district_head_kyc',
            default => null,
        };
        if ($table === null || ! Schema::connection('platform')->hasTable($table)) {
            return [];
        }

        return DB::connection('platform')->table($table)->where('user_id', $user->id)->orderByDesc('id')->get()->all();
    }
}
