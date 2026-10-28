@extends('layouts.app')
@section('title', 'Travel Bookings')
@section('heading', 'Travel Bookings')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="tickets"></i> Travel bookings</h1>
            <p class="muted">Pending requests, the {{ \App\Support\AdvancePayment::percent('TRAVEL') }}% payment, and driver assignment. Approve a request, wait for the advance, then assign a driver. Search drivers by name, phone, or vehicle.</p>
        </div>
    </div>
    <section class="card">
        <form method="GET" class="filters" style="margin-bottom:12px">
            <div>
                <label>Search</label>
                <input name="q" value="{{ $q ?? '' }}" placeholder="Ref, name, phone, or pickup">
            </div>
            <div>
                <label>Status</label>
                <select name="bucket" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved, unpaid', 'paid' => \App\Support\AdvancePayment::percent('TRAVEL').'% paid', 'assigned' => 'Assigned / on tour', 'completed' => 'Completed', 'rejected' => 'Rejected / cancelled'] as $key => $label)
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
                        <th>Package</th>
                        <th>Travellers</th>
                        <th>Pickup</th>
                        <th>Money</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($tours as $row)
                    <tr>
                        <td>{{ $row['publicRef'] }}<br><span class="muted">{{ $row['travelDate'] ?: '—' }}</span></td>
                        <td>{{ $row['package'] }}</td>
                        <td>
                            {{ $row['guests'] }}
                            @foreach ($row['passengers'] as $person)
                                <div class="muted">{{ $person['name'] ?? '' }} {{ $person['phone'] ?? '' }}</div>
                            @endforeach
                        </td>
                        <td>{{ $row['pickup'] ?: '—' }}<br><span class="muted">{{ $row['contactName'] }} {{ $row['contactPhone'] }}</span></td>
                        <td>
                            ₹{{ number_format($row['totalRupees'], 0) }} total<br>
                            <span class="muted">{{ \App\Support\AdvancePayment::percent('TRAVEL') }}% ₹{{ number_format($row['advanceRupees'], 0) }} · platform ₹{{ number_format($row['platformRupees'], 0) }} · driver ₹{{ number_format($row['driverRupees'], 0) }}</span>
                        </td>
                        <td>{{ $row['status'] }}@if ($row['driverName'])<div class="muted">{{ $row['driverName'] }}</div>@endif</td>
                        <td>
                            @can('travel.edit')
                                @if ($row['status'] === 'REQUESTED')
                                    <form method="POST" action="{{ route('ops.travel.approve', $row['id']) }}">@csrf<button class="btn" type="submit">Approve</button></form>
                                    <form method="POST" action="{{ route('ops.travel.reject', $row['id']) }}" style="margin-top:8px">
                                        @csrf
                                        <input name="reason" required placeholder="Reject reason" maxlength="240">
                                        <button class="btn ghost" type="submit">Reject</button>
                                    </form>
                                @elseif ($row['status'] === 'CONFIRMED')
                                    <form method="POST" action="{{ route('ops.travel.assign', $row['id']) }}">
                                        @csrf
                                        @include('partials.name-picker', ['name' => 'driver_id', 'options' => $drivers, 'required' => true, 'empty' => 'Search name, phone, or vehicle'])
                                        <button class="btn" type="submit">Assign</button>
                                    </form>
                                @elseif ($row['status'] === 'APPROVED_UNPAID')
                                    <span class="muted">Waiting for the customer to pay {{ \App\Support\AdvancePayment::percent('TRAVEL') }}%.</span>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No tour bookings in this list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
