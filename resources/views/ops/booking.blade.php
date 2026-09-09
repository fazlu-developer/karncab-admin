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
        <p class="muted">Requested → Assigned → Accepted → Arrived → OTP Verified → Started → In Progress → Completed</p>
    @endif
    <section class="card">
        <h2>Status log</h2>
        <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode($booking['statusLog'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
    <section class="card">
        <h2>Detail</h2>
        <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode(collect($booking)->except(['track','statusLog'])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
@endsection
