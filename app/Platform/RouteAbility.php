<?php

namespace App\Platform;

use Illuminate\Http\Request;

final class RouteAbility
{
    /**
     * Abilities that may open this request. Null means the route is not a module screen.
     *
     * @return list<string>|null
     */
    public static function anyOf(Request $request): ?array
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if ($name === '' || in_array($name, ['logout', 'platform.session'], true) || str_starts_with($name, 'customer.')) {
            return null;
        }
        if (in_array($name, ['ops.module', 'ops.export', 'ops.bookings.show'], true)) {
            $module = $name === 'ops.bookings.show' ? 'bookings' : (string) $request->route('module');
            $ability = OpsNav::find($module)['ability'] ?? null;

            return $ability ? [$ability] : ['platform.admin'];
        }
        foreach (self::rules() as [$pattern, $abilities]) {
            if ($pattern === $name || (str_ends_with($pattern, '*') && str_starts_with($name, substr($pattern, 0, -1)))) {
                return $abilities;
            }
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: list<string>}>
     */
    private static function rules(): array
    {
        return [
            ['dashboard', ['dashboard.view']],
            ['state.assignments', ['state_head.view']],
            ['state.assignments.update', ['state_head.update', 'state_head.view', 'users.edit']],
            ['state.*', ['state.operate']],
            ['district.settings', ['fleet.manage', 'commission.edit']],
            ['district.fleet-cut', ['fleet.manage', 'commission.edit']],
            ['district.*', ['dashboard.view']],
            ['fleet.*', ['fleet.view']],
            ['live.map', ['tracking.view']],
            ['wallets.commission', ['commission.view', 'wallet.view', 'payments.view']],
            ['wallets.commission.update', ['platform.admin']],
            ['wallets.*', ['wallet.view']],
            ['vehicles.*', ['vehicles.view']],
            ['drivers.*', ['drivers.view']],
            ['fleet-owners.*', ['fleet.view']],
            ['franchises.*', ['franchise.view']],
            ['users.*', ['users.view']],
            ['old-users.*', ['users.view']],
            ['managers.*', ['manager.view']],
            ['organization.states', ['state.view']],
            ['organization.districts', ['district.view']],
            ['organization.heads', ['users.view', 'state.view', 'district.view']],
            ['organization.district-kyc', ['kyc.view']],
            ['organization.state-kyc', ['kyc.view']],
            ['organization.*', ['state.view', 'district.view', 'kyc.view']],
            ['payments.*', ['payments.view']],
            ['settlements.*', ['payments.view']],
            ['withdrawals.*', ['payout.view']],
            ['coupons.*', ['platform.admin']],
            ['ads.*', ['advertising.view']],
            ['safety.*', ['safety.view']],
            ['support.faqs', ['safety.view']],
            ['support.*', ['safety.view', 'complaints.manage']],
            ['leads.*', ['customers.view']],
            ['notifications.*', ['notifications.view']],
            ['ops.notify', ['notifications.send', 'notifications.view']],
            ['reports.*', ['reports.view']],
            ['fare.*', ['fare.manage']],
            ['ride-settings.*', ['platform.admin']],
            ['ops.branding', ['platform.admin']],
            ['ops.branding.save', ['platform.admin']],
            ['ops.roles', ['platform.admin']],
            ['ops.roles.*', ['platform.admin']],
            ['ops.settings', ['platform.admin']],
            ['ops.settings.save', ['platform.admin']],
            ['ops.audit', ['platform.admin']],
            ['ops.services', ['platform.admin']],
            ['ops.services.*', ['platform.admin']],
            ['ops.app-content', ['platform.admin']],
            ['ops.app-content.*', ['platform.admin']],
            ['ops.website-pages', ['platform.admin']],
            ['ops.website-pages.*', ['platform.admin']],
            ['ops.travel', ['travel.view']],
            ['ops.travel.*', ['travel.view']],
            ['ops.corporate-plans', ['corporate.view']],
            ['ops.corporate-plans.*', ['corporate.view']],
            ['ops.parcels', ['parcels.view']],
            ['ops.parcels.*', ['parcels.view']],
            ['ops.manual-bookings', ['bookings.manage']],
            ['ops.manual-bookings.*', ['bookings.manage']],
            ['ops.assign-drivers', ['bookings.manage']],
            ['ops.assign-drivers.*', ['bookings.manage']],
        ];
    }
}
