<?php

namespace App\Http\Controllers;

use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use App\Services\AclCatalog;
use App\Services\OrganizationAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessControlController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        AclCatalog::catalog();
        $roles = array_values(array_filter(
            OperatorRole::all(),
            fn (string $role) => $role !== OperatorRole::PENDING,
        ));
        $selected = strtoupper((string) $request->query('role', OperatorRole::MANAGER));
        if (! in_array($selected, $roles, true)) {
            $selected = OperatorRole::MANAGER;
        }
        $modules = AclCatalog::modules();
        $abilities = [];
        foreach (AclCatalog::abilitiesFor() as $row) {
            $abilities[(int) $row->module_id][] = $row;
        }
        $granted = PlatformPermission::forRole($selected);

        return view('ops.roles', [
            'roles' => $roles,
            'selectedRole' => $selected,
            'modules' => $modules,
            'abilitiesByModule' => $abilities,
            'granted' => $granted,
            'locked' => $selected === OperatorRole::SUPER_ADMIN,
        ]);
    }

    public function storeModule(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'nav_group' => ['nullable', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:40'],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', 'max:40'],
        ]);
        $id = AclCatalog::createModule(
            $data['key'],
            $data['label'],
            (string) ($data['nav_group'] ?? 'Operations'),
            (string) ($data['icon'] ?? 'layout-grid'),
            array_values($data['actions'] ?? ['view', 'create', 'edit', 'delete']),
        );
        OrganizationAudit::record($request->user(), 'acl.module.create', 'acl_module', $id, null, $data, 'access');

        return back()->with('status', 'Module created. Assign its permissions to roles below.');
    }

    public function updateModule(Request $request, int $module): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'nav_group' => ['nullable', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:40'],
            'show_in_nav' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        AclCatalog::updateModule(
            $module,
            $data['label'],
            (string) ($data['nav_group'] ?? 'Operations'),
            (string) ($data['icon'] ?? ''),
            $request->boolean('show_in_nav'),
            $request->boolean('active'),
        );

        return back()->with('status', 'Module updated.');
    }

    public function destroyModule(Request $request, int $module): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        AclCatalog::deleteModule($module);
        OrganizationAudit::record($request->user(), 'acl.module.delete', 'acl_module', $module, null, null, 'access');

        return back()->with('status', 'Module removed.');
    }

    public function saveRole(Request $request, string $role): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $role = strtoupper($role);
        $abilities = array_values(array_filter((array) $request->input('abilities', []), 'is_string'));
        AclCatalog::saveRole($role, $abilities);
        OrganizationAudit::record($request->user(), 'acl.role.save', 'role', $role, null, ['abilities' => $abilities], 'access');

        return redirect()->route('ops.roles', ['role' => $role])->with('status', $role.' permissions saved. Users with this role pick them up immediately.');
    }
}
