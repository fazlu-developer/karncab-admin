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
        try {
            $kpis = $ops->dashboard($request->user())['kpis'] ?? [];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('dashboard', [
            'kpis' => $kpis,
            'error' => $error,
            'recentUsers' => tap(PlatformUser::query()->orderByDesc('id'), fn ($q) => \App\Platform\TerritoryScope::applyUsers($q, $request->user()))->limit(6)->get(),
            'recentDrivers' => tap(PlatformDriver::query()->with('user')->orderByDesc('id'), fn ($q) => \App\Platform\TerritoryScope::applyDrivers($q, $request->user()))->limit(6)->get(),
        ]);
    }

    public static function roles(): array
    {
        return OperatorRole::all();
    }
}
