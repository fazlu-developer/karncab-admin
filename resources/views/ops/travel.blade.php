@extends('layouts.app')
@section('title', 'Travel Packages')
@section('heading', 'Travel Packages')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="map"></i> Travel packages</h1>
            <p class="muted">Publish destination packages for the website and travel bookings.</p>
        </div>
    </div>
    @can('travel.edit')
        <section class="card">
            <h2>Add package</h2>
            <form method="POST" action="{{ route('ops.travel.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required></div>
                    <div><label>Destination</label><input name="destination"></div>
                    <div><label>Price (₹)</label><input name="price_rupees" type="number" min="0" step="1"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="DRAFT">DRAFT</option>
                            <option value="PUBLISHED">PUBLISHED</option>
                            <option value="ARCHIVED">ARCHIVED</option>
                        </select>
                    </div>
                    <button class="btn" type="submit">Save package</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Packages</h2>
        <table class="data">
            <thead><tr><th>ID</th><th>Title</th><th>Destination</th><th>Price</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($packages as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->title ?? $row->name ?? '—' }}</td>
                    <td>{{ $row->destination ?? '—' }}</td>
                    <td>₹{{ number_format(((int) ($row->price_paise ?? 0)) / 100, 0) }}</td>
                    <td>{{ $row->status ?? '—' }}</td>
                    <td>
                        @can('travel.edit')
                            <form method="POST" action="{{ route('ops.travel.update', $row->id) }}" class="filters" style="margin:0">
                                @csrf
                                @method('PUT')
                                <input name="title" value="{{ $row->title ?? $row->name }}" required>
                                <input name="destination" value="{{ $row->destination ?? '' }}">
                                <input name="price_rupees" type="number" min="0" value="{{ (int) round(((int) ($row->price_paise ?? 0)) / 100) }}" style="max-width:100px">
                                <select name="status">
                                    @foreach (['DRAFT','PUBLISHED','ARCHIVED'] as $st)
                                        <option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>
                                    @endforeach
                                </select>
                                <button class="btn ghost" type="submit">Save</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No travel packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2>Recent travel bookings</h2>
        <table class="data">
            <thead><tr><th>ID</th><th>Ref</th><th>Status</th><th>Customer</th></tr></thead>
            <tbody>
            @forelse ($bookings as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->public_ref ?? '—' }}</td>
                    <td>{{ $row->status ?? '—' }}</td>
                    <td>{{ $row->customer_id ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No travel bookings yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
