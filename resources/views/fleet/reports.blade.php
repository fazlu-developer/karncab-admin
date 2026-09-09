@extends('layouts.app')

@section('title', 'Fleet reports')
@section('heading', 'Reports')

@section('content')
    <div class="hero">
        <div>
            <h1>Fleet reports</h1>
            <p class="muted">{{ $fleet['tradeName'] }} — trips, revenue, earnings, commission and utilization for this fleet only.</p>
            <p><a class="btn ghost" href="{{ route('reports.index') }}">CSV / Excel / PDF reports</a></p>
        </div>
    </div>
    <div class="kpis">
        <div class="kpi"><div class="label">Trips today</div><b>{{ $trips['today'] }}</b></div>
        <div class="kpi"><div class="label">Trips month</div><b>{{ $trips['month'] }}</b></div>
        <div class="kpi"><div class="label">Revenue month ₹</div><b>{{ number_format($revenue['monthRupees'], 2) }}</b></div>
        <div class="kpi"><div class="label">Commission {{ $commission['percent'] }}%</div><b>₹{{ number_format($commission['monthRupees'], 2) }}</b></div>
    </div>
    <section class="card">
        <h3 style="margin-top:0">Vehicle utilization</h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Vehicle</th><th>Trips</th><th>Month</th><th>Month ₹</th></tr></thead>
                <tbody>
                @forelse ($vehicleUtilization as $row)
                    <tr>
                        <td>{{ $row['registrationNo'] }}</td>
                        <td>{{ $row['completedTrips'] }}</td>
                        <td>{{ $row['monthTrips'] }}</td>
                        <td>{{ number_format($row['monthRevenueRupees'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No vehicles.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h3 style="margin-top:0">Driver performance</h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Driver</th><th>Trips</th><th>Month</th><th>Rating</th><th>Week net ₹</th></tr></thead>
                <tbody>
                @forelse ($driverPerformance as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['completedTrips'] }}</td>
                        <td>{{ $row['monthTrips'] }}</td>
                        <td>{{ number_format($row['ratingAvg'], 1) }}</td>
                        <td>{{ number_format($row['earnings']['week']['netRupees'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No drivers.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h3 style="margin-top:0">Recent trips</h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Ref</th><th>Status</th><th>Route</th><th>₹</th><th>Vehicle</th></tr></thead>
                <tbody>
                @forelse ($recentTrips as $trip)
                    <tr>
                        <td>{{ $trip['publicRef'] }}</td>
                        <td>{{ $trip['status'] }}</td>
                        <td>{{ $trip['pickupText'] }} → {{ $trip['dropText'] }}</td>
                        <td>{{ number_format($trip['quoteRupees'], 2) }}</td>
                        <td>{{ $trip['registrationNo'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No trips.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
