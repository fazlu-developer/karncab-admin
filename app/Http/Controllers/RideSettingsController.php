<?php

namespace App\Http\Controllers;

use App\Services\TerritoryBonusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RideSettingsController extends Controller
{
    public function __construct(private readonly TerritoryBonusService $bonuses) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $db = DB::connection('platform');
        $radius = $db->table('system_settings')->where('key', 'driver_search_radius_km')->value('value')
            ?? $db->table('system_settings')->where('key', 'driver_offer_radius_km')->value('value')
            ?? '20';
        $timeout = $db->table('system_settings')->where('key', 'ride_request_timeout_seconds')->value('value') ?? '600';

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
            'driverReferralBonusRupees' => $int('driver_referral_bonus_rupees', 50),
            'driverReferralRequiredRides' => $int('driver_referral_required_rides', 1),
            'customerJoiningCreditRupees' => $int('customer_joining_credit_rupees', 50),
            'customerReferralCreditRupees' => $int('customer_referral_credit_rupees', 50),
            'customerPromoMaxRupees' => $int('customer_promo_max_rupees', 50),
            'customerPromoMaxFarePercent' => $int('customer_promo_max_fare_percent', 50),
            'gstPercent' => $int('gst_percent', 5),
            'fleetOwnerCommissionPercent' => is_numeric($db->table('system_settings')->where('key', 'fleet_owner_commission_percent')->value('value'))
                ? (float) $db->table('system_settings')->where('key', 'fleet_owner_commission_percent')->value('value')
                : 5,
            'territoryBonuses' => $this->bonuses->list(),
            'states' => $db->table('states')->orderBy('name')->get(),
            'districts' => $db->table('districts')->orderBy('name')->get(),
            'incentives' => $db->getSchemaBuilder()->hasTable('incentives')
                ? $db->table('incentives')->orderByDesc('id')->limit(80)->get()
                : collect(),
            'advancePayment' => $this->advancePayment($db),
            'driverReferrals' => $this->driverReferrals($db),
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

    /**
     * @param  \Illuminate\Database\Connection  $db
     * @return array<string, bool>
     */
    private function advancePayment($db): array
    {
        $defaults = [
            'LOCAL_CAB' => false,
            'ONE_WAY' => true,
            'ROUND_WAY' => true,
            'SCHEDULE' => true,
            'AIRPORT' => true,
            'RAILWAY' => true,
            'RENTAL' => true,
            'MULTI_STOP' => true,
            'PARCEL' => true,
        ];
        $raw = $db->table('system_settings')->where('key', 'advance_payment_products')->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded)) {
            return $defaults;
        }
        foreach ($defaults as $key => $fallback) {
            if (array_key_exists($key, $decoded)) {
                $defaults[$key] = in_array(strtolower((string) $decoded[$key]), ['1', 'true', 'yes', 'on'], true) || $decoded[$key] === true;
            }
        }

        return $defaults;
    }

    /**
     * @param  \Illuminate\Database\Connection  $db
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function driverReferrals($db)
    {
        if (! $db->getSchemaBuilder()->hasTable('incentives')) {
            return collect();
        }
        $code = $db->getSchemaBuilder()->hasColumn('users', 'referral_code') ? 'referrer.referral_code' : DB::raw('null as referral_code');

        return $db->table('incentives as i')
            ->leftJoin('users as referrer', 'referrer.id', '=', 'i.user_id')
            ->leftJoin('users as referred', 'referred.id', '=', 'i.related_user_id')
            ->where('i.kind', 'driver_referral')
            ->orderByDesc('i.id')
            ->limit(100)
            ->get([
                'i.amount_paise',
                'i.status',
                'i.booking_id',
                'referrer.name as referrer_name',
                $code,
                'referred.name as referred_name',
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
            'driver_referral_required_rides' => ['nullable', 'integer', 'min:0', 'max:500'],
            'customer_joining_credit_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_referral_credit_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_promo_max_rupees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'customer_promo_max_fare_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fleet_owner_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            $settings['driver_referral_bonus_rupees'] = (string) ($data['driver_referral_bonus_rupees'] ?? 50);
            $settings['driver_referral_required_rides'] = (string) ($data['driver_referral_required_rides'] ?? 0);
            $settings['customer_joining_credit_rupees'] = (string) ($data['customer_joining_credit_rupees'] ?? 50);
            $settings['customer_referral_credit_rupees'] = (string) ($data['customer_referral_credit_rupees'] ?? 50);
            $settings['customer_promo_max_rupees'] = (string) ($data['customer_promo_max_rupees'] ?? 50);
            $settings['customer_promo_max_fare_percent'] = (string) ($data['customer_promo_max_fare_percent'] ?? 50);
        }
        if ($request->has('gst_percent')) {
            $settings['gst_percent'] = (string) ($data['gst_percent'] ?? 5);
            $gst = (int) round((float) ($data['gst_percent'] ?? 5));
            if ($db->getSchemaBuilder()->hasTable('fare_rules') && $db->getSchemaBuilder()->hasColumn('fare_rules', 'gst_percent')) {
                $db->table('fare_rules')->update(['gst_percent' => $gst, 'updated_at' => $now]);
            }
            if ($db->getSchemaBuilder()->hasTable('parcel_fare_rules') && $db->getSchemaBuilder()->hasColumn('parcel_fare_rules', 'gst_percent')) {
                $db->table('parcel_fare_rules')->update(['gst_percent' => $gst]);
            }
        }
        if ($request->has('advance_payment_form')) {
            $flags = [];
            foreach (['LOCAL_CAB', 'ONE_WAY', 'ROUND_WAY', 'SCHEDULE', 'AIRPORT', 'RAILWAY', 'RENTAL', 'MULTI_STOP', 'PARCEL'] as $product) {
                $flags[$product] = $request->boolean('advance_'.$product);
            }
            $settings['advance_payment_products'] = json_encode($flags);
        }
        if ($request->has('fleet_owner_commission_percent')) {
            $settings['fleet_owner_commission_percent'] = (string) ($data['fleet_owner_commission_percent'] ?? 5);
            $settings['fleet_commission_share_percent'] = (string) ($data['fleet_owner_commission_percent'] ?? 5);
        }
        foreach ($settings as $key => $value) {
            $exists = $db->table('system_settings')->where('key', $key)->exists();
            if ($exists) {
                $db->table('system_settings')->where('key', $key)->update(['value' => $value, 'updated_at' => $now]);
            } else {
                $db->table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        return back()->with('status', 'Ride settings saved. Apps pick up GST, fleet commission and bonuses immediately.');
    }

    public function storeBonus(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $data = $request->validate([
            'audience' => ['required', 'in:DRIVER,CUSTOMER'],
            'kind' => ['required', 'in:joining,referral'],
            'state_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'amount_rupees' => ['required', 'integer', 'min:0', 'max:100000'],
            'required_rides' => ['nullable', 'integer', 'min:0', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $data['enabled'] = $request->boolean('enabled', true);
        $this->bonuses->store($data);

        return back()->with('status', 'Territory bonus saved. New registrations in that area see this amount in the app.');
    }

    public function updateBonus(Request $request, int $bonus): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $data = $request->validate([
            'amount_rupees' => ['required', 'integer', 'min:0', 'max:100000'],
            'required_rides' => ['nullable', 'integer', 'min:0', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $this->bonuses->update($bonus, $data);

        return back()->with('status', 'Territory bonus updated.');
    }

    public function destroyBonus(Request $request, int $bonus): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $this->bonuses->destroy($bonus);

        return back()->with('status', 'Territory bonus removed.');
    }
}
