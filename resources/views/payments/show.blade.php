@extends('layouts.app')

@section('title', 'Payment')
@section('heading', 'Payment')

@section('content')
    <p><a href="{{ route('payments.index') }}"><i data-lucide="arrow-left"></i> All payments</a></p>
    <div class="hero">
        <div>
            <h1>{{ $payment['paymentReference'] }}</h1>
            <p class="muted">{{ strtoupper($payment['method']) }} · {{ $payment['kind'] }} · ₹{{ number_format($payment['amountRupees'], 2) }} · {{ $payment['status'] }}</p>
        </div>
    </div>
    <p class="muted">Lifecycle: {{ implode(' → ', $payment['lifecycle']) }}</p>
    @if ($payment['invoiceId'])
        <p><a href="{{ route('payments.invoice', $payment['invoiceId']) }}">Open invoice</a></p>
    @endif

    @can('payments.edit')
        <section class="card">
            @if ($payment['kind'] === 'payment' && $payment['method'] === 'cash' && in_array($payment['status'], ['created', 'pending', 'initiated'], true))
                <form method="POST" action="{{ route('payments.cash', $payment['id']) }}" style="display:inline">
                    @csrf
                    <button class="btn" type="submit">Confirm cash collected</button>
                </form>
            @endif
            @if ($payment['kind'] === 'payment' && in_array($payment['status'], ['created', 'pending', 'initiated'], true))
                <form method="POST" action="{{ route('payments.fail', $payment['id']) }}" style="display:inline">
                    @csrf
                    <button class="btn ghost" type="submit">Mark failed</button>
                </form>
            @endif
            @if ($payment['kind'] === 'payment' && in_array($payment['status'], ['success', 'captured', 'partially_refunded'], true))
                <form method="POST" action="{{ route('payments.refund', $payment['id']) }}">
                    @csrf
                    <div class="filters">
                        <div><label>Refund paise</label><input name="amount_paise" type="number" min="1" placeholder="full"></div>
                        <div><label>Reason</label><input name="reason"></div>
                    </div>
                    <button class="btn" type="submit">Request refund</button>
                </form>
            @endif
            @if (in_array($payment['kind'], ['refund', 'partial_refund'], true))
                @foreach (['approved', 'processing', 'completed'] as $step)
                    <form method="POST" action="{{ route('payments.refund-step', ['payment' => $payment['id'], 'step' => $step]) }}" style="display:inline">
                        @csrf
                        <button class="btn ghost" type="submit">{{ ucfirst($step) }}</button>
                    </form>
                @endforeach
            @endif
        </section>
    @endcan

    <section class="card">
        <h2>Events</h2>
        <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode($payment['events'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
    <section class="card">
        <h2>Refunds</h2>
        <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode($payment['refunds'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
@endsection
