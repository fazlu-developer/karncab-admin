<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FareRulesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $db = DB::connection('platform');

        return view('fare.index', [
            'rules' => $db->table('fare_rules')
                ->leftJoin('districts', 'districts.id', '=', 'fare_rules.district_id')
                ->leftJoin('states', 'states.id', '=', 'districts.state_id')
                ->orderBy('fare_rules.product')
                ->orderBy('fare_rules.category')
                ->select('fare_rules.*', 'districts.name as district_name', 'states.name as state_name')
                ->get(),
            'districts' => $db->table('districts')->leftJoin('states', 'states.id', '=', 'districts.state_id')
                ->orderBy('states.name')->orderBy('districts.name')
                ->get(['districts.id', 'districts.name', 'states.name as state_name']),
            'products' => ['LOCAL_CAB', 'ONE_WAY', 'ROUND_WAY', 'RENTAL', 'SCHEDULE', 'OUTSTATION', 'AIRPORT', 'RAILWAY', 'MULTI_STOP'],
            'categories' => ['BIKE', 'AUTO', 'E_RICKSHAW', 'MINI', 'SEDAN', 'SUV', 'TRAVELLER'],
            'liveStates' => ['Bihar', 'Delhi'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $data = $this->payload($request);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::connection('platform')->table('fare_rules')->insert($data);

        return back()->with('status', 'Fare rule created. Quotes use these rows from the platform database.');
    }

    public function update(Request $request, int $rule): RedirectResponse
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $data = $this->payload($request);
        $data['updated_at'] = now();
        DB::connection('platform')->table('fare_rules')->where('id', $rule)->update($data);

        return back()->with('status', 'Fare rule updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'product' => ['required', 'string'],
            'category' => ['required', 'string'],
            'district_id' => ['nullable', 'integer'],
            'min_km' => ['required', 'numeric', 'min:0'],
            'included_km' => ['required', 'numeric', 'min:0'],
            'per_km_rupees' => ['required', 'numeric', 'min:0'],
            'extra_km_rupees' => ['required', 'numeric', 'min:0'],
            'waiting_per_min_rupees' => ['required', 'numeric', 'min:0'],
            'night_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'gst_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'cancel_rupees' => ['nullable', 'numeric', 'min:0'],
            'rental_hours' => ['nullable', 'integer', 'min:0'],
            'extra_hour_rupees' => ['nullable', 'numeric', 'min:0'],
            'stop_rupees' => ['nullable', 'numeric', 'min:0'],
            'night_stay_rupees' => ['nullable', 'numeric', 'min:0'],
            'driver_allow_rupees' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            'product' => $data['product'],
            'category' => $data['category'],
            'district_id' => $data['district_id'] ?: null,
            'min_km' => $data['min_km'],
            'included_km' => $data['included_km'],
            'per_km_paise' => (int) round(((float) $data['per_km_rupees']) * 100),
            'extra_km_paise' => (int) round(((float) $data['extra_km_rupees']) * 100),
            'waiting_paise_per_min' => (int) round(((float) $data['waiting_per_min_rupees']) * 100),
            'night_percent' => (int) $data['night_percent'],
            'gst_percent' => (int) $data['gst_percent'],
            'cancel_paise' => (int) round(((float) ($data['cancel_rupees'] ?? 0)) * 100),
            'rental_hours' => $data['rental_hours'] ?: null,
            'extra_hour_paise' => (int) round(((float) ($data['extra_hour_rupees'] ?? 150)) * 100),
            'stop_paise' => (int) round(((float) ($data['stop_rupees'] ?? 0)) * 100),
            'night_stay_paise' => (int) round(((float) ($data['night_stay_rupees'] ?? 0)) * 100),
            'driver_allow_paise' => (int) round(((float) ($data['driver_allow_rupees'] ?? 0)) * 100),
            'active' => $request->boolean('active'),
        ];
    }
}
