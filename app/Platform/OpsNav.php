<?php

namespace App\Platform;

final class OpsNav
{
    /**
     * Built-in console links (no dynamically created ACL modules).
     *
     * Hierarchy: Super Admin / Admin / Manager run the system.
     * State Head → District Head → Fleet Owner → Driver. Customer is separate.
     *
     * @return list<array{key: string, label: string, icon: string, group: string, ability: ?string, route?: string}>
     */
    public static function coreItems(): array
    {
        return [
            // Dashboard
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'group' => 'Dashboard', 'ability' => 'dashboard.view', 'route' => 'dashboard'],

            // Organization — territory. People are assigned from All users, not a menu per role.
            ['key' => 'states', 'label' => 'States', 'icon' => 'globe', 'group' => 'Organization', 'ability' => 'state.view', 'route' => 'organization.states'],
            ['key' => 'districts', 'label' => 'Districts', 'icon' => 'map-pinned', 'group' => 'Organization', 'ability' => 'district.view', 'route' => 'organization.districts'],
            ['key' => 'users', 'label' => 'All users', 'icon' => 'users', 'group' => 'Organization', 'ability' => 'users.view', 'route' => 'users.index'],
            ['key' => 'territory-heads', 'label' => 'State & district heads', 'icon' => 'user-cog', 'group' => 'Organization', 'ability' => 'users.view', 'route' => 'organization.heads'],
            ['key' => 'state-kyc', 'label' => 'State Head KYC', 'icon' => 'file-badge', 'group' => 'Organization', 'ability' => 'kyc.view', 'route' => 'organization.state-kyc'],
            ['key' => 'district-kyc', 'label' => 'District Head KYC', 'icon' => 'file-badge', 'group' => 'Organization', 'ability' => 'kyc.view', 'route' => 'organization.district-kyc'],
            ['key' => 'fleet-kyc', 'label' => 'Fleet Owner KYC', 'icon' => 'file-badge', 'group' => 'Organization', 'ability' => 'kyc.view', 'route' => 'fleet-owners.kyc'],

            // Fleet
            ['key' => 'fleet', 'label' => 'Fleet Owners', 'icon' => 'warehouse', 'group' => 'Fleet', 'ability' => 'fleet.view', 'route' => 'fleet-owners.index'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Fleet', 'ability' => 'drivers.view', 'route' => 'drivers.index'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Fleet', 'ability' => 'vehicles.view', 'route' => 'vehicles.index'],
            ['key' => 'district-vehicles', 'label' => 'District vehicles', 'icon' => 'map', 'group' => 'Fleet', 'ability' => 'vehicles.view', 'route' => 'vehicles.districts'],

            // KYC
            ['key' => 'kyc', 'label' => 'Driver KYC', 'icon' => 'badge-check', 'group' => 'KYC', 'ability' => 'kyc.view'],

            // Operations
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'assign-drivers', 'label' => 'Assign Drivers', 'icon' => 'user-plus', 'group' => 'Operations', 'ability' => 'bookings.manage', 'route' => 'ops.assign-drivers'],
            ['key' => 'trips', 'label' => 'Trips', 'icon' => 'navigation', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'map', 'label' => 'Live Tracking', 'icon' => 'radar', 'group' => 'Operations', 'ability' => 'tracking.view', 'route' => 'live.map'],
            ['key' => 'bulk', 'label' => 'Manual Booking', 'icon' => 'layers', 'group' => 'Operations', 'ability' => 'bookings.manage', 'route' => 'ops.manual-bookings'],
            ['key' => 'parcels', 'label' => 'Parcel', 'icon' => 'package', 'group' => 'Operations', 'ability' => 'parcels.view', 'route' => 'ops.parcels'],
            ['key' => 'travel', 'label' => 'Travel Packages', 'icon' => 'map', 'group' => 'Operations', 'ability' => 'travel.view', 'route' => 'ops.travel'],
            ['key' => 'travel-bookings', 'label' => 'Travel Bookings', 'icon' => 'tickets', 'group' => 'Operations', 'ability' => 'travel.view'],
            ['key' => 'corporate-plans', 'label' => 'Corporate Plans', 'icon' => 'briefcase', 'group' => 'Operations', 'ability' => 'corporate.view', 'route' => 'ops.corporate-plans'],
            ['key' => 'corporate', 'label' => 'Corporate accounts', 'icon' => 'building-2', 'group' => 'Operations', 'ability' => 'corporate.view'],
            ['key' => 'safety', 'label' => 'Safety & SOS', 'icon' => 'shield-alert', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'safety.index'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'support.index'],
            ['key' => 'faqs', 'label' => 'FAQ & support copy', 'icon' => 'circle-help', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'support.faqs'],
            ['key' => 'ratings', 'label' => 'Ratings', 'icon' => 'star', 'group' => 'Operations', 'ability' => 'bookings.view'],

