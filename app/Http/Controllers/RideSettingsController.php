<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RideSettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $db = DB::connection('platform');
        $radius = $db->table('system_settings')->where('key', 'driver_search_radius_km')->value('value')
            ?? $db->table('system_settings')->where('key', 'driver_offer_radius_km')->value('value')
            ?? '20';
        $timeout = $db->table('system_settings')->where('key', 'ride_request_timeout_seconds')->value('value') ?? '30';

        $bonusOn = $db->table('system_settings')->where('key', 'driver_welcome_bonus_enabled')->value('value');
        $bonusRupees = $db->table('system_settings')->where('key', 'driver_welcome_bonus_rupees')->value('value');
        $firstRide = $db->table('system_settings')->where('key', 'customer_first_ride_free_enabled')->value('value');
        $int = fn (string $key, int $fallback) => is_numeric($db->table('system_settings')->where('key', $key)->value('value'))
            ? (int) $db->table('system_settings')->where('key', $key)->value('value')
            : $fallback;

        return view('ride-settings.index', [
            'radiusKm' => (float) $radius,
            'timeoutSeconds' => (int) $timeout,
            'driverWelcomeBonusEnabled' => $bonusOn === null || in_array(strtolower((string) $bonusOn), ['1', 'true', 'yes', ''], true),
            'driverWelcomeBonusRupees' => is_numeric($bonusRupees) ? (int) $bonusRupees : 100,
            'customerFirstRideFreeEnabled' => in_array(strtolower((string) $firstRide), ['1', 'true', 'yes'], true),
            'driverReferralBonusRupees' => $int('driver_referral_bonus_rupees', 150),
            'driverReferralRequiredRides' => $int('driver_referral_required_rides', 10),
            'customerJoiningCreditRupees' => $int('customer_joining_credit_rupees', 50),
            'customerReferralCreditRupees' => $int('customer_referral_credit_rupees', 50),
            'customerPromoMaxRupees' => $int('customer_promo_max_rupees', 50),
            'customerPromoMaxFarePercent' => $int('customer_promo_max_fare_percent', 50),
            'incentives' => $db->getSchemaBuilder()->hasTable('incentives')
                ? $db->table('incentives')->orderByDesc('id')->limit(80)->get()
                : collect(),
            'incentiveTotals' => $db->getSchemaBuilder()->hasTable('incentives')
                ? [
                    'paidPaise' => (int) $db->table('incentives')->where('status', 'paid')->sum('amount_paise'),
                    'count' => (int) $db->table('incentives')->count(),
                    'pending' => (int) $db->table('incentives')->where('status', 'pending')->count(),
                    'reversed' => (int) $db->table('incentives')->where('status', 'reversed')->count(),
                ]
                : ['paidPaise' => 0, 'count' => 0, 'pending' => 0, 'reversed' => 0],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $data = $request->validate([
            'driver_search_radius_km' => ['required', 'numeric', 'min:1', 'max:100'],
            'ride_request_timeout_seconds' => ['required', 'integer', 'min:10', 'max:900'],
            'driver_welcome_bonus_enabled' => ['nullable', 'boolean'],
            'driver_welcome_bonus_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_first_ride_free_enabled' => ['nullable', 'boolean'],
            'driver_referral_bonus_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'driver_referral_required_rides' => ['nullable', 'integer', 'min:1', 'max:500'],
            'customer_joining_credit_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_referral_credit_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_promo_max_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_promo_max_fare_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $db = DB::connection('platform');
        $now = now();
        $settings = [
            'driver_search_radius_km' => (string) $data['driver_search_radius_km'],
            'driver_offer_radius_km' => (string) $data['driver_search_radius_km'],
            'ride_request_timeout_seconds' => (string) $data['ride_request_timeout_seconds'],
        ];
        if ($request->has('driver_welcome_bonus_rupees')) {
            $settings['driver_welcome_bonus_enabled'] = $request->boolean('driver_welcome_bonus_enabled') ? '1' : '0';
            $settings['driver_welcome_bonus_rupees'] = (string) ($data['driver_welcome_bonus_rupees'] ?? 100);
            $settings['customer_first_ride_free_enabled'] = $request->boolean('customer_first_ride_free_enabled') ? '1' : '0';
            $settings['driver_referral_bonus_rupees'] = (string) ($data['driver_referral_bonus_rupees'] ?? 150);
            $settings['driver_referral_required_rides'] = (string) ($data['driver_referral_required_rides'] ?? 10);
            $settings['customer_joining_credit_rupees'] = (string) ($data['customer_joining_credit_rupees'] ?? 50);
            $settings['customer_referral_credit_rupees'] = (string) ($data['customer_referral_credit_rupees'] ?? 50);
            $settings['customer_promo_max_rupees'] = (string) ($data['customer_promo_max_rupees'] ?? 50);
            $settings['customer_promo_max_fare_percent'] = (string) ($data['customer_promo_max_fare_percent'] ?? 50);
        }
        foreach ($settings as $key => $value) {
            $exists = $db->table('system_settings')->where('key', $key)->exists();
            if ($exists) {
                $db->table('system_settings')->where('key', $key)->update(['value' => $value, 'updated_at' => $now]);
            } else {
                $db->table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        return back()->with('status', 'Ride search radius and request timeout saved. Customer booking uses these values immediately.');
    }
}
