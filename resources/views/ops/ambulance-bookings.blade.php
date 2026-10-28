@extends('layouts.app')
@section('title', 'Ambulance')
@section('heading', 'Ambulance')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="heart-pulse"></i> Ambulance emergencies</h1>
            <p class="muted">Free emergency trips. The customer waits here until you assign an ambulance driver. There is no nearby offer and no fare.</p>
        </div>
    </div>
    @if (session('status'))
        <p class="muted">{{ session('status') }}</p>
    @endif
    <section class="card">
        <form method="GET" class="filters" style="margin-bottom:12px">
            <div>
                <label>Search</label>
                <input name="q" value="{{ $q ?? '' }}" placeholder="Ref, name, phone, pickup, or hospital">
            </div>
            <div>
                <label>Status</label>
                <select name="bucket" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach (['waiting' => 'Waiting for admin', 'assigned' => 'Assigned / on the way', 'done' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" @selected(($bucket ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="align-self:end"><button class="btn" type="submit">Search</button></div>
        </form>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Patient</th>
                        <th>Pickup</th>
                        <th>Hospital</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['publicRef'] }}</td>
                        <td>
                            {{ $row['contactName'] ?: 'Patient' }}<br>
                            <span class="muted">{{ $row['contactPhone'] }}</span>
                            @if ($row['photo'])
                                <div><a href="{{ $row['photo'] }}" target="_blank" rel="noopener">Patient photo</a></div>
                            @endif
                        </td>
                        <td>{{ $row['pickup'] ?: '—' }}</td>
                        <td>{{ $row['drop'] ?: '—' }}</td>
                        <td>
                            {{ $row['status'] }}
                            @if ($row['driverName'])<div class="muted">{{ $row['driverName'] }}</div>@endif
                            <div class="muted">Fare ₹0</div>
                        </td>
                        <td>
                            @if ($row['canAssign'])
                                @if (count($drivers) === 0)
                                    <span class="muted">Add a vehicle with category AMBULANCE before assigning.</span>
                                @else
                                    <form method="POST" action="{{ route('ops.ambulance.assign', $row['id']) }}">
                                        @csrf
                                        @include('partials.name-picker', ['name' => 'driver_id', 'options' => $drivers, 'required' => true, 'empty' => 'Search ambulance driver'])
                                        <button class="btn" type="submit">Assign</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No ambulance bookings in this list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
