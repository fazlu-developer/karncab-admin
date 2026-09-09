@extends('layouts.app')

@section('title', 'Fleet drivers')
@section('heading', 'Drivers')

@section('content')
    <div class="hero">
        <div>
            <h1>Drivers</h1>
            <p class="muted">Add, assign and review KYC for drivers in this fleet only.</p>
        </div>
    </div>
    @can('fleet.manage')
        <section class="card">
            <h3 style="margin-top:0">Add driver</h3>
            <form method="POST" action="{{ route('fleet.drivers.store') }}">
                @csrf
                <div class="field"><label>Name</label><input name="name" required></div>
                <div class="field"><label>Email</label><input name="email" type="email" required></div>
                <div class="field"><label>Phone</label><input name="phone"></div>
                <div class="field"><label>License</label><input name="license_no"></div>
                <div class="field"><label>City</label><input name="city"></div>
                <div class="field"><label>Password</label><input name="password" type="password" required></div>
                <button class="btn" type="submit"><i data-lucide="plus"></i> Add driver</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Name</th><th>KYC</th><th>Status</th><th>Vehicle</th><th>Trips</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong><div class="muted">{{ $row['email'] }}</div></td>
                        <td>{{ $row['kycStatus'] }}</td>
                        <td>{{ $row['accountStatus'] }} · {{ $row['dutyStatus'] }}</td>
                        <td>{{ $row['assignedVehicle']['registrationNo'] ?? '—' }}</td>
                        <td>{{ $row['performance']['completedTrips'] }}</td>
                        <td><a class="icon-btn" href="{{ route('fleet.driver', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No drivers in this fleet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
