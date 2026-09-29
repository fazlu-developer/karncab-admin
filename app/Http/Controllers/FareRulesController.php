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
        $service = strtoupper((string) $request->query('service', ''));
        $services = $this->services();
        if ($service !== '' && $service !== 'PARCEL' && ! isset($services[$service])) {
            $service = '';
        }

        return view('fare.index', [
            'rules' => $db->table('fare_rules')
                ->leftJoin('districts', 'districts.id', '=', 'fare_rules.district_id')
                ->leftJoin('states', 'states.id', '=', 'districts.state_id')
                ->orderBy('fare_rules.product')
                ->orderBy('fare_rules.category')
                ->select('fare_rules.*', 'districts.name as district_name', 'states.name as state_name')
                ->get(),
            'parcelRules' => $db->table('parcel_fare_rules')->orderBy('lane')->orderBy('category')->get(),
            'districts' => $db->table('districts')->leftJoin('states', 'states.id', '=', 'districts.state_id')
                ->orderBy('states.name')->orderBy('districts.name')
                ->get(['districts.id', 'districts.name', 'states.name as state_name']),
            'products' => array_keys($services),
            'categories' => array_keys($this->vehicles()),
            'services' => $services,
            'vehicles' => $this->vehicles(),
            'parcelVehicles' => $this->parcelVehicles(),
            'parcelLanes' => ['LOCAL' => 'Local parcel', 'BIHAR' => 'Bihar parcel'],
            'selectedService' => $service,
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

        return redirect()->route('fare.index', ['service' => $request->input('product')])->with('status', 'Fare rule created. Quotes use these rows from the platform database.');
    }

    public function update(Request $request, int $rule): RedirectResponse
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $data = $this->payload($request);
        $data['updated_at'] = now();
        DB::connection('platform')->table('fare_rules')->where('id', $rule)->update($data);

        return redirect()->route('fare.index', ['service' => $request->input('product')])->with('status', 'Fare rule updated.');
    }

    public function storeParcel(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $data = $this->parcelPayload($request);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $db = DB::connection('platform');
        $existing = $db->table('parcel_fare_rules')
            ->where('lane', $data['lane'])
            ->where('category', $data['category'])
            ->value('id');
        if ($existing) {
            unset($data['created_at']);
            $db->table('parcel_fare_rules')->where('id', $existing)->update($data);
        } else {
            $db->table('parcel_fare_rules')->insert($data);
        }

        return redirect()->route('fare.index', ['service' => 'PARCEL'])->with('status', 'Parcel vehicle fare saved.');
    }

    public function updateParcel(Request $request, int $parcelRule): RedirectResponse
    {
        abort_unless($request->user()?->can('fare.manage'), 403);
        $data = $this->parcelPayload($request);
        $data['updated_at'] = now();
        DB::connection('platform')->table('parcel_fare_rules')->where('id', $parcelRule)->update($data);

        return redirect()->route('fare.index', ['service' => 'PARCEL'])->with('status', 'Parcel vehicle fare updated.');
    }

    /**
     * @return array<string, string>
     */
    private function services(): array
    {
        return [
            'LOCAL_CAB' => 'City Ride',
            'ONE_WAY' => 'One Way',
            'ROUND_WAY' => 'Round Trip',
            'RENTAL' => 'Cab Rental',
            'SCHEDULE' => 'Schedule Ride',
            'OUTSTATION' => 'Outstation',
            'AIRPORT' => 'Airport',
            'RAILWAY' => 'Railway',
            'MULTI_STOP' => 'Multi Stop',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function vehicles(): array
    {
        return [
            'BIKE' => 'Bike',
            'AUTO' => 'Auto',
            'E_RICKSHAW' => 'E-Rickshaw',
            'MINI' => 'Mini',
            'SEDAN' => 'Sedan',
            'SUV' => 'SUV',
            'TRAVELLER' => 'Traveller',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parcelVehicles(): array
    {
        return [
            'BIKE' => 'Bike',
            'AUTO' => 'Auto',
            'SEDAN' => 'Car',
            'TRAVELLER' => 'Van',
            'TRUCK' => 'Truck',
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function parcelPayload(Request $request): array
    {
        $data = $request->validate([
            'lane' => ['required', 'in:LOCAL,BIHAR'],
            'category' => ['required', 'string'],
            'min_km' => ['required', 'numeric', 'min:0'],
            'included_km' => ['required', 'numeric', 'min:0'],
            'per_km_rupees' => ['required', 'numeric', 'min:0'],
            'extra_km_rupees' => ['required', 'numeric', 'min:0'],
            'per_kg_rupees' => ['required', 'numeric', 'min:0'],
            'min_charge_rupees' => ['required', 'numeric', 'min:0'],
            'gst_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        return [
            'lane' => $data['lane'],
            'category' => $data['category'],
            'min_km' => $data['min_km'],
            'included_km' => $data['included_km'],
            'per_km_paise' => (int) round(((float) $data['per_km_rupees']) * 100),
            'extra_km_paise' => (int) round(((float) $data['extra_km_rupees']) * 100),
            'per_kg_paise' => (int) round(((float) $data['per_kg_rupees']) * 100),
            'min_charge_paise' => (int) round(((float) $data['min_charge_rupees']) * 100),
            'gst_percent' => (int) $data['gst_percent'],
            'active' => $request->boolean('active'),
        ];
    }
}
