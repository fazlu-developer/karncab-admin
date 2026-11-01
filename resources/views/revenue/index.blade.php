@extends('layouts.app')

@section('title', 'Revenue')
@section('heading', 'Revenue')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="banknote"></i> State and district revenue</h1>
            <p class="muted">Completed trips only. Super Admin and Admin can pick a state. A State Head sees that state. A District Head sees that district.</p>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><div class="label">Completed trips</div><b>{{ number_format($trips) }}</b></div>
        <div class="kpi"><div class="label">Today</div><b>₹{{ number_format($todayRupees, 0) }}</b></div>
        <div class="kpi"><div class="label">This month</div><b>₹{{ number_format($monthRupees, 0) }}</b></div>
        <div class="kpi"><div class="label">Fare in view</div><b>₹{{ number_format($fareRupees, 0) }}</b></div>
        <div class="kpi"><div class="label">Commission</div><b>₹{{ number_format($commissionRupees, 0) }}</b></div>
    </div>

    @if ($stateOptions !== [])
        <section class="card">
            <form class="filters" method="GET">
                <div>
                    <label>State</label>
                    <select name="state_id">
                        <option value="">All states</option>
                        @foreach ($stateOptions as $state)
                            <option value="{{ $state->id }}" @selected((int) $selectedStateId === (int) $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button class="btn" type="submit">Show</button>
                </div>
            </form>
        </section>
    @endif

    @forelse ($states as $state)
        <section class="card">
            <h2>{{ $state['name'] }}</h2>
            <p class="muted">{{ number_format($state['trips']) }} trips · Fare ₹{{ number_format($state['fareRupees'], 2) }} · Commission ₹{{ number_format($state['commissionRupees'], 2) }}</p>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>District</th>
                            <th>Trips</th>
                            <th>Fare</th>
                            <th>Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($state['districts'] as $district)
                            <tr>
                                <td>{{ $district['name'] }}</td>
                                <td>{{ number_format($district['trips']) }}</td>
                                <td>₹{{ number_format($district['fareRupees'], 2) }}</td>
                                <td>₹{{ number_format($district['commissionRupees'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="card"><p class="muted">No completed trips in this territory yet.</p></section>
    @endforelse
@endsection
