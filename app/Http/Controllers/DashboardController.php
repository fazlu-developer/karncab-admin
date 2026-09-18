<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformUser;
use App\Platform\OperatorRole;
use App\Services\PlatformOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $states = \Illuminate\Support\Facades\DB::connection('platform')->table('states')->orderBy('name')->get();
        } catch (Throwable) {
            $states = collect();
        }

        return view('dashboard', [
            'kpis' => $kpis,
            'error' => $error,
            'liveCustomers' => $liveCustomers,
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
