<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformUser;
use App\Platform\OperatorRole;
use App\Services\PlatformOpsService;
use App\Support\PublicMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PlatformOpsService $ops): View|RedirectResponse
    {
        if ($request->user()?->isAdvertiser()) {
            return redirect()->route('ads.index');
        }
        if ($request->user()?->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }
        if ($request->user()?->isStateHead()) {
            return redirect()->route('state.dashboard');
        }
        if ($request->user()?->isDistrictHead()) {
            return redirect()->route('district.dashboard');
        }
        if ($request->user()?->isFleetOwner()) {
            return redirect()->route('fleet.dashboard');
        }
        $error = null;
        $kpis = [];
        $liveCustomers = [];
        $selectedStateId = $request->integer('stateId') ?: null;
        $selectedDistrictId = $request->integer('districtId') ?: null;
        try {
            $dash = $ops->dashboard($request->user(), $selectedStateId, $selectedDistrictId);
            $kpis = $dash['kpis'] ?? [];
            $liveCustomers = $dash['liveCustomers'] ?? [];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $states = collect();
        $districts = collect();
        try {
            $states = DB::connection('platform')->table('states')->orderBy('name')->get();
            if ($selectedStateId) {
                $districts = DB::connection('platform')->table('districts')->where('state_id', $selectedStateId)->orderBy('name')->get();
            }
        } catch (Throwable) {
            $states = collect();
            $districts = collect();
        }

        $liveRides = collect();
        $expiringDocuments = collect();
        try {
            $liveRides = DB::connection('platform')->table('bookings as b')
                ->leftJoin('users as c', 'c.id', '=', 'b.customer_id')
                ->leftJoin('drivers as d', 'd.id', '=', 'b.driver_id')
                ->leftJoin('users as du', 'du.id', '=', 'd.user_id');
            \App\Platform\TerritoryScope::applyBookings($liveRides, $request->user(), 'b');
            if ($selectedDistrictId && \App\Platform\TerritoryScope::isUnrestricted($request->user())) {
                $liveRides->where('b.district_id', $selectedDistrictId);
            } elseif ($selectedStateId && \App\Platform\TerritoryScope::isUnrestricted($request->user())) {
                \App\Platform\TerritoryScope::restrictBookingsToStateId($liveRides, $selectedStateId, 'b');
            }
            $liveRides = $liveRides
                ->whereIn('b.status', [
                    'SEARCHING', 'REQUESTED', 'DRIVER_SEARCHING', 'PENDING', 'CONFIRMED',
                    'DRIVER_ACCEPTED', 'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING',
                    'DRIVER_ARRIVED', 'TRIP_STARTED', 'OTP_VERIFIED', 'ONGOING',
                ])
                ->orderByDesc('b.id')
                ->limit(20)
                ->get([
                    'b.id', 'b.public_ref', 'b.product', 'b.status', 'b.pickup_text', 'b.drop_text',
                    'c.name as customer', 'c.phone as customer_phone', 'du.name as driver_name',
                ]);
        } catch (Throwable) {
            $liveRides = collect();
        }
        try {
            $expiringDocuments = DB::connection('platform')->table('driver_documents as dd')
                ->join('drivers as d', 'd.id', '=', 'dd.driver_id')
                ->join('users as u', 'u.id', '=', 'd.user_id')
                ->where(function ($q) {
                    $q->where('dd.status', 'expired')
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('dd.expires_at')->whereDate('dd.expires_at', '<=', now()->addDays(15)->toDateString());
                        });
                })
                ->orderBy('dd.expires_at')
                ->limit(20)
                ->get(['dd.type', 'dd.expires_at', 'dd.status', 'u.name', 'u.phone', 'd.id as driver_id']);
        } catch (Throwable) {
            $expiringDocuments = collect();
        }

        return view('dashboard', [
            'kpis' => $kpis,
            'error' => $error,
            'liveCustomers' => $liveCustomers,
            'liveRides' => $liveRides,
            'expiringDocuments' => $expiringDocuments,
            'recentUsers' => tap(PlatformUser::query()->orderByDesc('id'), function ($q) use ($request, $selectedStateId, $selectedDistrictId) {
                \App\Platform\TerritoryScope::applyUsers($q, $request->user());
                \App\Platform\TerritoryScope::applyAdminGeo($q, $request->user(), $selectedStateId, $selectedDistrictId);
            })->limit(6)->get(),
            'recentDrivers' => tap(PlatformDriver::query()->with('user')->orderByDesc('id'), function ($q) use ($request, $selectedStateId, $selectedDistrictId) {
                \App\Platform\TerritoryScope::applyDrivers($q, $request->user());
                \App\Platform\TerritoryScope::applyAdminGeo($q, $request->user(), $selectedStateId, $selectedDistrictId);
            })->limit(6)->get(),
            'states' => $states,
            'districts' => $districts,
            'selectedStateId' => $selectedStateId,
            'selectedDistrictId' => $selectedDistrictId,
            'pulse' => $this->pulse(),
        ]);
    }

    /**
     * @return array{withdraws: int, corporate: int, bulk: int, tours: int, banners: list<array{title: string, url: string, status: string}>}
     */
    private function pulse(): array
    {
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        $pending = function (string $table) use ($db, $schema): int {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'status')) {
                return 0;
            }

            return (int) $db->table($table)->where(function ($query) {
                $query->whereIn('status', ['requested', 'pending', 'REQUESTED', 'PENDING', 'SUBMITTED', 'NEW', 'awaiting_approval', 'awaiting_payment'])
                    ->orWhere('status', 'like', '%pend%')
                    ->orWhere('status', 'like', '%request%');
            })->count();
        };
        $banners = [];
        if ($schema->hasTable('ad_campaigns')) {
            $field = fn (object $row, string $name) => property_exists($row, $name) ? $row->{$name} : null;
            foreach ($db->table('ad_campaigns')->orderByDesc('id')->limit(4)->get() as $row) {
                $url = PublicMedia::presentMediaUrl($field($row, 'image_url') ?: $field($row, 'banner_key'), $field($row, 'updated_at'));
                if ($url === null) {
                    continue;
                }
                $banners[] = [
                    'title' => (string) ($field($row, 'title') ?: ($field($row, 'name') ?: 'Banner')),
                    'url' => $url,
                    'status' => (string) ($field($row, 'status') ?? ''),
                ];
            }
        }

        return [
            'withdraws' => $pending('wallet_withdrawals'),
            'corporate' => $pending('corporate_travel_bookings'),
            'bulk' => $pending('bulk_bookings'),
            'tours' => $pending('travel_bookings'),
            'banners' => $banners,
        ];
    }

    public static function roles(): array
    {
        return OperatorRole::all();
    }
}
