@extends('layouts.app')

@section('title', 'Vehicles')
@section('heading', 'Vehicles')

@section('content')
    @php
        $family = $filters['family'] ?? '';
        $category = $filters['category'] ?? '';
    @endphp
    <div class="hero">
        <div>
            <h1>Vehicles</h1>
            <p class="muted">All registered vehicles by type: Bike, Auto and Car. Territory scope still applies.</p>
        </div>
    </div>

    <section class="card">
        <div class="row-actions" style="flex-wrap:wrap;gap:8px">
            <a class="btn {{ $family === '' && $category === '' ? '' : 'ghost' }}" href="{{ route('vehicles.index', ['q' => $filters['q'] ?? null]) }}">All ({{ $totalCount ?? array_sum($counts) }})</a>
            <a class="btn {{ $family === 'BIKE' ? '' : 'ghost' }}" href="{{ route('vehicles.index', ['family' => 'BIKE'] + $filters) }}">Bike ({{ $familyCounts['BIKE'] ?? 0 }})</a>
            <a class="btn {{ $family === 'AUTO' ? '' : 'ghost' }}" href="{{ route('vehicles.index', ['family' => 'AUTO'] + $filters) }}">Auto ({{ $familyCounts['AUTO'] ?? 0 }})</a>
            <a class="btn {{ $family === 'CAR' ? '' : 'ghost' }}" href="{{ route('vehicles.index', ['family' => 'CAR'] + $filters) }}">Car ({{ $familyCounts['CAR'] ?? 0 }})</a>
        </div>
        <div class="row-actions" style="flex-wrap:wrap;gap:8px;margin-top:10px">
            @foreach ($catalog as $key => $meta)
                <a class="pill {{ $category === $key ? 'ok' : 'muted' }}" href="{{ route('vehicles.index', ['category' => $key, 'q' => $filters['q'] ?? null]) }}">{{ $meta['label'] }} ({{ $counts[$key] ?? 0 }})</a>
            @endforeach
        </div>
        <form class="filters" method="GET" style="margin-top:16px">
            <input type="hidden" name="family" value="{{ $family }}">
            <input type="hidden" name="category" value="{{ $category }}">
            <div><label>Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Number, brand, model"></div>
            <button class="btn" type="submit">Filter</button>
        </form>
    </section>

    @can('vehicles.edit')
        <section class="card">
            <h2>Add vehicle</h2>
            <form method="POST" action="{{ route('vehicles.store') }}">
                @csrf
                <div class="filters">
                    <div class="field"><label>Registration number</label><input name="registration_no" required></div>
                    <div class="field">
                        <label>Type</label>
                        <select name="category" required>
                            <optgroup label="Bike">
                                <option value="BIKE">Bike</option>
                            </optgroup>
                            <optgroup label="Auto">
                                <option value="AUTO">Auto</option>
                                <option value="E_RICKSHAW">E-Rickshaw</option>
                            </optgroup>
                            <optgroup label="Car">
                                <option value="MINI">Mini / Hatchback</option>
                                <option value="SEDAN">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="TRAVELLER">Traveller / XL</option>
                            </optgroup>
                        </select>
                    </div>
                    @include('partials.geo-fields', [
                        'required' => true,
                        'emptyState' => 'Select state',
                        'emptyDistrict' => 'Select district',
                    ])
                    <div class="field">
                        <label>Assigned driver</label>
                        <select name="driver_id">
                            <option value="">Unassigned</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->user->name ?? ('Driver #'.$driver->id) }} · {{ $driver->user->phone ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Brand</label><input name="brand"></div>
                    <div class="field"><label>Model</label><input name="model"></div>
                    <div class="field"><label>Year</label><input name="year" type="number"></div>
                    <div class="field"><label>Colour</label><input name="color"></div>
                    <div class="field"><label>Fuel</label><input name="fuel"></div>
                </div>
                <button class="btn" type="submit">Save vehicle</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Family</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th>District</th>
                        <th>Make</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($vehicles as $row)
                    @php
                        $familyLabel = 'Other';
                        foreach (\App\Http\Controllers\VehiclesController::FAMILIES as $key => $cats) {
                            if (in_array($row->category, $cats, true)) {
                                $familyLabel = ucfirst(strtolower($key));
                                break;
                            }
                        }
                    @endphp
                    <tr>
                        <td><strong>{{ $row->registration_no }}</strong></td>
                        <td>{{ $familyLabel }}</td>
                        <td>{{ $catalog[$row->category]['label'] ?? $row->category ?: '—' }}</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            @if ($row->driver)
                                <a href="{{ route('drivers.show', $row->driver) }}">{{ $row->driver->user->name ?? 'Driver' }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $districtNames[$row->district_id] ?? '—' }}{{ !empty($stateNames[$row->state_id]) ? ' · '.$stateNames[$row->state_id] : '' }}</td>
                        <td>{{ trim(($row->brand ?? '').' '.($row->model ?? '')) ?: '—' }}</td>
                        <td>
                            @if (in_array(strtolower((string) $row->status), ['pending_review', 'pending'], true))
                                @can('vehicles.edit')
                                    <form method="POST" action="{{ route('vehicles.approve', $row) }}" style="display:inline">
                                        @csrf
                                        <button class="btn" type="submit">Approve</button>
                                    </form>
                                @endcan
                            @elseif ($row->driver)
                                <a class="icon-btn" href="{{ route('drivers.show', $row->driver) }}" title="Driver"><i data-lucide="eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No vehicles in this filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $vehicles->links('pagination.admin') }}
    </section>
@endsection
