@extends('layouts.app')

@section('title', 'State dashboard')
@section('heading', 'State dashboard')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $state['stateName'] }}</h1>
            <p class="muted">State Head workspace. Data is limited to this state: districts, fleets, drivers, vehicles and bookings.</p>
        </div>
        <span class="pill ok"><i data-lucide="shield-check"></i> Assigned state #{{ $state['stateId'] }}</span>
    </div>
    <div class="kpis">
        @foreach ([
            ['districts', 'Districts', 'map-pinned'],
            ['districtHeads', 'District Heads', 'user-cog'],
            ['franchises', 'Franchises', 'store'],
            ['fleets', 'Fleets', 'warehouse'],
            ['drivers', 'Drivers', 'id-card'],
            ['onlineDrivers', 'Online drivers', 'radio'],
            ['vehicles', 'Vehicles', 'car'],
            ['activeRides', 'Active rides', 'navigation'],
            ['completedRides', 'Completed', 'circle-check'],
            ['parcels', 'Parcels', 'package'],
            ['todayRevenueRupees', 'Today ₹', 'banknote'],
            ['monthlyRevenueRupees', 'Month ₹', 'wallet'],
            ['openComplaints', 'Open complaints', 'message-circle-warning'],
        ] as [$key, $label, $icon])
            <div class="kpi">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </div>
        @endforeach
    </div>
    <section class="card">
        <h3 style="margin-top:0">Districts in {{ $state['stateName'] }}</h3>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>District</th>
                        <th>Heads</th>
                        <th>Drivers</th>
                        <th>Vehicles</th>
                        <th>Bookings</th>
                        <th>Revenue ₹</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($districts as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td>{{ $row['districtHeads'] }}</td>
                        <td>{{ $row['drivers'] }}</td>
                        <td>{{ $row['vehicles'] }}</td>
                        <td>{{ $row['bookings'] }}</td>
                        <td>{{ $row['revenueRupees'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No districts in this state yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
