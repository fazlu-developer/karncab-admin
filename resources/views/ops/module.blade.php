@extends('layouts.app')

@section('title', $def['label'] ?? 'Module')
@section('heading', $def['label'] ?? 'Module')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="{{ $def['icon'] ?? 'folder' }}"></i> {{ $def['label'] }}</h1>
            <p class="muted">Laravel reads the platform database. Secrets are never shown in this table. Territory filters cannot be widened via the URL.</p>
        </div>
        @can('reports.export')
            <a class="btn ghost" href="{{ route('ops.export', ['module' => $def['key'], ...request()->query()]) }}"><i data-lucide="download"></i> Export CSV</a>
        @endcan
    </div>
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif

    @if (($def['key'] ?? '') === 'bookings')
        <form class="card" method="GET">
            <div class="filters">
                <div><label>Booking ID / ref</label><input name="publicRef" value="{{ $query['publicRef'] ?? '' }}"></div>
                <div><label>Customer</label><input name="customer" value="{{ $query['customer'] ?? '' }}"></div>
                <div><label>Driver</label><input name="driver" value="{{ $query['driver'] ?? '' }}"></div>
                <div><label>Vehicle</label><input name="vehicle" value="{{ $query['vehicle'] ?? '' }}"></div>
                <div><label>Booking type</label><input name="product" value="{{ $query['product'] ?? '' }}" placeholder="ONE_WAY"></div>
                <div><label>Status</label><input name="status" value="{{ $query['status'] ?? '' }}"></div>
                <div><label>Payment status</label><input name="paymentStatus" value="{{ $query['paymentStatus'] ?? '' }}"></div>
                <div><label>State ID</label><input name="stateId" value="{{ $query['stateId'] ?? '' }}"></div>
                <div><label>District ID</label><input name="districtId" value="{{ $query['districtId'] ?? '' }}"></div>
                <div><label>Fleet ID</label><input name="fleetId" value="{{ $query['fleetId'] ?? '' }}"></div>
                <div><label>Franchise ID</label><input name="franchiseId" value="{{ $query['franchiseId'] ?? '' }}"></div>
                <div><label>From</label><input type="date" name="from" value="{{ $query['from'] ?? '' }}"></div>
                <div><label>To</label><input type="date" name="to" value="{{ $query['to'] ?? '' }}"></div>
            </div>
            <button class="btn" type="submit"><i data-lucide="filter"></i> Filter</button>
        </form>
    @endif

    @if (($def['key'] ?? '') === 'notifications' && auth()->user()->can('platform.admin'))
        <section class="card">
            <h2><i data-lucide="megaphone"></i> Broadcast</h2>
            <form method="POST" action="{{ route('ops.notify') }}">
                @csrf
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Body</label><textarea name="body" rows="3" required></textarea></div>
                <div class="field">
                    <label>Audience</label>
                    <select name="audience">
                        <option value="customer">Customers</option>
                        <option value="driver">Drivers</option>
                        <option value="ops">Operations</option>
                    </select>
                </div>
                <button class="btn" type="submit"><i data-lucide="send"></i> Send</button>
            </form>
        </section>
    @endif

    <section class="card">
        @if ($rows === [])
            <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            @foreach (array_keys($rows[0]) as $col)
                                @if (!is_array($rows[0][$col]))
                                    <th>{{ $col }}</th>
                                @endif
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $col => $value)
                                    @if (!is_array($value))
                                        <td>{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</td>
                                    @endif
                                @endforeach
                                <td>
                                    @if (($def['key'] ?? '') === 'bookings' && !empty($row['id']))
                                        <a class="icon-btn" href="{{ route('ops.bookings.show', $row['id']) }}" title="Lifecycle"><i data-lucide="eye"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
