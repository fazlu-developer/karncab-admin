@extends('layouts.app')

@section('title', 'District dashboard')
@section('heading', 'District dashboard')

@section('content')
    <div class="hero">
        <div>
            <h1>Hello, {{ auth()->user()->name }}</h1>
            <p class="muted">{{ $district['districtName'] }}, {{ $district['stateName'] }}. Bookings, fleets, and the 4% district commission are limited to this district.</p>
        </div>
        <span class="pill ok"><i data-lucide="map-pinned"></i> {{ $district['districtName'] }}</span>
    </div>
    <div class="kpis">
        @foreach ([
            ['bookings', 'Bookings', 'calendar-check', route('ops.module', 'bookings')],
            ['completed', 'Completed', 'circle-check', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['revenueRupees', 'District revenue ₹', 'banknote', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['districtEarningRupees', 'District earnings ₹', 'badge-percent', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['todayCommissionRupees', 'District earnings today ₹', 'percent', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['monthCommissionRupees', 'District earnings month ₹', 'wallet', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['commissionRupees', 'Ride commission ₹', 'percent', route('wallets.index')],
            ['platformShareRupees', 'Platform share ₹', 'landmark', route('wallets.index')],
            ['vehicles', 'Vehicles', 'car', route('vehicles.index')],
            ['drivers', 'Drivers', 'id-card', route('drivers.index')],
            ['fleets', 'Fleets', 'warehouse', route('fleet-owners.index')],
        ] as [$key, $label, $icon, $href])
            <a class="kpi" href="{{ $href }}">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </a>
        @endforeach
    </div>
@endsection
