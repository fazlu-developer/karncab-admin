<?php

namespace App\Platform;

/**
 * One permission per sidebar module. The same ability opens the menu and the URL.
 */
final class MenuAccess
{
    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        $abilities = [];
        foreach (OpsNav::coreItems() as $item) {
            if (! empty($item['ability'])) {
                $abilities[] = (string) $item['ability'];
            }
        }
        $abilities[] = 'fleet_commission.view';

        return array_values(array_unique($abilities));
    }

    public static function forRequest(string $name): ?string
    {
        if ($name === 'state.section') {
            return match ((string) request()->route('section')) {
                'revenue' => 'revenue.view',
                default => 'dashboard.view',
            };
        }

        foreach (self::routes() as $prefix => $ability) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return $ability;
            }
        }

        return null;
    }

    /**
     * Longer prefixes are listed first so a KYC page is not opened by the parent module.
     *
     * @return array<string, string>
     */
    private static function routes(): array
    {
        return [
            'organization.state-kyc' => 'state_kyc.view',
            'organization.district-kyc' => 'district_kyc.view',
            'organization.heads' => 'territory_heads.view',
            'organization.states' => 'state.view',
            'organization.districts' => 'district.view',
            'territory-contacts.' => 'territory_contacts.view',
            'fleet-owners.kyc' => 'fleet_kyc.view',
            'vehicles.districts' => 'district_vehicles.view',
            'ops.assign-drivers' => 'assign_drivers.view',
            'ops.manual-bookings' => 'manual_booking.view',
            'ops.bulk-bookings' => 'bulk_bookings.view',
            'ops.tour-bookings' => 'travel_bookings.view',
            'ops.ambulance' => 'ambulance.view',
            'ops.corporate-bookings' => 'corporate_bookings.view',
            'ops.corporate-plans' => 'corporate_plans.view',
            'ops.travel' => 'travel.view',
            'ops.parcels' => 'parcels.view',
            'support.faqs' => 'faqs.view',
            'support.' => 'complaints.view',
            'routes.popular' => 'popular_routes.view',
            'coupons.' => 'coupons.view',
            'leads.' => 'leads.view',
            'settlements.' => 'settlements.view',
            'withdrawals.' => 'payout.view',
            'wallets.commission' => 'commission.view',
            'ride-settings.' => 'ride_settings.view',
            'ops.services' => 'services.view',
            'ops.app-content' => 'app_content.view',
            'ops.website-pages' => 'website_pages.view',
            'ops.branding' => 'branding.view',
            'ops.roles' => 'roles.view',
            'ops.settings' => 'settings.view',
            'ops.audit' => 'audit.view',
            'old-users.' => 'old_users.view',
            'live.map' => 'tracking.view',
            'district.settings' => 'fleet_commission.view',
            'district.fleet-cut' => 'fleet_commission.view',
            'users.' => 'users.view',
            'drivers.' => 'drivers.view',
            'vehicles.' => 'vehicles.view',
            'fleet-owners.' => 'fleet.view',
            'payments.' => 'payments.view',
            'wallets.' => 'wallet.view',
            'reports.' => 'reports.view',
            'notifications.' => 'notifications.view',
            'fare.' => 'fare.manage',
            'ads.' => 'advertising.view',
            'safety.' => 'safety.view',
            'state.dashboard' => 'dashboard.view',
            'district.dashboard' => 'dashboard.view',
            'dashboard' => 'dashboard.view',
        ];
    }
}
