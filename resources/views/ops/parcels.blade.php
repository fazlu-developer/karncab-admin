@extends('layouts.app')
@section('title', 'Parcel')
@section('heading', 'Parcel')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="package"></i> Parcel shipments</h1>
            <p class="muted">Create a desk parcel, assign a driver ID, and move status. Columns that are missing in the database are skipped automatically.</p>
        </div>
    </div>
    @can('parcels.manage')
        <section class="card">
            <h2>Create parcel</h2>
            <form method="POST" action="{{ route('ops.parcels.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Pickup</label><input name="pickup_text" required></div>
                    <div><label>Drop</label><input name="drop_text" required></div>
                    <div>
                        <label>Customer</label>
                        @include('partials.name-picker', ['name' => 'customer_id', 'options' => $pickerUsers ?? [], 'empty' => 'Search customer name or phone'])
                    </div>
                    <div><label>Quote (₹)</label><input name="quote_rupees" type="number" min="0" step="1"></div>
                    <button class="btn" type="submit">Create</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <form method="GET" class="filters" style="margin-bottom:12px">
            <div>
                <label>Search</label>
                <input name="q" value="{{ request('q') }}" placeholder="Parcel ID, ref, status, or place">
            </div>
            <div style="align-self:end"><button class="btn" type="submit">Search</button></div>
        </form>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th>ID</th><th>Ref</th><th>Status</th><th>Pickup</th><th>Drop</th><th>Quote</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->public_ref ?? '—' }}</td>
                    <td>{{ $row->status ?? '—' }}</td>
                    <td class="cell-wrap">{{ $row->pickup_text ?? '—' }}</td>
                    <td class="cell-wrap">{{ $row->drop_text ?? '—' }}</td>
                    <td>₹{{ number_format(((int) ($row->quote_paise ?? 0)) / 100, 0) }}</td>
                    <td>
                        @can('parcels.manage')
                            <form method="POST" action="{{ route('ops.parcels.update', $row->id) }}" class="filters" style="margin:0">
                                @csrf
                                @method('PUT')
                                <select name="status">
                                    @foreach (['CREATED','SEARCHING','ASSIGNED','PICKED','IN_TRANSIT','DELIVERED','CANCELLED'] as $st)
                                        <option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>
                                    @endforeach
                                </select>
                                @include('partials.name-picker', ['name' => 'driver_id', 'options' => $pickerDrivers ?? [], 'value' => $row->driver_id ?? '', 'empty' => 'Assign driver'])
                                <button class="btn ghost" type="submit">Update</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No parcel shipments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if (is_object($rows) && method_exists($rows, 'links'))
            <div style="margin-top:14px">{{ $rows->links('pagination.admin') }}</div>
        @endif
    </section>
@endsection
