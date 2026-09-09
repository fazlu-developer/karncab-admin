@extends('layouts.app')

@section('title', 'Commission')
@section('heading', 'Commission')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="percent"></i> Commission engine</h1>
            <p class="muted">{{ $policy['note'] }} Eligible booking amount uses only the buckets enabled below. Example: if the rule is 10% and eligible is ₹10,000, commission is ₹1,000 and net is ₹9,000.</p>
        </div>
        <a class="btn ghost" href="{{ route('wallets.index') }}"><i data-lucide="wallet"></i> Ledger</a>
    </div>

    <section class="card">
        <h2>Active rule <span class="muted">({{ $policy['name'] }})</span></h2>
        <p>Percent: <strong>{{ $policy['percent'] }}%</strong> · Source: {{ $policy['source'] }}{{ empty($policy['configured']) ? ' · not saved yet' : '' }}</p>
        @can('platform.admin')
            <form method="POST" action="{{ route('wallets.commission.update') }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label>Commission percent</label>
                    <input name="percent" type="number" min="0" max="100" step="0.01" value="{{ $policy['percent'] }}" required>
                </div>
                <p class="muted">Apply commission to:</p>
                <div class="field"><label><input type="checkbox" name="on_base_fare" value="1" @checked($policy['onBaseFare'])> Base fare</label></div>
                <div class="field"><label><input type="checkbox" name="on_gst" value="1" @checked($policy['onGst'])> GST</label></div>
                <div class="field"><label><input type="checkbox" name="on_toll" value="1" @checked($policy['onToll'])> Toll</label></div>
                <div class="field"><label><input type="checkbox" name="on_parking" value="1" @checked($policy['onParking'])> Parking</label></div>
                <div class="field"><label><input type="checkbox" name="on_waiting" value="1" @checked($policy['onWaiting'])> Waiting / other time charges</label></div>
                <div class="field"><label><input type="checkbox" name="on_other" value="1" @checked($policy['onOther'])> Other charges</label></div>
                <div class="field"><label><input type="checkbox" name="on_discount" value="1" @checked($policy['onDiscount'])> Discount (include in eligible base)</label></div>
                <div class="field"><label><input type="checkbox" name="on_complete" value="1" @checked($policy['onComplete'])> Complete booking amount</label></div>
                <button class="btn" type="submit"><i data-lucide="save"></i> Save rule</button>
            </form>
        @else
            <ul>
                @foreach ($policy['appliesTo'] as $label => $on)
                    <li>{{ $label }}: {{ $on ? 'yes' : 'no' }}</li>
                @endforeach
            </ul>
        @endcan
    </section>
@endsection
