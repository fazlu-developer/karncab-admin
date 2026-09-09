@extends('layouts.app')

@section('title', 'Coupons')
@section('heading', 'Coupons & loyalty')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="ticket-percent"></i> Coupons, offers & loyalty</h1>
            <p class="muted">{{ $catalog['note'] }} Loyalty: {{ $catalog['loyalty']['pointsPerCompletedRide'] }} points per completed ride · ₹{{ number_format($catalog['loyalty']['paisePerPoint'] / 100, 2) }} per point.</p>
        </div>
    </div>

    @can('platform.admin')
        <section class="card">
            <h2>Create coupon</h2>
            <form method="POST" action="{{ route('coupons.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Code</label><input name="code" required></div>
                    <div><label>Title</label><input name="title" required></div>
                    <div>
                        <label>Kind</label>
                        <select name="kind">
                            <option value="percent">Percentage</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </div>
                    <div><label>Percent</label><input name="percent" type="number" min="0" max="100" value="10"></div>
                    <div><label>Fixed paise</label><input name="amount_paise" type="number" min="0" value="0"></div>
                    <div><label>Max discount paise</label><input name="max_discount_paise" type="number" min="0" value="0"></div>
                    <div><label>Min booking paise</label><input name="min_fare_paise" type="number" min="0" value="0"></div>
                    <div>
                        <label>Service</label>
                        <select name="product">
                            <option value="">All services</option>
                            @foreach ($catalog['products'] as $product)
                                <option value="{{ $product }}">{{ $product }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Audience</label>
                        <select name="audience">
                            @foreach ($catalog['audiences'] as $item)
                                <option value="{{ $item['key'] }}">{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>State</label>
                        <select name="state_id">
                            <option value="">All states</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>District</label>
                        <select name="district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Usage limit</label><input name="usage_limit" type="number" min="0" value="0"></div>
                    <div><label>User limit</label><input name="user_limit" type="number" min="0" value="1"></div>
                    <div><label>Starts</label><input name="starts_on" type="date" required></div>
                    <div><label>Expiry</label><input name="ends_on" type="date" required></div>
                </div>
                <div><label>Subtitle</label><input name="subtitle"></div>
                <label><input type="checkbox" name="active" value="1" checked> Active</label>
                <button class="btn" type="submit">Save coupon</button>
            </form>
        </section>

        <section class="card">
            <h2>Server preview</h2>
            <form method="POST" action="{{ route('coupons.preview') }}">
                @csrf
                <div class="filters">
                    <div><label>Code</label><input name="code" required></div>
                    <div><label>Fare paise</label><input name="fare_paise" type="number" min="0" required></div>
                    <div>
                        <label>Service</label>
                        <select name="product">
                            <option value="">Any</option>
                            @foreach ($catalog['products'] as $product)
                                <option value="{{ $product }}">{{ $product }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="muted">Do not send a discount amount. The server quotes it.</p>
                <button class="btn ghost" type="submit">Quote on server</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Coupons</h2>
        @if ($coupons === [])
            <p class="muted">No coupons configured.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Kind</th>
                            <th>Audience</th>
                            <th>Target</th>
                            <th>Expiry</th>
                            <th>Uses</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coupons as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['title'] }}{{ $row['active'] ? '' : ' (off)' }}</td>
                                <td>{{ $row['kind'] }} {{ $row['kind'] === 'percent' ? $row['percent'].'%' : '₹'.number_format($row['amountPaise'] / 100, 2) }}</td>
                                <td>{{ $row['audience'] }}</td>
                                <td>{{ $row['product'] ?: 'all' }} / {{ $row['state']['name'] ?? '—' }} / {{ $row['district']['name'] ?? '—' }}</td>
                                <td>{{ $row['endsOn'] }}</td>
                                <td>{{ $row['redemptions'] }}</td>
                                <td><a class="icon-btn" href="{{ route('coupons.show', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
