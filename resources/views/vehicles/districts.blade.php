@extends('layouts.app')

@section('title', 'District vehicles')
@section('heading', 'District vehicles')

@section('content')
    <div class="hero">
        <div>
            <h1>Vehicles by district</h1>
            <p class="muted">How many bikes, autos and cars are registered in each district. Territory scope still applies.</p>
        </div>
        <a class="btn ghost" href="{{ route('vehicles.index') }}"><i data-lucide="car"></i> All vehicles</a>
    </div>

    <div class="kpis">
        <div class="kpi"><div class="label">Districts</div><b>{{ count($rows) }}</b></div>
        <div class="kpi"><div class="label">Bike</div><b>{{ $totals['bike'] }}</b></div>
        <div class="kpi"><div class="label">Auto</div><b>{{ $totals['auto'] }}</b></div>
        <div class="kpi"><div class="label">Car</div><b>{{ $totals['car'] }}</b></div>
        <div class="kpi"><div class="label">Total</div><b>{{ $totals['total'] }}</b></div>
    </div>

    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>District</th>
                        <th>State</th>
                        <th>Bike</th>
                        <th>Auto</th>
                        <th>Car</th>
                        <th>Other</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['district'] }}</strong></td>
                        <td>{{ $row['state'] ?: '—' }}</td>
                        <td>{{ $row['bike'] }}</td>
                        <td>{{ $row['auto'] }}</td>
                        <td>{{ $row['car'] }}</td>
                        <td>{{ $row['other'] }}</td>
                        <td><strong>{{ $row['total'] }}</strong></td>
                        <td>
                            @if ($row['districtId'])
                                <a class="icon-btn" href="{{ route('vehicles.index', ['district_id' => $row['districtId']]) }}" title="View vehicles"><i data-lucide="eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No vehicles in this territory yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
