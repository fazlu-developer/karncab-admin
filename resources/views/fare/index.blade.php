@extends('layouts.app')

@section('title', 'Fare Management')
@section('heading', 'Fare Management')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="banknote"></i> Vehicle fares by service</h1>
            <p class="muted">Pick a service, then set the fare for each vehicle and press Save. Cab quotes use these rows. Parcel is its own list and includes Truck. Live states: {{ implode(', ', $liveStates) }}.</p>
        </div>
    </div>

    <nav class="service-nav">
        <a class="{{ $selectedService === '' ? 'active' : '' }}" href="{{ route('fare.index') }}">All services</a>
        @foreach ($services as $key => $label)
            <a class="{{ $selectedService === $key ? 'active' : '' }}" href="{{ route('fare.index', ['service' => $key]) }}">{{ $label }}</a>
        @endforeach
        <a class="{{ $selectedService === 'PARCEL' ? 'active' : '' }}" href="{{ route('fare.index', ['service' => 'PARCEL']) }}">Parcel</a>
    </nav>

    @foreach ($services as $product => $label)
        @continue($selectedService !== '' && $selectedService !== $product)
        @php
            $rows = $rules->where('product', $product)->keyBy('category');
        @endphp
        <section class="card" id="service-{{ $product }}">
            <h2>{{ $label }}</h2>
            <p class="muted">Set the fare for each vehicle on {{ $label }}.</p>
            @foreach ($vehicles as $category => $vehicleLabel)
                @php $row = $rows->get($category); @endphp
                <form class="fare-line" method="POST" action="{{ $row ? route('fare.update', $row->id) : route('fare.store') }}">
                    @csrf
                    @if ($row) @method('PATCH') @endif
                    <input type="hidden" name="product" value="{{ $product }}">
                    <input type="hidden" name="category" value="{{ $category }}">
                    <input type="hidden" name="district_id" value="{{ $row->district_id ?? '' }}">
                    <input type="hidden" name="cancel_rupees" value="{{ $row ? $row->cancel_paise / 100 : 0 }}">
                    @if ($product !== 'RENTAL')
                        <input type="hidden" name="rental_hours" value="{{ $row->rental_hours ?? '' }}">
                        <input type="hidden" name="extra_hour_rupees" value="{{ $row ? ($row->extra_hour_paise ?? 0) / 100 : 150 }}">
                    @endif
                    @if ($product !== 'ROUND_WAY')
                        <input type="hidden" name="driver_allow_rupees" value="{{ $row ? ($row->driver_allow_paise ?? 0) / 100 : 0 }}">
                        <input type="hidden" name="night_stay_rupees" value="{{ $row ? ($row->night_stay_paise ?? 0) / 100 : 0 }}">
                    @endif
                    @if ($product !== 'MULTI_STOP' && $product !== 'RENTAL')
                        <input type="hidden" name="stop_rupees" value="{{ $row ? ($row->stop_paise ?? 0) / 100 : 0 }}">
                    @endif
                    <strong>{{ $vehicleLabel }}</strong>
                    <label>Min km<input name="min_km" type="number" step="0.1" value="{{ $row->min_km ?? 2 }}" required></label>
                    <label>Included km<input name="included_km" type="number" step="0.1" value="{{ $row->included_km ?? 2 }}" required></label>
                    <label>₹ / km<input name="per_km_rupees" type="number" step="0.01" value="{{ $row ? $row->per_km_paise / 100 : 12 }}" required></label>
                    <label>Extra ₹ / km<input name="extra_km_rupees" type="number" step="0.01" value="{{ $row ? $row->extra_km_paise / 100 : 14 }}" required></label>
                    <label>Wait ₹ / min<input name="waiting_per_min_rupees" type="number" step="0.01" value="{{ $row ? $row->waiting_paise_per_min / 100 : 1 }}" required></label>
                    <label>Night %<input name="night_percent" type="number" value="{{ $row->night_percent ?? 20 }}" required></label>
                    <label>GST %<input name="gst_percent" type="number" value="{{ $row->gst_percent ?? 5 }}" required></label>
                    @if ($product === 'RENTAL')
                        <label>Hours<input name="rental_hours" type="number" value="{{ $row->rental_hours ?? 8 }}"></label>
                        <label>Extra hour ₹<input name="extra_hour_rupees" type="number" step="0.01" value="{{ $row ? ($row->extra_hour_paise ?? 15000) / 100 : 150 }}"></label>
                    @endif
                    @if ($product === 'ROUND_WAY')
                        <label>Driver allow ₹<input name="driver_allow_rupees" type="number" step="0.01" value="{{ $row ? ($row->driver_allow_paise ?? 0) / 100 : 0 }}"></label>
                        <label>Night stay ₹<input name="night_stay_rupees" type="number" step="0.01" value="{{ $row ? ($row->night_stay_paise ?? 0) / 100 : 0 }}"></label>
                    @endif
                    @if ($product === 'MULTI_STOP' || $product === 'RENTAL')
                        <label>Stop ₹<input name="stop_rupees" type="number" step="0.01" value="{{ $row ? ($row->stop_paise ?? 0) / 100 : 0 }}"></label>
                    @endif
                    <label class="fare-check">On<input type="checkbox" name="active" value="1" @checked(! $row || $row->active)></label>
                    <button class="btn" type="submit">Save</button>
                </form>
            @endforeach
        </section>
    @endforeach

    @if ($selectedService === '' || $selectedService === 'PARCEL')
        <section class="card" id="service-PARCEL">
            <h2>Parcel</h2>
            <p class="muted">Set each delivery vehicle’s fare. Bike, Auto, Car, Van, and Truck are listed for local parcels and for Bihar parcels. Choose Delivery Partner uses these amounts.</p>
            @foreach ($parcelLanes as $lane => $laneLabel)
                @php $laneRows = $parcelRules->where('lane', $lane)->keyBy('category'); @endphp
                <h3 style="margin:18px 0 8px">{{ $laneLabel }}</h3>
                @foreach ($parcelVehicles as $category => $vehicleLabel)
                    @php $row = $laneRows->get($category); @endphp
                    <form class="fare-line" method="POST" action="{{ $row ? route('fare.parcel.update', $row->id) : route('fare.parcel.store') }}">
                        @csrf
                        @if ($row) @method('PATCH') @endif
                        <input type="hidden" name="lane" value="{{ $lane }}">
                        <input type="hidden" name="category" value="{{ $category }}">
                        <strong>{{ $vehicleLabel }}</strong>
                        <label>Min charge ₹<input name="min_charge_rupees" type="number" step="0.01" value="{{ $row ? $row->min_charge_paise / 100 : 49 }}" required></label>
                        <label>₹ / km<input name="per_km_rupees" type="number" step="0.01" value="{{ $row ? $row->per_km_paise / 100 : 15 }}" required></label>
                        <label>Extra ₹ / km<input name="extra_km_rupees" type="number" step="0.01" value="{{ $row ? $row->extra_km_paise / 100 : 18 }}" required></label>
                        <label>₹ / kg<input name="per_kg_rupees" type="number" step="0.01" value="{{ $row ? $row->per_kg_paise / 100 : 2 }}" required></label>
                        <label>Min km<input name="min_km" type="number" step="0.1" value="{{ $row->min_km ?? 1 }}" required></label>
                        <label>Included km<input name="included_km" type="number" step="0.1" value="{{ $row->included_km ?? 2 }}" required></label>
                        <label>GST %<input name="gst_percent" type="number" value="{{ $row->gst_percent ?? 5 }}" required></label>
                        <label class="fare-check">On<input type="checkbox" name="active" value="1" @checked(! $row || $row->active)></label>
                        <button class="btn" type="submit">Save</button>
                    </form>
                @endforeach
            @endforeach
        </section>
    @endif
@endsection
