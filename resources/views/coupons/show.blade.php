@extends('layouts.app')

@section('title', $coupon['code'])
@section('heading', 'Coupon')

@section('content')
    <p><a href="{{ route('coupons.index') }}"><i data-lucide="arrow-left"></i> All coupons</a></p>
    <div class="hero">
        <div>
            <h1>{{ $coupon['code'] }}</h1>
            <p class="muted">{{ $coupon['title'] }} · {{ $coupon['kind'] }} · {{ $coupon['audience'] }} · {{ $coupon['active'] ? 'active' : 'off' }}</p>
        </div>
    </div>

    @can('platform.admin')
        <section class="card">
            <form method="POST" action="{{ route('coupons.update', $coupon['id']) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="code" value="{{ $coupon['code'] }}">
                <div class="filters">
                    <div><label>Title</label><input name="title" value="{{ $coupon['title'] }}" required></div>
                    <div>
                        <label>Kind</label>
                        <select name="kind">
                            <option value="percent" @selected($coupon['kind'] === 'percent')>Percentage</option>
                            <option value="fixed" @selected($coupon['kind'] === 'fixed')>Fixed</option>
                        </select>
                    </div>
                    <div><label>Percent</label><input name="percent" type="number" min="0" max="100" value="{{ $coupon['percent'] }}"></div>
                    <div><label>Fixed paise</label><input name="amount_paise" type="number" min="0" value="{{ $coupon['amountPaise'] }}"></div>
                    <div><label>Max discount paise</label><input name="max_discount_paise" type="number" min="0" value="{{ $coupon['maxDiscountPaise'] }}"></div>
                    <div><label>Min booking paise</label><input name="min_fare_paise" type="number" min="0" value="{{ $coupon['minFarePaise'] }}"></div>
                    <div>
                        <label>Service</label>
                        <select name="product">
                            <option value="">All services</option>
                            @foreach ($catalog['products'] as $product)
                                <option value="{{ $product }}" @selected($coupon['product'] === $product)>{{ $product }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Audience</label>
                        <select name="audience">
                            @foreach ($catalog['audiences'] as $item)
                                <option value="{{ $item['key'] }}" @selected($coupon['audience'] === $item['key'])>{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>State</label>
                        <select name="state_id">
                            <option value="">All states</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}" @selected(($coupon['state']['id'] ?? null) == $state->id)>{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>District</label>
                        <select name="district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected(($coupon['district']['id'] ?? null) == $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Usage limit</label><input name="usage_limit" type="number" min="0" value="{{ $coupon['usageLimit'] }}"></div>
                    <div><label>User limit</label><input name="user_limit" type="number" min="0" value="{{ $coupon['userLimit'] }}"></div>
                    <div><label>Starts</label><input name="starts_on" type="date" value="{{ \Illuminate\Support\Carbon::parse($coupon['startsOn'])->toDateString() }}" required></div>
                    <div><label>Expiry</label><input name="ends_on" type="date" value="{{ \Illuminate\Support\Carbon::parse($coupon['endsOn'])->toDateString() }}" required></div>
                </div>
                <div><label>Subtitle</label><input name="subtitle" value="{{ $coupon['subtitle'] }}"></div>
                <label><input type="checkbox" name="active" value="1" @checked($coupon['active'])> Active</label>
                <button class="btn" type="submit">Save</button>
            </form>
        </section>
    @endcan
@endsection
