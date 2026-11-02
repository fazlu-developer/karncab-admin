@extends('layouts.app')

@section('title', 'Booking')
@section('heading', 'Booking')

@section('content')
    <p><a href="{{ route('ops.module', 'bookings') }}"><i data-lucide="arrow-left"></i> Back to bookings</a></p>
    <h1>Booking {{ $booking['bookingId'] ?? $booking['id'] ?? '' }} · {{ $booking['publicRef'] ?? 'Booking' }}</h1>
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif
    <p>{{ str_replace('_', ' ', (string) ($booking['product'] ?? '')) }} · {{ str_replace('_', ' ', (string) ($booking['status'] ?? '')) }}</p>
    @can('bookings.manage')
        @if (!empty($booking['id']))
            <form method="POST" action="{{ route('ops.bookings.destroy', $booking['id']) }}" onsubmit="return confirm('Delete this booking permanently? It will disappear from the customer app and the driver app. The audit log keeps a copy.');">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit" style="background:#7f1d1d">Delete booking</button>
            </form>
        @endif
    @endcan
    @if (empty($booking['driverId']) && in_array($booking['status'] ?? '', ['CONFIRMED', 'PENDING', 'SEARCHING', 'REQUESTED', 'DRIVER_SEARCHING'], true))
        @can('bookings.manage')
            <section class="card">
                <h2>Assign driver</h2>
                <form method="POST" action="{{ route('ops.assign-drivers.store', $booking['id']) }}">
                    @csrf
                    <div class="filters">
                        <div>
                            <label>Driver (matching {{ $booking['category'] ?? 'cab' }})</label>
                            <select name="driver_id" required>
                                <option value="">Select driver</option>
                                @foreach ($drivers ?? [] as $driver)
                                    @php
                                        $match = empty($booking['category']) || empty($driver['category']) || strcasecmp((string) $driver['category'], (string) $booking['category']) === 0;
                                    @endphp
                                    @if ($match && empty($driver['busy']) && !empty($driver['vehicleId']))
                                        <option value="{{ $driver['id'] }}">{{ $driver['name'] }} · {{ $driver['registrationNo'] }} ({{ $driver['category'] }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <button class="btn" type="submit">Assign to booking</button>
                    </div>
                </form>
            </section>
        @endcan
    @endif
    @can('bookings.manage')
        @if (!empty($booking['id']) && !empty($booking['driverId']))
            <section class="card">
                <h2>Driver settlement</h2>
                <p class="muted">Customer money stays in the company account. This form only records a payout you have already sent. It does not transfer money.</p>
                @foreach ($settlements ?? [] as $settlement)
                    <p>Payable ₹{{ number_format($settlement->payable_paise / 100, 2) }} · Paid ₹{{ number_format($settlement->paid_paise / 100, 2) }} · {{ str_replace('_', ' ', $settlement->status) }}</p>
                @endforeach
                <form method="POST" action="{{ route('ops.bookings.settle', $booking['id']) }}">
                    @csrf
                    <div class="filters">
                        <div>
                            <label>Amount (₹)</label>
                            <input name="amount_rupees" type="number" min="1" step="1" required>
                        </div>
                        <div>
                            <label>Method</label>
                            <select name="method">
                                <option value="UPI">UPI</option>
                                <option value="BANK">Bank</option>
                                <option value="CASH">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label>UTR / reference</label>
                            <input name="reference" required maxlength="80">
                        </div>
                        <button class="btn" type="submit">Record settlement</button>
                    </div>
                </form>
            </section>
        @endif
    @endcan
    @php $track = $booking['track'] ?? []; @endphp
    @if (!empty($track['cancelled']))
        <div class="steps">
            <span class="step done">Requested</span>
            <span class="step cancel">Cancelled</span>
        </div>
    @elseif (!empty($track['steps']))
        <div class="steps">
            @foreach ($track['steps'] as $step)
                <span class="step {{ !empty($step['done']) ? 'done' : '' }} {{ !empty($step['active']) ? 'active' : '' }}">{{ $step['label'] }}</span>
            @endforeach
        </div>
    @endif

    <section class="card">
        <h2>Booking</h2>
        <div class="table-wrap">
            <table class="data">
                <tbody>
                    @foreach (collect($booking)->except(['id', 'driverId', 'customerId', 'track', 'statusLog', 'paymentHistory', 'walletHistory', 'driverHistory', 'quoteSnapshot', 'navigation'])->all() as $key => $value)
                        @if (!is_array($value))
                            <tr>
                                <th style="width:220px;text-align:left">{{ ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('_', ' ', $key))) }}</th>
                                <td class="cell-wrap">
                                    @if ($key === 'driver' && !empty($booking['driverId']))
                                        <a href="{{ route('drivers.show', $booking['driverId']) }}">{{ $value }}</a>
                                    @elseif ($key === 'customer' && !empty($booking['customerId']))
                                        <a href="{{ route('users.show', $booking['customerId']) }}">{{ $value }}</a>
                                    @else
                                        {{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === null || $value === '' ? '—' : $value) }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach ([
        'statusLog' => 'Maintain logs',
        'paymentHistory' => 'Payment history',
        'walletHistory' => 'Wallet / commission log',
        'driverHistory' => 'Driver trip history',
    ] as $key => $title)
        <section class="card">
            <h2>{{ $title }}</h2>
            @php $log = $booking[$key] ?? []; @endphp
            @if (empty($log) || !is_array($log))
                <p class="muted">Nothing recorded yet.</p>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                @foreach (array_keys(is_array($log[0] ?? null) ? $log[0] : ['Detail' => '']) as $col)
                                    <th>{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($log as $row)
                                <tr>
                                    @if (is_array($row))
                                        @foreach ($row as $value)
                                            <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                                        @endforeach
                                    @else
                                        <td>{{ $row }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
@endsection
