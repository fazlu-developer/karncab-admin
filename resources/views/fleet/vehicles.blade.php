@extends('layouts.app')

@section('title', 'Vehicles')
@section('heading', 'Vehicles')

@section('content')
    <div class="hero">
        <div>
            <h1>Vehicles</h1>
            <p class="muted">All fleet vehicles by type: Bike, Auto and Car.</p>
        </div>
    </div>
    @can('fleet.manage')
        <section class="card">
            <h3 style="margin-top:0">Add vehicle</h3>
            <form method="POST" action="{{ route('fleet.vehicles.store') }}">
                @csrf
                <div class="field"><label>Vehicle number</label><input name="registration_no" required></div>
                <div class="field">
                    <label>Vehicle type</label>
                    <select name="category" required>
                        <optgroup label="Bike">
                            <option value="BIKE">Bike</option>
                        </optgroup>
                        <optgroup label="Auto">
                            <option value="AUTO">Auto</option>
                            <option value="E_RICKSHAW">E-Rickshaw</option>
                        </optgroup>
                        <optgroup label="Car">
                            <option value="MINI">Mini / Hatchback</option>
                            <option value="SEDAN">Sedan</option>
                            <option value="SUV">SUV</option>
                            <option value="TRAVELLER">Traveller / XL</option>
                        </optgroup>
                    </select>
                </div>
                <div class="field"><label>Brand</label><input name="brand"></div>
                <div class="field"><label>Model</label><input name="model"></div>
                <div class="field"><label>Year</label><input name="year" type="number"></div>
                <div class="field"><label>Color</label><input name="color"></div>
                <div class="field"><label>Fuel</label><input name="fuel"></div>
                <button class="btn" type="submit"><i data-lucide="plus"></i> Add vehicle</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Number</th><th>Type</th><th>Status</th><th>Alerts</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['registrationNo'] }}</strong></td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['statusLabel'] }}</td>
                        <td class="muted">{{ implode(', ', $row['documentAlerts']) ?: '—' }}</td>
                        <td><a class="icon-btn" href="{{ route('fleet.vehicle', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No vehicles in this fleet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
