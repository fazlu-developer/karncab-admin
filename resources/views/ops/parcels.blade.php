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
                    <div><label>Customer user ID</label><input name="customer_id" type="number"></div>
                    <div><label>Quote (₹)</label><input name="quote_rupees" type="number" min="0" step="1"></div>
                    <button class="btn" type="submit">Create</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <table class="data">
            <thead><tr><th>ID</th><th>Ref</th><th>Status</th><th>Pickup</th><th>Drop</th><th>Quote</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->public_ref ?? '—' }}</td>
                    <td>{{ $row->status ?? '—' }}</td>
                    <td>{{ $row->pickup_text ?? '—' }}</td>
                    <td>{{ $row->drop_text ?? '—' }}</td>
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
                                <input name="driver_id" type="number" value="{{ $row->driver_id ?? '' }}" placeholder="Driver ID" style="max-width:110px">
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
    </section>
@endsection
