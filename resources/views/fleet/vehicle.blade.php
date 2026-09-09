@extends('layouts.app')

@section('title', $row['registrationNo'])
@section('heading', 'Vehicle')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $row['registrationNo'] }}</h1>
            <p class="muted">{{ $row['category'] }} · {{ $row['statusLabel'] }}{{ $row['assignedDriver'] ? ' · '.$row['assignedDriver']['name'] : '' }}</p>
        </div>
        <a class="btn ghost" href="{{ route('fleet.vehicles') }}">Vehicles</a>
    </div>
    @can('fleet.manage')
        <section class="card">
            <h3 style="margin-top:0">Edit vehicle</h3>
            <form method="POST" action="{{ route('fleet.vehicle.update', $row['id']) }}">
                @csrf @method('PATCH')
                <div class="field"><label>Vehicle number</label><input name="registration_no" value="{{ $row['registrationNo'] }}" required></div>
                <div class="field">
                    <label>Type</label>
                    <select name="category">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected($row['category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Brand</label><input name="brand" value="{{ $row['brand'] }}"></div>
                <div class="field"><label>Model</label><input name="model" value="{{ $row['model'] }}"></div>
                <div class="field"><label>Year</label><input name="year" type="number" value="{{ $row['year'] }}"></div>
                <div class="field"><label>Color</label><input name="color" value="{{ $row['color'] }}"></div>
                <div class="field"><label>Fuel</label><input name="fuel" value="{{ $row['fuel'] }}"></div>
                <div class="field">
                    <label>Stored status</label>
                    <select name="status">
                        @foreach ($writableStatuses as $status)
                            <option value="{{ $status }}" @selected($row['storedStatus'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn" type="submit">Save</button>
            </form>
        </section>
        <section class="card">
            <h3 style="margin-top:0">RC, insurance, permit, fitness, photos</h3>
            <form method="POST" action="{{ route('fleet.vehicle.documents', $row['id']) }}">
                @csrf
                <div class="filters">
                    <select name="type">
                        @foreach ($docTypes as $type)
                            <option value="{{ $type }}">{{ $docLabels[$type] }}</option>
                        @endforeach
                    </select>
                    <input name="storage_key" placeholder="Storage key" required>
                    <input name="original_name" placeholder="File name">
                    <input type="date" name="expires_at" title="Expiry">
                    <button class="btn" type="submit">Save document</button>
                </div>
            </form>
            <ul>
                @foreach ($row['documents'] as $doc)
                    <li>{{ $doc['label'] }} · {{ $doc['status'] }} · expires {{ $doc['expiresAt'] ?: '—' }}</li>
                @endforeach
            </ul>
        </section>
    @endcan
    <section class="card">
        <h3 style="margin-top:0">Utilization</h3>
        <p>{{ $row['utilization']['completedTrips'] }} completed trips · {{ $row['utilization']['monthTrips'] }} this month · ₹{{ number_format($row['utilization']['monthRevenueRupees'], 2) }}</p>
    </section>
@endsection
