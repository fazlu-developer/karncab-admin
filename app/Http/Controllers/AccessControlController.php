<?php

namespace App\Http\Controllers;

use App\Platform\MenuAccess;
use App\Platform\OperatorRole;
use App\Platform\OpsNav;
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
        abort_unless($request->user()?->can('roles.view') || $request->user()?->can('platform.admin'), 403);
        AclCatalog::catalog();
        $roles = [
            OperatorRole::SUPER_ADMIN,
            OperatorRole::ADMIN,
            OperatorRole::MANAGER,
            OperatorRole::STATE_HEAD,
            OperatorRole::DISTRICT_HEAD,
        ];
        $selected = strtoupper((string) $request->query('role', OperatorRole::ADMIN));
        if (! in_array($selected, $roles, true)) {
            $selected = OperatorRole::ADMIN;
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
            'menuGroups' => $this->menuGroups($abilities),
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
        abort_unless($request->user()?->can('roles.view') || $request->user()?->can('platform.admin'), 403);
        $role = strtoupper($role);
        $abilities = array_values(array_filter((array) $request->input('abilities', []), 'is_string'));
        $managed = $this->managedAbilities();
        $kept = array_values(array_filter(
            PlatformPermission::forRole($role),
            fn (string $ability) => ! in_array($ability, $managed, true),
        ));
        AclCatalog::saveRole($role, array_values(array_unique(array_merge($kept, $abilities))));
        OrganizationAudit::record($request->user(), 'acl.role.save', 'role', $role, null, ['abilities' => $abilities], 'access');

        return redirect()->route('ops.roles', ['role' => $role])->with('status', $role.' permissions saved. The menu and the page URL follow these ticks. State Head and District Head still only see their own area.');
    }

    /**
     * @param  array<int, list<object>>  $abilitiesByModule
     * @return array<string, list<array{label: string, icon: string, ability: string, related: list<object>}>>
     */
    private function menuGroups(array $abilitiesByModule): array
    {
        $byAbility = [];
        foreach ($abilitiesByModule as $rows) {
            foreach ($rows as $row) {
                $byAbility[(string) $row->ability] = $row;
            }
        }
        $groups = [];
        $items = OpsNav::coreItems();
        $items[] = ['key' => 'fleet-cut', 'label' => 'Fleet commission', 'icon' => 'sliders-horizontal', 'group' => 'Finance', 'ability' => 'fleet_commission.view'];
        $seen = [];
        foreach ($items as $item) {
            $ability = (string) ($item['ability'] ?? '');
            if ($ability === '' || isset($seen[$ability]) || OpsNav::retired((string) $item['key'])) {
                continue;
            }
            $seen[$ability] = true;
            $domain = explode('.', $ability, 2)[0];
            $related = [];
            foreach ($byAbility as $code => $row) {
                if ($code === $ability || ! str_starts_with($code, $domain.'.')) {
                    continue;
                }
                $action = (string) ($row->action ?? '');
                if (! in_array($action, ['view', 'create', 'edit', 'delete', 'manage', 'approve', 'export', 'send', 'update', 'upload'], true)) {
                    continue;
                }
                $related[] = $row;
            }
            $groups[(string) $item['group']][] = [
                'label' => (string) $item['label'],
                'icon' => (string) ($item['icon'] ?? 'layout-grid'),
                'ability' => $ability,
                'related' => $related,
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function managedAbilities(): array
    {
        $managed = MenuAccess::abilities();
        foreach (AclCatalog::abilitiesFor() as $row) {
            $domain = explode('.', (string) $row->ability, 2)[0];
            foreach (MenuAccess::abilities() as $ability) {
                if (str_starts_with($ability, $domain.'.') && in_array((string) $row->action, ['view', 'create', 'edit', 'delete', 'manage', 'approve', 'export', 'send', 'update', 'upload'], true)) {
                    $managed[] = (string) $row->ability;
                }
            }
        }

        return array_values(array_unique($managed));
    }
}
