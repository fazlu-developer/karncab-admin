@extends('layouts.app')
@section('title', 'Assign Drivers')
@section('heading', 'Assign Drivers')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="user-plus"></i> Assign drivers</h1>
            <p class="muted">Rental, schedule, airport, railway, multi-stop, travel, bulk, one way and round trip bookings wait here. Ops assigns a driver instead of nearby search.</p>
        </div>
    </div>
    <form class="card" method="GET">
        <div class="filters">
            <div>
                <label>Service</label>
                <select name="product" onchange="this.form.submit()">
                    <option value="">All scheduled services</option>
                    @foreach (['RENTAL' => 'Cab Rental', 'SCHEDULE' => 'Schedule Ride', 'LOCAL_CAB' => 'City Ride (scheduled)', 'AIRPORT' => 'Airport', 'RAILWAY' => 'Railway', 'MULTI_STOP' => 'Multi Stop', 'TRAVEL' => 'Travel & Tour', 'BULK' => 'Bulk Booking', 'CORPORATE' => 'Corporate Travel', 'ONE_WAY' => 'One Way', 'ROUND_WAY' => 'Round Trip'] as $key => $label)
                        <option value="{{ $key }}" @selected(($product ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <section class="card">
        <h2>Remaining (unassigned)</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Service</th>
                        <th>When</th>
                        <th>Pickup</th>
                        <th>Customer</th>
                        <th>Cab</th>
                        <th>Fare</th>
                        <th>Assign driver</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($remaining as $row)
                    <tr>
                        <td><a href="{{ route('ops.bookings.show', $row['id']) }}">{{ $row['publicRef'] }}</a></td>
                        <td>{{ $row['product'] }}</td>
                        <td>{{ $row['scheduledAt'] ?: '—' }}</td>
                        <td>{{ $row['pickupText'] }}</td>
                        <td>{{ $row['customer'] }}<br><span class="muted">{{ $row['phone'] }}</span></td>
                        <td>{{ $row['category'] }}</td>
                        <td>₹ {{ number_format($row['quoteRupees']) }}</td>
                        <td>
                            @can('bookings.manage')
                                <form method="POST" action="{{ route('ops.assign-drivers.store', $row['id']) }}">
                                    @csrf
                                    <select name="driver_id" required>
                                        <option value="">Select driver</option>
                                        @foreach ($drivers as $driver)
                                            @php
                                                $match = empty($row['category']) || empty($driver['category']) || strcasecmp((string) $driver['category'], (string) $row['category']) === 0;
                                            @endphp
                                            @if ($match && empty($driver['busy']) && !empty($driver['vehicleId']))
                                                <option value="{{ $driver['id'] }}">{{ $driver['name'] }} · {{ $driver['registrationNo'] }} ({{ $driver['category'] }})</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button class="btn" type="submit">Assign</button>
                                </form>
                            @else
                                <span class="muted">Need bookings.manage</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No remaining scheduled bookings to assign.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Assigned (same scheduled services)</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Service</th>
                        <th>When</th>
                        <th>Pickup</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($assigned as $row)
                    <tr>
                        <td><a href="{{ route('ops.bookings.show', $row['id']) }}">{{ $row['publicRef'] }}</a></td>
                        <td>{{ $row['product'] }}</td>
                        <td>{{ $row['scheduledAt'] ?: '—' }}</td>
                        <td>{{ $row['pickupText'] }}</td>
                        <td>{{ $row['driver'] }}</td>
                        <td>{{ $row['vehicle'] }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No assigned scheduled bookings yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
