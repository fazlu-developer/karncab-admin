@extends('layouts.app')
@section('title', 'Corporate Plans')
@section('heading', 'Corporate Plans')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="briefcase"></i> Corporate travel plans</h1>
            <p class="muted">Publish On-Demand, Monthly, Employee Transport and custom plans for the customer app Corporate Travel flow. Trip fares still come from live fare rules unless you set a fixed plan price. After booking, assign a driver from Assign Drivers.</p>
        </div>
    </div>
    @can('corporate.edit')
        <section class="card">
            <h2>Add plan</h2>
            <form method="POST" action="{{ route('ops.corporate-plans.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required placeholder="On-Demand Booking"></div>
                    <div><label>Key</label><input name="plan_key" placeholder="ON_DEMAND"></div>
                    <div><label>Subtitle</label><input name="subtitle" placeholder="Instant booking for business travel"></div>
                    <div>
                        <label>Pricing</label>
                        <select name="pricing_mode">
                            <option value="VEHICLE">Live vehicle fare</option>
                            <option value="FIXED">Fixed plan price (₹)</option>
                            <option value="QUOTE">Custom quote (no sticker price)</option>
                        </select>
                    </div>
                    <div><label>Fixed price (₹)</label><input name="price_rupees" type="number" min="0" step="1" value="0"></div>
                    <div><label>Price label</label><input name="price_label" placeholder="Custom Get Quote"></div>
                    <div><label>GST %</label><input name="gst_percent" type="number" min="0" step="0.01" value="5"></div>
                    <div><label>Sort</label><input name="sort_order" type="number" min="0" value="1"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="PUBLISHED">PUBLISHED</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="ARCHIVED">ARCHIVED</option>
                        </select>
                    </div>
                    <div style="grid-column:1/-1"><label>Highlights (one per line)</label><textarea name="highlights" rows="3" placeholder="Pay per trip"></textarea></div>
                </div>
                <button class="btn" type="submit" style="margin-top:12px">Save plan</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Plans</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Plan</th><th>Pricing</th><th>GST</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($plans as $row)
                    @php
                        $highlights = is_string($row->highlights ?? null) ? json_decode($row->highlights, true) : [];
                        $highlightText = is_array($highlights) ? implode("\n", $highlights) : ($row->highlights ?? '');
                    @endphp
                    <tr>
                        <td>{{ $row->title }}<br><span class="muted">{{ $row->plan_key }} · {{ $row->subtitle }}</span></td>
                        <td>
                            {{ $row->pricing_mode }}
                            @if ((int) ($row->price_paise ?? 0) > 0)
                                · ₹{{ number_format(((int) $row->price_paise) / 100, 0) }}
                            @elseif (!empty($row->price_label))
                                · {{ $row->price_label }}
                            @else
                                · live fare
                            @endif
                        </td>
                        <td>{{ $row->gst_percent }}%</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            @can('corporate.edit')
                                <form method="POST" action="{{ route('ops.corporate-plans.update', $row->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="filters">
                                        <input name="title" value="{{ $row->title }}" required>
                                        <input name="plan_key" value="{{ $row->plan_key }}">
                                        <input name="subtitle" value="{{ $row->subtitle }}">
                                        <select name="pricing_mode">
                                            @foreach (['VEHICLE' => 'Live vehicle fare', 'FIXED' => 'Fixed price', 'QUOTE' => 'Custom quote'] as $key => $label)
                                                <option value="{{ $key }}" @selected(($row->pricing_mode ?? '') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="price_rupees" type="number" min="0" value="{{ (int) round(((int) ($row->price_paise ?? 0)) / 100) }}">
                                        <input name="price_label" value="{{ $row->price_label }}" placeholder="Label">
                                        <input name="gst_percent" type="number" step="0.01" value="{{ $row->gst_percent ?? 5 }}">
                                        <input name="sort_order" type="number" value="{{ $row->sort_order ?? 0 }}">
                                        <select name="status">
                                            @foreach (['DRAFT','PUBLISHED','ARCHIVED'] as $st)
                                                <option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="highlights" rows="2">{{ $highlightText }}</textarea>
                                        <button class="btn" type="submit">Update</button>
                                    </div>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No corporate plans yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>Recent corporate bookings</h2>
        <p class="muted">Paid corporate trips also appear under <a href="{{ route('ops.assign-drivers') }}?product=CORPORATE">Assign Drivers → Corporate Travel</a>.</p>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Ref</th><th>Plan</th><th>Route</th><th>When</th><th>Fare</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($bookings as $row)
                    <tr>
                        <td>{{ $row->public_ref }}</td>
                        <td>{{ $row->plan_key ?? '—' }}</td>
                        <td>{{ $row->pickup_text }} → {{ $row->drop_text }}</td>
                        <td>{{ $row->travel_date }} {{ $row->pickup_time }}</td>
                        <td>₹{{ number_format(((int) ($row->quote_paise ?? 0)) / 100, 0) }}</td>
                        <td>{{ $row->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No corporate travel bookings yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
