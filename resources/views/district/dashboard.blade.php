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
            ['bookings', 'Bookings', 'calendar-check'],
            ['completed', 'Completed', 'circle-check'],
            ['revenueRupees', 'Revenue ₹', 'banknote'],
            ['vehicles', 'Vehicles', 'car'],
            ['drivers', 'Drivers', 'id-card'],
            ['fleets', 'Fleets', 'warehouse'],
            ['todayCommissionRupees', '4% today ₹', 'percent'],
            ['monthCommissionRupees', '4% month ₹', 'wallet'],
        ] as [$key, $label, $icon])
            <div class="kpi">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </div>
        @endforeach
    </div>
@endsection
