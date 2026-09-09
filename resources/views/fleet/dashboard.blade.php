@extends('layouts.app')

@section('title', 'Fleet')
@section('heading', 'Fleet')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $fleet['tradeName'] }}</h1>
            <p class="muted">Vehicles, drivers and trips for this fleet only. Other fleets are not visible.</p>
        </div>
        <a class="btn" href="{{ route('fleet.vehicles') }}"><i data-lucide="plus"></i> Add vehicle</a>
    </div>
    <div class="kpis">
        @foreach ([
            ['vehicles', 'Vehicles', 'car'],
            ['drivers', 'Drivers', 'id-card'],
            ['onlineDrivers', 'Online', 'radio'],
            ['tripsToday', 'Trips today', 'navigation'],
            ['todayRevenueRupees', 'Today ₹', 'banknote'],
        ] as [$key, $label, $icon])
            <div class="kpi">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </div>
        @endforeach
    </div>
    <section class="card">
        <h3 style="margin-top:0">Vehicles</h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Number</th><th>Type</th><th>Status</th><th>Driver</th><th></th></tr></thead>
                <tbody>
                @forelse ($vehicles as $row)
                    <tr>
                        <td><strong>{{ $row['registrationNo'] }}</strong></td>
                        <td>{{ $row['category'] }}</td>
                        <td><span class="pill {{ $row['locked'] ? 'bad' : ($row['status'] === 'on_trip' ? 'ok' : 'muted') }}">{{ $row['statusLabel'] }}</span></td>
                        <td>{{ $row['assignedDriver']['name'] ?? '—' }}</td>
                        <td><a class="icon-btn" href="{{ route('fleet.vehicle', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No vehicles yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
