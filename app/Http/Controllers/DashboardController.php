<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformUser;
use App\Platform\OperatorRole;
use App\Services\PlatformOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $error = null;
        $kpis = [];
        $liveCustomers = [];
        try {
            $dash = $ops->dashboard($request->user(), $request->integer('stateId') ?: null, $request->integer('districtId') ?: null);
            $kpis = $dash['kpis'] ?? [];
            $liveCustomers = $dash['liveCustomers'] ?? [];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $states = collect();
        try {
            $states = DB::connection('platform')->table('states')->orderBy('name')->get();
        } catch (Throwable) {
            $states = collect();
        }

        $liveRides = collect();
        $expiringDocuments = collect();
        try {
            $liveRides = DB::connection('platform')->table('bookings as b')
                ->leftJoin('users as c', 'c.id', '=', 'b.customer_id')
                ->leftJoin('drivers as d', 'd.id', '=', 'b.driver_id')
                ->leftJoin('users as du', 'du.id', '=', 'd.user_id')
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
            'recentUsers' => tap(PlatformUser::query()->orderByDesc('id'), function ($q) use ($request) {
                \App\Platform\TerritoryScope::applyUsers($q, $request->user());
                \App\Platform\TerritoryScope::applyAdminGeo($q, $request->user(), $request->integer('stateId') ?: null, $request->integer('districtId') ?: null);
            })->limit(6)->get(),
            'recentDrivers' => tap(PlatformDriver::query()->with('user')->orderByDesc('id'), fn ($q) => \App\Platform\TerritoryScope::applyDrivers($q, $request->user()))->limit(6)->get(),
            'states' => $states,
            'selectedStateId' => $request->integer('stateId') ?: null,
        ]);
    }

    public static function roles(): array
    {
        return OperatorRole::all();
    }
}
