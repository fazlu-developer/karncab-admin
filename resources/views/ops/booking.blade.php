@extends('layouts.app')

@section('title', 'Booking')
@section('heading', 'Booking')

@section('content')
    <p><a href="{{ route('ops.module', 'bookings') }}"><i data-lucide="arrow-left"></i> Back to bookings</a></p>
    <h1>{{ $booking['publicRef'] ?? 'Booking' }}</h1>
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif
    <p>{{ $booking['product'] ?? '' }} · {{ $booking['status'] ?? '' }}</p>
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
                    @foreach (collect($booking)->except(['track', 'statusLog', 'quoteSnapshot', 'navigation'])->all() as $key => $value)
                        @if (!is_array($value))
                            <tr>
                                <th style="width:220px;text-align:left">{{ ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('_', ' ', $key))) }}</th>
                                <td>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === null || $value === '' ? '—' : $value) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Status log</h2>
        @php $log = $booking['statusLog'] ?? []; @endphp
        @if (empty($log) || !is_array($log))
            <p class="muted">No status history yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            @foreach (array_keys(is_array($log[0] ?? null) ? $log[0] : ['status' => '', 'at' => '']) as $col)
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
@endsection
