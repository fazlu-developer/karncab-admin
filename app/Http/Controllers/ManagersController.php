<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Services\OrganizationAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManagersController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('manager.view') || $request->user()?->isPrivilegedOperator(), 403);

        return view('managers.index', [
            'managers' => User::query()->where('role', OperatorRole::MANAGER)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isPrivilegedOperator(), 403);

        return view('managers.form', [
            'manager' => new User(['role' => OperatorRole::MANAGER, 'status' => 'ACTIVE']),
            'catalog' => $this->managerCatalog(),
            'granted' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isPrivilegedOperator(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['ACTIVE', 'PENDING', 'SUSPENDED'])],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'abilities' => ['array'],
            'abilities.*' => ['string'],
        ]);
        $manager = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => OperatorRole::MANAGER,
            'status' => $data['status'],
            'state_id' => $data['state_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
        ]);
        $manager->syncAbilities($data['abilities'] ?? []);
        OrganizationAudit::record($request->user(), 'manager.created', 'manager', $manager->id, null, [
            'abilities' => $manager->assignedAbilities(),
        ]);

        return redirect()->route('managers.edit', $manager)->with('status', 'Manager created. Permissions are enforced on the server.');
    }

    public function edit(Request $request, User $manager): View
    {
        abort_unless($request->user()?->isPrivilegedOperator(), 403);
        abort_unless($manager->role === OperatorRole::MANAGER, 404);

        return view('managers.form', [
            'manager' => $manager,
            'catalog' => $this->managerCatalog(),
            'granted' => $manager->assignedAbilities(),
        ]);
    }

    public function update(Request $request, User $manager): RedirectResponse
    {
        abort_unless($request->user()?->isPrivilegedOperator(), 403);
        abort_unless($manager->role === OperatorRole::MANAGER, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique(User::class, 'email')->ignore($manager->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['ACTIVE', 'PENDING', 'SUSPENDED'])],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'abilities' => ['array'],
            'abilities.*' => ['string'],
        ]);
        $old = ['abilities' => $manager->assignedAbilities(), 'status' => $manager->status];
        $manager->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
            'state_id' => $data['state_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $manager->password = $data['password'];
        }
        $manager->save();
        $manager->syncAbilities($data['abilities'] ?? []);
        OrganizationAudit::record($request->user(), 'permission.changed', 'manager', $manager->id, $old, [
            'abilities' => $manager->assignedAbilities(),
        ]);

        return back()->with('status', 'Manager permissions updated.');
    }

    /**
     * @return list<string>
     */
    private function managerCatalog(): array
    {
        return array_values(array_filter(
            PlatformPermission::catalog(),
            fn (string $ability) => $ability !== 'platform.admin',
        ));
    }
}
