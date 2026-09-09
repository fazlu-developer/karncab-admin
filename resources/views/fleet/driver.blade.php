@extends('layouts.app')

@section('title', $row['name'])
@section('heading', 'Driver')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $row['name'] }}</h1>
            <p class="muted">{{ $row['email'] }} · KYC {{ $row['kycStatus'] }} · {{ $row['dutyStatus'] }}</p>
        </div>
        <a class="btn ghost" href="{{ route('fleet.drivers') }}">Drivers</a>
    </div>
    <section class="card">
        <p>Completed trips {{ $row['performance']['completedTrips'] }} · month {{ $row['performance']['monthTrips'] }} · rating {{ number_format($row['performance']['ratingAvg'], 1) }}</p>
        <p class="muted">Earnings today ₹{{ number_format($row['earnings']['today']['netRupees'], 2) }} · week ₹{{ number_format($row['earnings']['week']['netRupees'], 2) }}</p>
    </section>
    @can('fleet.manage')
        <section class="card">
            <h3 style="margin-top:0">Assign vehicle</h3>
            <form method="POST" action="{{ route('fleet.driver.assign', $row['id']) }}">
                @csrf
                <div class="filters">
                    <select name="vehicle_id" required>
                        @foreach ($row['vehicles'] as $vehicle)
                            <option value="{{ $vehicle['id'] }}">{{ $vehicle['registrationNo'] }} ({{ $vehicle['statusLabel'] }})</option>
                        @endforeach
                    </select>
                    <button class="btn" type="submit">Assign</button>
                </div>
            </form>
            @if ($row['assignedVehicle'])
                <form method="POST" action="{{ route('fleet.driver.unassign', $row['id']) }}" style="margin-top:8px">
                    @csrf
                    <input type="hidden" name="vehicle_id" value="{{ $row['assignedVehicle']['id'] }}">
                    <button class="btn ghost" type="submit">Remove from {{ $row['assignedVehicle']['registrationNo'] }}</button>
                </form>
            @endif
        </section>
        <section class="card">
            <h3 style="margin-top:0">KYC and status</h3>
            <form method="POST" action="{{ route('fleet.driver.kyc', $row['id']) }}">
                @csrf
                <div class="filters">
                    <select name="kyc_status">
                        @foreach (['pending','under_review','rejected'] as $kyc)
                            <option value="{{ $kyc }}" @selected($row['kycStatus'] === $kyc)>{{ $kyc }}</option>
                        @endforeach
                    </select>
                    <input name="reason" placeholder="Rejection reason">
                    <button class="btn" type="submit">Save KYC</button>
                </div>
            </form>
            <form method="POST" action="{{ route('fleet.driver.status', $row['id']) }}" style="margin-top:12px">
                @csrf
                <div class="filters">
                    <select name="account_status">
                        @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                            <option value="{{ $status }}" @selected($row['accountStatus'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="duty_status">
                        @foreach (['offline','online','suspended'] as $duty)
                            <option value="{{ $duty }}" @selected($row['dutyStatus'] === $duty)>{{ $duty }}</option>
                        @endforeach
                    </select>
                    <button class="btn" type="submit">Save status</button>
                </div>
            </form>
            <form method="POST" action="{{ route('fleet.driver.documents', $row['id']) }}" style="margin-top:12px">
                @csrf
                <div class="filters">
                    <select name="type">
                        @foreach ($docTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input name="storage_key" placeholder="Storage key" required>
                    <input type="date" name="expires_at">
                    <button class="btn ghost" type="submit">Record document</button>
                </div>
            </form>
            <form method="POST" action="{{ route('fleet.driver.remove', $row['id']) }}" onsubmit="return confirm('Remove this driver from the fleet?')">
                @csrf
                <button class="btn ghost" type="submit" style="margin-top:12px">Remove driver from fleet</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h3 style="margin-top:0">Documents</h3>
        <ul>
            @foreach ($row['documents'] as $doc)
                @php $doc = (array) $doc; @endphp
                <li>{{ $doc['type'] ?? '' }} · {{ $doc['status'] ?? '' }} · {{ $doc['expires_at'] ?? '—' }}</li>
            @endforeach
        </ul>
    </section>
@endsection
