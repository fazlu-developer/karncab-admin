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
            ?? '10';
        $timeout = $db->table('system_settings')->where('key', 'ride_request_timeout_seconds')->value('value') ?? '30';

        return view('ride-settings.index', [
            'radiusKm' => (float) $radius,
            'timeoutSeconds' => (int) $timeout,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('fare.manage'), 403);
        $data = $request->validate([
            'driver_search_radius_km' => ['required', 'numeric', 'min:1', 'max:100'],
            'ride_request_timeout_seconds' => ['required', 'integer', 'min:10', 'max:300'],
        ]);
        $db = DB::connection('platform');
        $now = now();
        foreach ([
            'driver_search_radius_km' => (string) $data['driver_search_radius_km'],
            'driver_offer_radius_km' => (string) $data['driver_search_radius_km'],
            'ride_request_timeout_seconds' => (string) $data['ride_request_timeout_seconds'],
        ] as $key => $value) {
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
