<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformVehicle;
use App\Platform\RideCatalog;
use App\Platform\TerritoryScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehiclesController extends Controller
{
    public const FAMILIES = [
        'BIKE' => ['BIKE'],
        'AUTO' => ['AUTO', 'E_RICKSHAW'],
        'CAR' => ['MINI', 'SEDAN', 'SUV', 'TRAVELLER'],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('vehicles.view'), 403);
        $family = strtoupper((string) $request->get('family', ''));
        $category = strtoupper((string) $request->get('category', ''));
        $query = PlatformVehicle::query()
            ->with(['driver.user'])
            ->orderByDesc('id');
        TerritoryScope::applyVehicles($query, $request->user());
        if (isset(self::FAMILIES[$family])) {
            $query->whereIn('category', self::FAMILIES[$family]);
        }
        if ($category !== '' && isset(RideCatalog::VEHICLES[$category])) {
            $query->where('category', $category);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('registration_no', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('model', 'like', '%'.$search.'%');
            });
        }
        $counts = [];
        foreach (array_keys(RideCatalog::VEHICLES) as $key) {
            $countQuery = PlatformVehicle::query()->where('category', $key);
            TerritoryScope::applyVehicles($countQuery, $request->user());
            $counts[$key] = $countQuery->count();
        }
        $uncategorized = PlatformVehicle::query()->where(function ($inner) {
            $inner->whereNull('category')->orWhere('category', '')->orWhereNotIn('category', array_keys(RideCatalog::VEHICLES));
        });
        TerritoryScope::applyVehicles($uncategorized, $request->user());
        $counts['OTHER'] = $uncategorized->count();
        $familyCounts = [];
        foreach (self::FAMILIES as $key => $cats) {
            $familyCounts[$key] = collect($cats)->sum(fn ($cat) => $counts[$cat] ?? 0);
        }
        $allQuery = PlatformVehicle::query();
        TerritoryScope::applyVehicles($allQuery, $request->user());

        return view('vehicles.index', [
            'vehicles' => $query->paginate(20)->withQueryString(),
            'counts' => $counts,
            'familyCounts' => $familyCounts,
            'totalCount' => $allQuery->count(),
            'filters' => $request->only(['q', 'family', 'category']),
            'catalog' => RideCatalog::VEHICLES,
            'drivers' => PlatformDriver::query()->with('user')->orderByDesc('id')->limit(200)->get(),
            'stateNames' => DB::connection('platform')->table('states')->pluck('name', 'id'),
            'districtNames' => DB::connection('platform')->table('districts')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('vehicles.edit') || $request->user()?->can('fleet.manage'), 403);
        $data = $request->validate([
            'registration_no' => ['required', 'string', 'max:20', 'unique:platform.vehicles,registration_no'],
            'category' => ['required', Rule::in(array_keys(RideCatalog::VEHICLES))],
            'state_id' => ['required', 'integer'],
            'district_id' => ['required', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'fuel' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $district = DB::connection('platform')->table('districts')->where('id', $data['district_id'])->first();
        abort_unless($district && (int) $district->state_id === (int) $data['state_id'], 422, 'District does not belong to the selected state.');
        PlatformVehicle::query()->create([
            'registration_no' => strtoupper(trim($data['registration_no'])),
            'category' => $data['category'],
            'state_id' => $data['state_id'],
            'district_id' => $data['district_id'],
            'driver_id' => $data['driver_id'] ?: null,
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'fuel' => $data['fuel'] ?? null,
            'status' => $data['status'] ?? 'ACTIVE',
        ]);

        return redirect()->route('vehicles.index')->with('status', 'Vehicle added.');
    }
}
