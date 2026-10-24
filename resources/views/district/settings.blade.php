@extends('layouts.app')

@section('title', 'Fleet commission')
@section('heading', 'Fleet commission')

@section('content')
    <div class="hero">
        <div>
            <h1>Fleet share of district commission</h1>
            <p class="muted">{{ $district['districtName'] }}, {{ $district['stateName'] }}. Platform commission is already taken on the trip. Of the District Head commission, a fleet vehicle gives 50% to the fleet owner unless you set a custom percent or a fixed paise amount. An empty percent keeps the 50% default. An independent driver leaves the full district share with the District Head.</p>
        </div>
    </div>
    @if (($districts ?? []) !== [])
        <form class="filters" method="GET">
            <div>
                <label>District</label>
                <select name="districtId" onchange="this.form.submit()">
                    @foreach ($districts as $item)
                        <option value="{{ $item['id'] }}" @selected((int) $district['districtId'] === (int) $item['id'])>{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Fleet</th>
                        <th>Share of district commission</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($fleets as $fleet)
                    <tr>
                        <td><strong>{{ $fleet['name'] }}</strong><div class="muted">{{ $fleet['percent'] === null ? '50% default' : $fleet['percent'].'% custom' }}</div></td>
                        <td colspan="2">
                            <form method="POST" action="{{ route('district.fleet-cut') }}" class="filters">
                                @csrf
                                <input type="hidden" name="fleet_id" value="{{ $fleet['id'] }}">
                                @if (!empty($district['districtId']))
                                    <input type="hidden" name="districtId" value="{{ $district['districtId'] }}">
                                @endif
                                <input name="percent" type="number" min="0" max="100" step="0.01" value="{{ $fleet['percent'] }}" placeholder="50">
                                <input name="paise" type="number" min="0" step="1" value="{{ $fleet['paise'] }}" placeholder="Fixed paise, optional">
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
