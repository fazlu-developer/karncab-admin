@extends('layouts.app')

@section('title', 'Fleet commission')
@section('heading', 'Fleet commission')

@section('content')
    <div class="hero">
        <div>
            <h1>Fleet cut from the 4%</h1>
            <p class="muted">{{ $district['districtName'] }}. Each percent is a share of the completed fare. The cap is {{ $capPercent }}%, which is the whole District Head pool. Super Admin keeps 6% of the fare.</p>
        </div>
    </div>
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Fleet</th>
                        <th>Cut (% of fare)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($fleets as $fleet)
                    <tr>
                        <td><strong>{{ $fleet['name'] }}</strong></td>
                        <td colspan="2">
                            <form method="POST" action="{{ route('district.fleet-cut') }}" class="filters">
                                @csrf
                                <input type="hidden" name="fleet_id" value="{{ $fleet['id'] }}">
                                <input name="percent" type="number" min="0" max="{{ $capPercent }}" step="0.01" value="{{ $fleet['percent'] }}" required>
                                <button class="btn" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No fleets in this district yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
