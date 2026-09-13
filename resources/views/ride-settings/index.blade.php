@extends('layouts.app')

@section('title', 'Ride Settings')
@section('heading', 'Ride Settings')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="radar"></i> Driver Search Radius</h1>
            <p class="muted">Customer booking search uses this global radius. Apps do not hardcode the distance. Typical values: 5, 10, 15 or 20 km.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif

    <section class="card">
        <h2>Ride matching</h2>
        <form method="POST" action="{{ route('ride-settings.update') }}" class="filters" style="align-items:end">
            @csrf
            @method('PUT')
            <div>
                <label>Driver search radius (km)</label>
                <input name="driver_search_radius_km" type="number" step="0.5" min="1" max="100" value="{{ $radiusKm }}" required>
            </div>
            <div>
                <label>Ride request timeout (seconds)</label>
                <input name="ride_request_timeout_seconds" type="number" min="10" max="300" value="{{ $timeoutSeconds }}" required>
            </div>
            <div>
                <button class="btn" type="submit">Save</button>
            </div>
        </form>
        <p class="muted">Current radius: <strong>{{ $radiusKm }} km</strong>. Only online, available drivers with a matching vehicle inside this radius receive a request.</p>
    </section>
@endsection
