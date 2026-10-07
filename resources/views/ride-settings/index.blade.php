@extends('layouts.app')

@section('title', 'Ride Settings')
@section('heading', 'Ride Settings')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="radar"></i> Driver Search Radius</h1>
            <p class="muted">Customer booking search uses this global radius. Apps do not hardcode the distance. Default is 20 km.</p>
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
                <input name="ride_request_timeout_seconds" type="number" min="10" max="900" value="{{ $timeoutSeconds }}" required>
            </div>
            <div>
                <button class="btn" type="submit">Save</button>
            </div>
        </form>
        <p class="muted">Current radius: <strong>{{ $radiusKm }} km</strong>. Only online, available drivers with a matching vehicle inside this radius receive a request.</p>
    </section>

    <section class="card">
        <h2>Saharsa launch incentives</h2>
        <p class="muted">Bonuses pay only after genuine completed rides — not on registration. Driver amounts go to the driver wallet. Customer amounts are promotional ride credit (not cash).</p>
        <form method="POST" action="{{ route('ride-settings.update') }}" class="filters" style="align-items:end">
            @csrf
            @method('PUT')
            <input type="hidden" name="driver_search_radius_km" value="{{ $radiusKm }}">
            <input type="hidden" name="ride_request_timeout_seconds" value="{{ $timeoutSeconds }}">
            <div>
                <label>Driver joining (₹)</label>
                <input name="driver_welcome_bonus_rupees" type="number" min="0" max="100000" value="{{ $driverWelcomeBonusRupees }}" required>
            </div>
            <div>
                <label>Driver referral (₹)</label>
                <input name="driver_referral_bonus_rupees" type="number" min="0" max="100000" value="{{ $driverReferralBonusRupees }}" required>
            </div>
            <div>
                <label>Referral rides required</label>
                <input name="driver_referral_required_rides" type="number" min="1" max="500" value="{{ $driverReferralRequiredRides }}" required>
            </div>
            <div>
                <label>Customer joining credit (₹)</label>
                <input name="customer_joining_credit_rupees" type="number" min="0" max="100000" value="{{ $customerJoiningCreditRupees }}" required>
            </div>
            <div>
                <label>Customer referral credit (₹)</label>
                <input name="customer_referral_credit_rupees" type="number" min="0" max="100000" value="{{ $customerReferralCreditRupees }}" required>
            </div>
            <div>
                <label>Promo max per ride (₹)</label>
                <input name="customer_promo_max_rupees" type="number" min="0" max="100000" value="{{ $customerPromoMaxRupees }}" required>
            </div>
            <div>
                <label>Promo max % of fare</label>
                <input name="customer_promo_max_fare_percent" type="number" min="0" max="100" step="1" value="{{ $customerPromoMaxFarePercent }}" required>
            </div>
            <div>
                <label><input type="checkbox" name="driver_welcome_bonus_enabled" value="1" @checked($driverWelcomeBonusEnabled)> Enable driver joining bonus</label>
            </div>
            <div>
                <label><input type="checkbox" name="customer_first_ride_free_enabled" value="1" @checked($customerFirstRideFreeEnabled)> First customer ride 100% free (legacy)</label>
            </div>
            <div>
                <button class="btn" type="submit">Save offers</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Incentive ledger</h2>
        <p class="muted">Paid ₹{{ number_format(($incentiveTotals['paidPaise'] ?? 0) / 100, 0) }} · {{ $incentiveTotals['count'] ?? 0 }} rows · pending {{ $incentiveTotals['pending'] ?? 0 }} · reversed {{ $incentiveTotals['reversed'] ?? 0 }}</p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Kind</th>
                    <th>₹</th>
                    <th>Status</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incentives as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->user_id }}</td>
                        <td>{{ $row->kind }}</td>
                        <td>{{ number_format(((int) $row->amount_paise) / 100, 0) }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No incentives posted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
