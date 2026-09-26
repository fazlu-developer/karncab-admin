<?php

namespace App\Platform;

final class OpsNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, ability: ?string, route?: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'group' => 'Dashboard', 'ability' => null, 'route' => 'dashboard'],
            ['key' => 'state-workspace', 'label' => 'State workspace', 'icon' => 'landmark', 'group' => 'Dashboard', 'ability' => 'state.operate', 'route' => 'state.dashboard'],
            ['key' => 'states', 'label' => 'States', 'icon' => 'globe', 'group' => 'Organization', 'ability' => 'state.view', 'route' => 'organization.states'],
            ['key' => 'districts', 'label' => 'Districts', 'icon' => 'map-pinned', 'group' => 'Organization', 'ability' => 'district.view', 'route' => 'organization.districts'],
            ['key' => 'state-assignments', 'label' => 'State Heads', 'icon' => 'map', 'group' => 'Organization', 'ability' => 'state_head.view', 'route' => 'state.assignments'],
            ['key' => 'managers', 'label' => 'Managers', 'icon' => 'user-cog', 'group' => 'Organization', 'ability' => 'manager.view', 'route' => 'managers.index'],
            ['key' => 'franchise', 'label' => 'Franchises', 'icon' => 'store', 'group' => 'Organization', 'ability' => 'franchise.view', 'route' => 'franchises.index'],
            ['key' => 'state-heads', 'label' => 'State Head users', 'icon' => 'landmark', 'group' => 'Organization', 'ability' => 'users.view'],
            ['key' => 'district-heads', 'label' => 'District Heads', 'icon' => 'map-pinned', 'group' => 'Organization', 'ability' => 'users.view'],
            ['key' => 'fleet', 'label' => 'Fleet Owners / Operators', 'icon' => 'warehouse', 'group' => 'Fleet Management', 'ability' => 'fleet.view', 'route' => 'fleet-owners.index'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Fleet Management', 'ability' => 'vehicles.view', 'route' => 'vehicles.index'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Fleet Management', 'ability' => 'drivers.view', 'route' => 'drivers.index'],
            ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'group' => 'People', 'ability' => 'users.view', 'route' => 'users.index'],
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'assign-drivers', 'label' => 'Assign Drivers', 'icon' => 'user-plus', 'group' => 'Operations', 'ability' => 'bookings.manage', 'route' => 'ops.assign-drivers'],
            ['key' => 'trips', 'label' => 'Trips', 'icon' => 'navigation', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'map', 'label' => 'Live Tracking', 'icon' => 'radar', 'group' => 'Operations', 'ability' => 'tracking.view', 'route' => 'live.map'],
            ['key' => 'bulk', 'label' => 'Manual Booking', 'icon' => 'layers', 'group' => 'Operations', 'ability' => 'bookings.manage', 'route' => 'ops.manual-bookings'],
            ['key' => 'parcels', 'label' => 'Parcel', 'icon' => 'package', 'group' => 'Operations', 'ability' => 'parcels.view', 'route' => 'ops.parcels'],
            ['key' => 'travel', 'label' => 'Travel Packages', 'icon' => 'map', 'group' => 'Operations', 'ability' => 'travel.view', 'route' => 'ops.travel'],
            ['key' => 'corporate-plans', 'label' => 'Corporate Plans', 'icon' => 'briefcase', 'group' => 'Operations', 'ability' => 'corporate.view', 'route' => 'ops.corporate-plans'],
            ['key' => 'travel-bookings', 'label' => 'Travel Bookings', 'icon' => 'tickets', 'group' => 'Operations', 'ability' => 'travel.view'],
            ['key' => 'corporate', 'label' => 'Corporate', 'icon' => 'building-2', 'group' => 'Partners', 'ability' => 'corporate.view'],
            ['key' => 'kyc', 'label' => 'Driver KYC', 'icon' => 'badge-check', 'group' => 'KYC & Documents', 'ability' => 'kyc.view'],
            ['key' => 'payments', 'label' => 'Payments', 'icon' => 'credit-card', 'group' => 'Finance', 'ability' => 'payments.view', 'route' => 'payments.index'],
            ['key' => 'wallets', 'label' => 'Wallet', 'icon' => 'wallet', 'group' => 'Finance', 'ability' => 'wallet.view', 'route' => 'wallets.index'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'ability' => 'commission.view', 'route' => 'wallets.commission'],
            ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket-percent', 'group' => 'Finance', 'ability' => 'platform.admin', 'route' => 'coupons.index'],
            ['key' => 'advertising', 'label' => 'Advertising', 'icon' => 'megaphone', 'group' => 'Growth', 'ability' => 'advertising.view', 'route' => 'ads.index'],
            ['key' => 'safety', 'label' => 'Safety & SOS', 'icon' => 'shield-alert', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'safety.index'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'support.index'],
            ['key' => 'faqs', 'label' => 'FAQ & support copy', 'icon' => 'circle-help', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'support.faqs'],
            ['key' => 'leads', 'label' => 'Website enquiries', 'icon' => 'inbox', 'group' => 'Growth', 'ability' => 'customers.view', 'route' => 'leads.index'],
            ['key' => 'ratings', 'label' => 'Ratings', 'icon' => 'star', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Reports', 'ability' => 'reports.view', 'route' => 'reports.index'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'System', 'ability' => 'notifications.view', 'route' => 'notifications.index'],
            ['key' => 'fare', 'label' => 'Fare Management', 'icon' => 'banknote', 'group' => 'System', 'ability' => 'fare.manage', 'route' => 'fare.index'],
            ['key' => 'ride-settings', 'label' => 'Ride Settings', 'icon' => 'radar', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ride-settings.index'],
            ['key' => 'services', 'label' => 'Service Management', 'icon' => 'settings-2', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.services'],
            ['key' => 'branding', 'label' => 'Website & branding', 'icon' => 'image', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.branding'],
            ['key' => 'locations', 'label' => 'Locations', 'icon' => 'globe', 'group' => 'System', 'ability' => 'platform.admin'],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => 'key-round', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.roles'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'sliders-horizontal', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.settings'],
            ['key' => 'audit', 'label' => 'Audit Logs', 'icon' => 'scroll-text', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.audit'],
        ];
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

    public static function href(array $item): string
    {
        if (! empty($item['route'])) {
            return route($item['route']);
        }

        return route('ops.module', $item['key']);
    }

    public static function active(array $item): bool
    {
        if (($item['route'] ?? '') === 'dashboard') {
            return request()->routeIs('dashboard');
        }
        if (($item['route'] ?? '') === 'state.dashboard') {
            return request()->routeIs('state.*') && ! request()->routeIs('state.assignments*');
        }
        if (($item['route'] ?? '') === 'state.assignments') {
            return request()->routeIs('state.assignments*');
        }
        if (($item['key'] ?? '') === 'managers') {
            return request()->routeIs('managers.*');
        }
        if (($item['key'] ?? '') === 'states') {
            return request()->routeIs('organization.states*');
        }
        if (($item['key'] ?? '') === 'districts') {
            return request()->routeIs('organization.districts*');
        }
        if (($item['key'] ?? '') === 'fleet') {
            return request()->routeIs('fleet-owners.*') || request()->route('module') === 'fleet';
        }
        if (($item['key'] ?? '') === 'users') {
            return request()->routeIs('users.*');
        }
        if (($item['key'] ?? '') === 'drivers') {
            return request()->routeIs('drivers.*');
        }
        if (($item['key'] ?? '') === 'vehicles') {
            return request()->routeIs('vehicles.*');
        }

        if (($item['key'] ?? '') === 'franchise') {
            return request()->routeIs('franchises.*');
        }

        if (($item['key'] ?? '') === 'map') {
            return request()->routeIs('live.map');
        }
        if (($item['key'] ?? '') === 'wallets') {
            return request()->routeIs('wallets.*') && ! request()->routeIs('wallets.commission*');
        }
        if (($item['key'] ?? '') === 'commission') {
            return request()->routeIs('wallets.commission*');
        }
        if (($item['key'] ?? '') === 'payments') {
            return request()->routeIs('payments.*');
        }
        if (($item['key'] ?? '') === 'advertising') {
            return request()->routeIs('ads.*');
        }
        if (($item['key'] ?? '') === 'coupons') {
            return request()->routeIs('coupons.*');
        }
        if (($item['key'] ?? '') === 'safety') {
            return request()->routeIs('safety.*') && ! request()->routeIs('safety.share');
        }
        if (($item['key'] ?? '') === 'complaints') {
            return request()->routeIs('support.index', 'support.show', 'support.assign', 'support.transition', 'support.reply', 'support.attach', 'support.file');
        }
        if (($item['key'] ?? '') === 'faqs') {
            return request()->routeIs('support.faqs', 'support.faqs.store', 'support.faqs.update');
        }
        if (($item['key'] ?? '') === 'leads') {
            return request()->routeIs('leads.*');
        }
        if (($item['key'] ?? '') === 'notifications') {
            return request()->routeIs('notifications.*');
        }
        if (($item['key'] ?? '') === 'fare') {
            return request()->routeIs('fare.*');
        }
        if (($item['key'] ?? '') === 'ride-settings') {
            return request()->routeIs('ride-settings.*');
        }
        if (($item['key'] ?? '') === 'reports') {
            return request()->routeIs('reports.*');
        }
        if (($item['key'] ?? '') === 'services') {
            return request()->routeIs('ops.services*');
        }
        if (($item['key'] ?? '') === 'branding') {
            return request()->routeIs('ops.branding*');
        }
        if (($item['key'] ?? '') === 'roles') {
            return request()->routeIs('ops.roles*');
        }
        if (($item['key'] ?? '') === 'settings') {
            return request()->routeIs('ops.settings*');
        }
        if (($item['key'] ?? '') === 'audit') {
            return request()->routeIs('ops.audit*');
        }
        if (($item['key'] ?? '') === 'travel') {
            return request()->routeIs('ops.travel*') || request()->route('module') === 'travel';
        }
        if (($item['key'] ?? '') === 'corporate-plans') {
            return request()->routeIs('ops.corporate-plans*') || request()->route('module') === 'corporate-plans';
        }
        if (($item['key'] ?? '') === 'parcels') {
            return request()->routeIs('ops.parcels*') || request()->route('module') === 'parcels';
        }
        if (($item['key'] ?? '') === 'bulk') {
            return request()->routeIs('ops.manual-bookings*') || request()->route('module') === 'bulk';
        }
        if (($item['key'] ?? '') === 'assign-drivers') {
            return request()->routeIs('ops.assign-drivers*') || request()->route('module') === 'assign-drivers';
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
        foreach (self::items() as $item) {
            if ($item['ability'] === null || (method_exists($user, 'can') && $user->can($item['ability']))) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
