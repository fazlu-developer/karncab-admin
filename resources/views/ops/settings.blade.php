@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="sliders-horizontal"></i> Platform settings</h1>
            <p class="muted">Labeled fields only. Ride radius also lives under Ride Settings; this form is the same values plus wallet eligibility and enquiry email.</p>
        </div>
    </div>
    <section class="card">
        <form method="POST" action="{{ route('ops.settings.save') }}">
            @csrf
            <div class="grid-2">
                <div class="field">
                    <label>Driver search radius (km)</label>
                    <input name="driver_search_radius_km" type="number" step="0.5" min="1" max="100" value="{{ $radiusKm }}" required>
                </div>
                <div class="field">
                    <label>Ride request timeout (seconds)</label>
                    <input name="ride_request_timeout_seconds" type="number" min="10" max="300" value="{{ $timeoutSeconds }}" required>
                </div>
                <div class="field">
                    <label>Driver wallet minimum (₹)</label>
                    <input name="driver_wallet_min_rupees" type="number" min="0" step="1" value="{{ $walletMinRupees }}">
                </div>
                <div class="field">
                    <label>Wallet min % of fare</label>
                    <input name="driver_wallet_min_fare_percent" type="number" min="0" max="100" step="0.1" value="{{ $walletMinPercent }}">
                    <p class="muted">10 means a ₹1,000 wallet can accept a ₹10,000 booking.</p>
                </div>
                <div class="field">
                    <label>Website enquiry notify email</label>
                    <input name="leads_notify_email" type="email" value="{{ $leadsEmail }}">
                </div>
            </div>
            <p>
                <label>
                    <input type="checkbox" name="driver_wallet_must_cover_commission" value="1" @checked($walletCoverCommission)>
                    Driver wallet must cover trip commission before going online / accepting
                </label>
            </p>
            <button class="btn" type="submit">Save settings</button>
        </form>
    </section>
@endsection
