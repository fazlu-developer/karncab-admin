@extends('layouts.app')

@section('title', 'State dashboard')
@section('heading', 'State dashboard')

@section('content')
    <div class="hero">
        <div>
            <h1>Hello, {{ auth()->user()->name }}</h1>
            <p class="muted">State: {{ $state['stateName'] }}. This workspace is limited to the selected state.</p>
        </div>
        @if (!empty($states))
            <form method="GET" class="filters">
                <select name="stateId" onchange="this.form.submit()">
                    @foreach ($states as $opt)
                        <option value="{{ $opt['id'] }}" @selected((int) $state['stateId'] === (int) $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <span class="pill ok"><i data-lucide="shield-check"></i> {{ $state['stateName'] }}</span>
        @endif
    </div>
    <div class="kpis">
        @foreach ([
            ['districts', 'Districts', 'map-pinned', route('state.section', 'districts')],
            ['districtHeads', 'District Heads', 'user-cog', route('state.section', 'district-heads')],
            ['franchises', 'Franchises', 'store', route('state.section', 'franchises')],
            ['fleets', 'Fleets', 'warehouse', route('fleet-owners.index')],
            ['drivers', 'Drivers', 'id-card', route('drivers.index')],
            ['onlineDrivers', 'Online drivers', 'radio', route('drivers.index')],
            ['vehicles', 'Vehicles', 'car', route('vehicles.index')],
            ['activeRides', 'Active rides', 'navigation', route('ops.module', ['module' => 'bookings', 'status' => 'ACTIVE'])],
            ['completedRides', 'Completed', 'circle-check', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['parcels', 'Parcels', 'package', route('ops.parcels')],
            ['todayRevenueRupees', 'Today ₹', 'banknote', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['monthlyRevenueRupees', 'Month ₹', 'wallet', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['commissionRupees', 'Ride commission ₹', 'percent', route('wallets.index')],
            ['districtEarningRupees', 'District earnings ₹', 'badge-percent', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['platformShareRupees', 'Platform share ₹', 'landmark', route('wallets.index')],
            ['openComplaints', 'Open complaints', 'message-circle-warning', route('support.index')],
        ] as [$key, $label, $icon, $href])
            <a class="kpi" href="{{ $href }}">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </a>
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
