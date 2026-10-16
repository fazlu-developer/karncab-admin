@extends('layouts.app')

@section('title', $vehicle->registration_no)
@section('heading', 'Vehicle')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $vehicle->registration_no }}</h1>
            <p class="muted">{{ $familyLabel }} · {{ $catalogLabel ?: '—' }} · {{ $vehicle->status ?: '—' }}</p>
        </div>
        <div class="row-actions">
            @if ($vehicle->district_id)
                <a class="btn ghost" href="{{ route('vehicles.index', ['district_id' => $vehicle->district_id]) }}"><i data-lucide="arrow-left"></i> District list</a>
            @endif
            <a class="btn ghost" href="{{ route('vehicles.districts') }}">Districts</a>
            <a class="btn ghost" href="{{ route('live.map', ['q' => $vehicle->registration_no, 'vehicle' => $vehicle->id]) }}">Live tracking</a>
        </div>
    </div>

    <section class="card">
        <h2>Vehicle details</h2>
        <dl class="dl">
            <div><dt>Number</dt><dd>{{ $vehicle->registration_no }}</dd></div>
            <div><dt>Family</dt><dd>{{ $familyLabel }}</dd></div>
            <div><dt>Type</dt><dd>{{ $catalogLabel ?: '—' }}</dd></div>
            <div><dt>Status</dt><dd>{{ $vehicle->status ?: '—' }}</dd></div>
            <div><dt>Brand</dt><dd>{{ $vehicle->brand ?: '—' }}</dd></div>
            <div><dt>Model</dt><dd>{{ $vehicle->model ?: '—' }}</dd></div>
            <div><dt>Year</dt><dd>{{ $vehicle->year ?: '—' }}</dd></div>
            <div><dt>Colour</dt><dd>{{ $vehicle->color ?: '—' }}</dd></div>
            <div><dt>Fuel</dt><dd>{{ $vehicle->fuel ?: '—' }}</dd></div>
            <div><dt>State</dt><dd>{{ $stateName ?: '—' }}</dd></div>
            <div><dt>District</dt><dd>{{ $districtName ?: '—' }}</dd></div>
            <div><dt>Fleet</dt><dd>{{ $fleetName ?: '—' }}</dd></div>
            <div>
                <dt>Driver</dt>
                <dd>
                    @if ($vehicle->driver)
                        <a href="{{ route('drivers.show', $vehicle->driver) }}">{{ $vehicle->driver->user->name ?? ('Driver #'.$vehicle->driver->id) }}</a>
                        <div class="muted">{{ $vehicle->driver->user->phone ?? '' }}</div>
                    @else
                        Unassigned
                    @endif
                </dd>
            </div>
            <div><dt>Trips</dt><dd>{{ $trips }} total · {{ $monthTrips }} this month</dd></div>
            <div><dt>Added</dt><dd>{{ $vehicle->created_at ?: '—' }}</dd></div>
            <div><dt>Updated</dt><dd>{{ $vehicle->updated_at ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Documents</h2>
        @if ($documents->isEmpty())
            <p class="muted">No RC, insurance, permit or fitness files on this vehicle yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>File</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($documents as $doc)
                        <tr>
                            <td>{{ $doc->type }}</td>
                            <td>{{ $doc->status ?? '—' }}</td>
                            <td class="cell-wrap">{{ $doc->original_name ?? $doc->storage_key ?? '—' }}</td>
                            <td>{{ $doc->expires_at ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