            // Offers
            ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket-percent', 'group' => 'Offers', 'ability' => 'platform.admin', 'route' => 'coupons.index'],
            ['key' => 'advertising', 'label' => 'Advertising', 'icon' => 'megaphone', 'group' => 'Offers', 'ability' => 'advertising.view', 'route' => 'ads.index'],
            ['key' => 'leads', 'label' => 'Website enquiries', 'icon' => 'inbox', 'group' => 'Offers', 'ability' => 'customers.view', 'route' => 'leads.index'],

            // Finance
            ['key' => 'payments', 'label' => 'Payments', 'icon' => 'credit-card', 'group' => 'Finance', 'ability' => 'payments.view', 'route' => 'payments.index'],
            ['key' => 'wallets', 'label' => 'Wallet', 'icon' => 'wallet', 'group' => 'Finance', 'ability' => 'wallet.view', 'route' => 'wallets.index'],
            ['key' => 'revenue', 'label' => 'Revenue', 'icon' => 'banknote', 'group' => 'Finance', 'ability' => 'revenue.view', 'route' => 'state.section'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'ability' => 'commission.view', 'route' => 'wallets.commission'],
            ['key' => 'settlements', 'label' => 'Commission settlement', 'icon' => 'banknote', 'group' => 'Finance', 'ability' => 'payments.view', 'route' => 'settlements.index'],
            ['key' => 'payout', 'label' => 'Withdraw requests', 'icon' => 'hand-coins', 'group' => 'Finance', 'ability' => 'payout.view', 'route' => 'withdrawals.index'],

            // Reports
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Reports', 'ability' => 'reports.view', 'route' => 'reports.index'],

