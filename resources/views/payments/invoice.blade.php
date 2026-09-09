@extends('layouts.app')

@section('title', 'Invoice')
@section('heading', 'Invoice')

@section('content')
    <p><a href="{{ route('payments.index') }}"><i data-lucide="arrow-left"></i> Payments</a></p>
    <div class="hero">
        <div>
            <h1>{{ $invoice['invoiceNumber'] }}</h1>
            <p class="muted">{{ $invoice['kind'] }} · {{ $invoice['status'] }} · {{ $invoice['currency'] }}</p>
        </div>
    </div>
    <section class="card">
        <p>Subtotal ₹{{ number_format(($invoice['subtotalPaise'] ?? 0) / 100, 2) }}</p>
        <p>Tax ₹{{ number_format(($invoice['taxPaise'] ?? 0) / 100, 2) }}</p>
        <p><strong>Total ₹{{ number_format($invoice['totalRupees'], 2) }}</strong></p>
        <p>Paid ₹{{ number_format(($invoice['paidPaise'] ?? 0) / 100, 2) }} · Refunded ₹{{ number_format(($invoice['refundedPaise'] ?? 0) / 100, 2) }}</p>
    </section>
    <section class="card">
        <h2>Linked payments</h2>
        <pre class="muted" style="white-space:pre-wrap;font-size:12px">{{ json_encode($invoice['payments'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
@endsection
