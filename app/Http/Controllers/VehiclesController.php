<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformDriver;
use App\Models\Platform\PlatformVehicle;
use App\Platform\OperatorRole;
use App\Platform\RideCatalog;
use App\Platform\TerritoryScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $districtId = (int) $request->get('district_id', 0);
        $actor = $request->user();
        if ($actor && ! TerritoryScope::isUnrestricted($actor)) {
            $role = TerritoryScope::effectiveRole($actor);
            if (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true) && $actor->district_id) {
                $districtId = (int) $actor->district_id;
            }
            if ($role === OperatorRole::STATE_HEAD && $districtId > 0 && ! in_array($districtId, TerritoryScope::districtIdsForState((int) $actor->state_id), true)) {
                $districtId = 0;
            }
        }
        $query = PlatformVehicle::query()
            ->with(['driver.user'])
            ->orderByDesc('id');
        TerritoryScope::applyVehicles($query, $request->user());
        if ($districtId > 0) {
            $query->where('district_id', $districtId);
        }
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
        $scoped = function ($inner) use ($request, $districtId) {
            TerritoryScope::applyVehicles($inner, $request->user());
            if ($districtId > 0) {
                $inner->where('district_id', $districtId);
            }
        };
        $counts = [];
        foreach (array_keys(RideCatalog::VEHICLES) as $key) {
            $countQuery = PlatformVehicle::query()->where('category', $key);
            $scoped($countQuery);
            $counts[$key] = $countQuery->count();
        }
        $uncategorized = PlatformVehicle::query()->where(function ($inner) {
            $inner->whereNull('category')->orWhere('category', '')->orWhereNotIn('category', array_keys(RideCatalog::VEHICLES));
        });
        $scoped($uncategorized);
        $counts['OTHER'] = $uncategorized->count();
        $familyCounts = [];
        foreach (self::FAMILIES as $key => $cats) {
            $familyCounts[$key] = collect($cats)->sum(fn ($cat) => $counts[$cat] ?? 0);
        }
        $allQuery = PlatformVehicle::query();
        $scoped($allQuery);
        $districtNames = DB::connection('platform')->table('districts')->pluck('name', 'id');
        $stateNames = DB::connection('platform')->table('states')->pluck('name', 'id');

        return view('vehicles.index', [
            'vehicles' => $query->paginate(20)->withQueryString(),
            'counts' => $counts,
            'familyCounts' => $familyCounts,
            'totalCount' => $allQuery->count(),
            'filters' => $request->only(['q', 'family', 'category', 'district_id']),
            'districtId' => $districtId,
            'districtLabel' => $districtId ? (string) ($districtNames[$districtId] ?? 'District') : null,
            'catalog' => RideCatalog::VEHICLES,
            'drivers' => tap(PlatformDriver::query()->with('user')->orderByDesc('id'), function ($q) use ($request) {
                TerritoryScope::applyDrivers($q, $request->user());
            })->limit(200)->get(),
            'stateNames' => $stateNames,
            'districtNames' => $districtNames,
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
        $actor = $request->user();
        if ($actor && ! TerritoryScope::isUnrestricted($actor) && $actor->district_id) {
            $data['district_id'] = (int) $actor->district_id;
            $ownDistrict = DB::connection('platform')->table('districts')->where('id', $data['district_id'])->first();
            if ($ownDistrict) {
                $data['state_id'] = (int) $ownDistrict->state_id;
            }
        }
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

    public function show(Request $request, PlatformVehicle $vehicle): View
    {
        abort_unless($request->user()?->can('vehicles.view'), 403);
        $visible = PlatformVehicle::query()->whereKey($vehicle->id);
        TerritoryScope::applyVehicles($visible, $request->user());
        abort_unless($visible->exists(), 404);
        $vehicle->load(['driver.user']);
        $documents = collect();
        if (Schema::connection('platform')->hasTable('vehicle_documents')) {
            $documents = DB::connection('platform')->table('vehicle_documents')
                ->where('vehicle_id', $vehicle->id)
                ->orderBy('id')
                ->get();
        }
        $fleetName = null;
        if ($vehicle->fleet_owner_id) {
            $fleetName = DB::connection('platform')->table('fleet_owners')->where('id', $vehicle->fleet_owner_id)->value('trade_name');
        }
        $trips = 0;
        $monthTrips = 0;
        if (Schema::connection('platform')->hasTable('bookings')) {
            $trips = (int) DB::connection('platform')->table('bookings')->where('vehicle_id', $vehicle->id)->count();
            $monthTrips = (int) DB::connection('platform')->table('bookings')
                ->where('vehicle_id', $vehicle->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        }
        $familyLabel = 'Other';
        foreach (self::FAMILIES as $key => $cats) {
            if (in_array(strtoupper((string) $vehicle->category), $cats, true)) {
                $familyLabel = ucfirst(strtolower($key));
                break;
            }
        }

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'familyLabel' => $familyLabel,
            'catalogLabel' => RideCatalog::VEHICLES[strtoupper((string) $vehicle->category)]['label'] ?? $vehicle->category,
            'stateName' => DB::connection('platform')->table('states')->where('id', $vehicle->state_id)->value('name'),
            'districtName' => DB::connection('platform')->table('districts')->where('id', $vehicle->district_id)->value('name'),
            'fleetName' => $fleetName,
            'documents' => $documents,
            'trips' => $trips,
            'monthTrips' => $monthTrips,
        ]);
    }

    public function districts(Request $request): View
    {
        abort_unless($request->user()?->can('vehicles.view'), 403);
        $query = PlatformVehicle::query()
            ->selectRaw('district_id, state_id, category, count(*) as total')
            ->groupBy('district_id', 'state_id', 'category');
        TerritoryScope::applyVehicles($query, $request->user());
        $rows = $query->get();
        $stateNames = DB::connection('platform')->table('states')->pluck('name', 'id');
        $districtNames = DB::connection('platform')->table('districts')->pluck('name', 'id');
        $byDistrict = [];
        foreach ($rows as $row) {
            $districtId = (int) ($row->district_id ?? 0);
            if (! isset($byDistrict[$districtId])) {
                $byDistrict[$districtId] = [
                    'districtId' => $districtId,
                    'stateId' => (int) ($row->state_id ?? 0),
                    'district' => $districtId ? (string) ($districtNames[$districtId] ?? 'Unknown') : 'Unassigned',
                    'state' => (string) ($stateNames[$row->state_id] ?? ''),
                    'bike' => 0,
                    'auto' => 0,
                    'car' => 0,
                    'other' => 0,
                    'total' => 0,
                ];
            }
            $family = 'other';
            $category = strtoupper((string) $row->category);
            foreach (self::FAMILIES as $key => $cats) {
                if (in_array($category, $cats, true)) {
                    $family = strtolower($key);
                    break;
                }
            }
            $count = (int) $row->total;
            $byDistrict[$districtId][$family] += $count;
            $byDistrict[$districtId]['total'] += $count;
        }
        uasort($byDistrict, function (array $a, array $b) {
            return [$a['state'], $a['district']] <=> [$b['state'], $b['district']];
        });

        return view('vehicles.districts', [
            'rows' => array_values($byDistrict),
            'totals' => [
                'bike' => collect($byDistrict)->sum('bike'),
                'auto' => collect($byDistrict)->sum('auto'),
                'car' => collect($byDistrict)->sum('car'),
                'other' => collect($byDistrict)->sum('other'),
                'total' => collect($byDistrict)->sum('total'),
            ],
        ]);
    }

    public function approve(Request $request, PlatformVehicle $vehicle): RedirectResponse
    {
        abort_unless($request->user()?->can('vehicles.edit') || $request->user()?->can('fleet.manage'), 403);
        $query = PlatformVehicle::query()->whereKey($vehicle->id);
        TerritoryScope::applyVehicles($query, $request->user());
        abort_unless($query->exists(), 404);
        $vehicle->update(['status' => 'available']);

        return redirect()->route('vehicles.index')->with('status', 'Vehicle approved. The fleet owner can start using it.');
    }
}