            // System (Super Admin / Admin / Manager)
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'System', 'ability' => 'notifications.view', 'route' => 'notifications.index'],
            ['key' => 'fare', 'label' => 'Fare Management', 'icon' => 'banknote', 'group' => 'System', 'ability' => 'fare.manage', 'route' => 'fare.index'],
            ['key' => 'ride-settings', 'label' => 'Ride Settings', 'icon' => 'radar', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ride-settings.index'],
            ['key' => 'services', 'label' => 'Service Management', 'icon' => 'settings-2', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.services'],
            ['key' => 'app-content', 'label' => 'App account pages', 'icon' => 'file-text', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.app-content'],
            ['key' => 'website-pages', 'label' => 'Website CMS', 'icon' => 'file-text', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.website-pages'],
            ['key' => 'branding', 'label' => 'Website & branding', 'icon' => 'image', 'group' => 'System', 'ability' => 'branding.view', 'route' => 'ops.branding'],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => 'key-round', 'group' => 'System', 'ability' => 'roles.view', 'route' => 'ops.roles'],
            ['key' => 'modules', 'label' => 'Manage modules', 'icon' => 'layout-grid', 'group' => 'System', 'ability' => 'roles.view', 'route' => 'ops.roles'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'sliders-horizontal', 'group' => 'System', 'ability' => 'settings.view', 'route' => 'ops.settings'],
            ['key' => 'audit', 'label' => 'Audit Logs', 'icon' => 'scroll-text', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.audit'],
            ['key' => 'old-users', 'label' => 'Old users', 'icon' => 'history', 'group' => 'System', 'ability' => 'users.view', 'route' => 'old-users.index'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, icon: string, group: string, ability: ?string, route?: string}>
     */
    public static function items(): array
    {
        $items = self::coreItems();
        $known = [];
        foreach ($items as $item) {
            $known[$item['key']] = true;
        }
        foreach (\App\Services\AclCatalog::customNavItems() as $item) {
            if (isset($known[$item['key']])) {
                continue;
            }
            $items[] = $item;
            $known[$item['key']] = true;
        }

        return $items;
    }

    public static function find(string $key): ?array
    {
        foreach (self::items() as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Role menus that used to be separate (and the old exclusive franchise).
     * People with those jobs are still users. The sidebar keeps Roles and Manage modules.
     */
    public static function retired(string $key): bool
    {
        return in_array($key, [
            'state-heads', 'state-head', 'state_head', 'state-assignments', 'state-workspace',
            'district-heads', 'district-head', 'district_head', 'district-applications',
            'managers', 'manager',
            'franchise', 'franchises', 'exclusive', 'exclusive-franchise', 'exclusive_franchise',
        ], true);
    }

    public static function href(array $item): string
    {
        $user = auth()->user();
        $key = (string) ($item['key'] ?? '');
        if ($user && method_exists($user, 'isFleetOwner') && $user->isFleetOwner()) {
            $fleet = match ($key) {
                'dashboard' => 'fleet.dashboard',
                'vehicles' => 'fleet.vehicles',
                'drivers' => 'fleet.drivers',
                'reports' => 'fleet.reports',
                default => null,
            };
            if ($fleet !== null) {
                return route($fleet);
            }
        }
        if ($user && method_exists($user, 'isStateHead') && $user->isStateHead()) {
            if ($key === 'dashboard') {
                return route('state.dashboard');
            }
            if (in_array($key, ['parcels', 'complaints', 'fleet'], true)) {
                return route('state.section', $key === 'fleet' ? 'fleets' : $key);
            }
        }
        if ($user && method_exists($user, 'isDistrictHead') && $user->isDistrictHead() && $key === 'dashboard') {
            return route('district.dashboard');
        }
        if ($key === 'fleet-cut') {
            return route('district.settings');
        }
        if ($key === 'modules') {
            return route('ops.roles', ['panel' => 'modules']);
        }
        if ($key === 'roles') {
            return route('ops.roles');
        }
        if ($key === 'revenue') {
            return route('state.section', 'revenue');
        }
        if (! empty($item['route'])) {
            return route($item['route']);
        }

        return route('ops.module', $item['key']);
    }

    public static function active(array $item): bool
    {
        if (($item['key'] ?? '') === 'dashboard' || ($item['route'] ?? '') === 'dashboard') {
            return request()->routeIs('dashboard')
                || request()->routeIs('state.dashboard')
                || request()->routeIs('district.dashboard')
                || request()->routeIs('fleet.dashboard');
        }
        if (($item['route'] ?? '') === 'state.assignments') {
            return request()->routeIs('state.assignments*');
        }
        if (($item['route'] ?? '') === 'state.section') {
            return request()->routeIs('state.section') && request()->route('section') === 'revenue';
        }
        if (($item['route'] ?? '') === 'organization.states') {
            return request()->routeIs('organization.states*');
        }
        if (($item['route'] ?? '') === 'organization.districts') {
            return request()->routeIs('organization.districts*');
        }
        if (($item['route'] ?? '') === 'organization.state-kyc') {
            return request()->routeIs('organization.state-kyc*');
        }
        if (($item['route'] ?? '') === 'organization.district-kyc') {
            return request()->routeIs('organization.district-kyc*');
        }
        if (($item['route'] ?? '') === 'fleet-owners.kyc') {
            return request()->routeIs('fleet-owners.kyc');
        }
        if (($item['route'] ?? '') === 'fleet-owners.index') {
            return request()->routeIs('fleet-owners.*') && ! request()->routeIs('fleet-owners.kyc');
        }
        if (($item['route'] ?? '') === 'vehicles.index' || ($item['key'] ?? '') === 'vehicles') {
            return request()->routeIs('vehicles.index') || request()->routeIs('vehicles.show') || request()->routeIs('vehicles.create') || request()->routeIs('vehicles.edit') || request()->routeIs('vehicles.store') || request()->routeIs('vehicles.update') || request()->routeIs('vehicles.destroy')
                || request()->routeIs('fleet.vehicles*') || request()->routeIs('fleet.vehicle*');
        }
        if (($item['route'] ?? '') === 'vehicles.districts') {
            return request()->routeIs('vehicles.districts*');
        }
        if (($item['route'] ?? '') === 'drivers.index' || ($item['key'] ?? '') === 'drivers') {
            return request()->routeIs('drivers.*') || request()->routeIs('fleet.drivers*') || request()->routeIs('fleet.driver*');
        }
        if (($item['route'] ?? '') === 'users.index') {
            return request()->routeIs('users.*');
        }
        if (($item['route'] ?? '') === 'organization.heads') {
            return request()->routeIs('organization.heads');
        }
        if (($item['route'] ?? '') === 'old-users.index') {
            return request()->routeIs('old-users.*');
        }
        if (($item['route'] ?? '') === 'live.map') {
            return request()->routeIs('live.map');
        }
        if (($item['route'] ?? '') === 'ops.assign-drivers') {
            return request()->routeIs('ops.assign-drivers*') || request()->route('module') === 'assign-drivers';
        }
        if (($item['route'] ?? '') === 'ops.manual-bookings') {
            return request()->routeIs('ops.manual-bookings*') || request()->route('module') === 'bulk';
        }
        if (($item['route'] ?? '') === 'ops.parcels') {
            return request()->routeIs('ops.parcels*');
        }
        if (($item['route'] ?? '') === 'ops.travel') {
            return request()->routeIs('ops.travel*');
        }
        if (($item['route'] ?? '') === 'ops.corporate-plans') {
            return request()->routeIs('ops.corporate-plans*') || request()->route('module') === 'corporate-plans';
        }
        if (($item['route'] ?? '') === 'coupons.index') {
            return request()->routeIs('coupons.*');
        }
        if (($item['route'] ?? '') === 'ads.index') {
            return request()->routeIs('ads.*');
        }
        if (($item['route'] ?? '') === 'leads.index') {
            return request()->routeIs('leads.*');
        }
        if (($item['route'] ?? '') === 'payments.index') {
            return request()->routeIs('payments.*');
        }
        if (($item['route'] ?? '') === 'wallets.index') {
            return request()->routeIs('wallets.*') && ! request()->routeIs('wallets.commission*');
        }
        if (($item['route'] ?? '') === 'wallets.commission' || ($item['key'] ?? '') === 'commission') {
            return request()->routeIs('wallets.commission*');
        }
        if (($item['key'] ?? '') === 'fleet-cut') {
            return request()->routeIs('district.settings', 'district.fleet-cut');
        }
        if (($item['route'] ?? '') === 'settlements.index') {
            return request()->routeIs('settlements.*');
        }
        if (($item['route'] ?? '') === 'withdrawals.index') {
            return request()->routeIs('withdrawals.*');
        }
        if (($item['route'] ?? '') === 'reports.index' || ($item['key'] ?? '') === 'reports') {
            return request()->routeIs('reports.*') || request()->routeIs('fleet.reports');
        }
        if (($item['route'] ?? '') === 'notifications.index') {
            return request()->routeIs('notifications.*');
        }
        if (($item['route'] ?? '') === 'fare.index') {
            return request()->routeIs('fare.*');
        }
        if (($item['route'] ?? '') === 'ride-settings.index') {
            return request()->routeIs('ride-settings.*');
        }
        if (($item['route'] ?? '') === 'ops.services') {
            return request()->routeIs('ops.services*');
        }
        if (($item['route'] ?? '') === 'ops.app-content') {
            return request()->routeIs('ops.app-content*');
        }
        if (($item['route'] ?? '') === 'ops.website-pages') {
            return request()->routeIs('ops.website-pages*');
        }
        if (($item['route'] ?? '') === 'ops.branding') {
            return request()->routeIs('ops.branding*');
        }
        if (($item['key'] ?? '') === 'modules') {
            return request()->routeIs('ops.roles*') && request()->query('panel') === 'modules';
        }
        if (($item['route'] ?? '') === 'ops.roles' || ($item['key'] ?? '') === 'roles') {
            return request()->routeIs('ops.roles*') && request()->query('panel') !== 'modules';
        }
        if (($item['route'] ?? '') === 'ops.settings') {
            return request()->routeIs('ops.settings*');
        }
        if (($item['route'] ?? '') === 'ops.audit') {
            return request()->routeIs('ops.audit*');
        }
        if (($item['route'] ?? '') === 'safety.index') {
            return request()->routeIs('safety.*');
        }
        if (($item['route'] ?? '') === 'support.index') {
            return request()->routeIs('support.index') || request()->routeIs('support.store') || request()->routeIs('support.update');
        }
        if (($item['route'] ?? '') === 'support.faqs') {
            return request()->routeIs('support.faqs*');
        }
        if (request()->routeIs('state.section')) {
            $section = (string) request()->route('section');
            if (($item['key'] ?? '') === 'fleet' && $section === 'fleets') {
                return true;
            }
            if (($item['key'] ?? '') === $section) {
                return true;
            }
        }

        return request()->route('module') === ($item['key'] ?? null);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(object $user): array
    {
        $groups = [];
        foreach (self::visibleFor($user) as $item) {
            $groups[$item['group']][] = $item;
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function visibleFor(object $user): array
    {
        $out = [];
        $seen = [];
        $assigned = method_exists($user, 'assignedAbilities') ? $user->assignedAbilities() : [];
        $stateHead = method_exists($user, 'isStateHead') && $user->isStateHead();
        $districtHead = method_exists($user, 'isDistrictHead') && $user->isDistrictHead();
        $manager = method_exists($user, 'isManager') && $user->isManager();
        $super = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        foreach (self::items() as $item) {
            $key = (string) ($item['key'] ?? '');
            if (isset($seen[$key]) || self::retired($key)) {
                continue;
            }
            if (in_array($key, ['state-kyc', 'district-kyc', 'fleet-kyc'], true) && ! $super) {
                continue;
            }
            if ($stateHead && in_array($key, ['states', 'districts'], true)) {
                continue;
            }
            if ($districtHead && $key === 'districts') {
                continue;
            }
            if (PrivilegedModules::denied($user, $key)) {
                continue;
            }
            if (($stateHead || $districtHead) && in_array($key, PrivilegedModules::HEAD_HIDDEN, true)) {
                continue;
            }
            if ($stateHead && ! in_array($key, ['dashboard', 'users'], true)) {
                $ability = (string) ($item['ability'] ?? '');
                if ($ability === '' || ! in_array($ability, $assigned, true)) {
                    continue;
                }
            }
            if ($manager && $assigned !== [] && $key !== 'dashboard') {
                $ability = (string) ($item['ability'] ?? '');
                if ($ability !== '' && ! in_array($ability, $assigned, true)) {
                    continue;
                }
            }
            $fleetHome = $key === 'dashboard'
                && method_exists($user, 'isFleetOwner')
                && $user->isFleetOwner();
            if (! $fleetHome && ! NavAccess::allowed($user, $item['ability'] ?? null)) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        if (method_exists($user, 'isDistrictHead') && $user->isDistrictHead() && $user->can('fleet.manage') && ! isset($seen['fleet-cut'])) {
            $out[] = ['key' => 'fleet-cut', 'label' => 'Fleet commission', 'icon' => 'sliders-horizontal', 'group' => 'Finance', 'ability' => 'fleet.manage', 'route' => 'district.settings'];
        }

        return $out;
    }
}
