@extends('layouts.app')

@section('title', 'Payments')
@section('heading', 'Payments')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="credit-card"></i> Payments, refunds & invoices</h1>
            <p class="muted">{{ $catalog['note'] }} Lifecycle: {{ implode(' → ', $catalog['paymentLifecycle']) }}. Refunds: {{ implode(' → ', $catalog['refundLifecycle']) }}.</p>
        </div>
    </div>

    @can('payments.edit')
        <section class="card">
            <h2>Start payment</h2>
            <form method="POST" action="{{ route('payments.store') }}">
                @csrf
                <div class="filters">
                    <div>
                        <label>Method</label>
                        <select name="method" required>
                            @foreach ($catalog['methods'] as $item)
                                <option value="{{ $item['method'] }}">{{ strtoupper($item['method']) }} ({{ $item['capture'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Booking ID</label><input name="booking_id" type="number" required></div>
                    <div><label>Amount (paise)</label><input name="amount_paise" type="number" min="1" placeholder="from quote"></div>
                    <div>
                        <label>Intent</label>
                        <select name="intent">
                            <option value="capture">capture</option>
                            <option value="advance">advance</option>
                            <option value="partial">partial</option>
                        </select>
                    </div>
                </div>
                <button class="btn" type="submit"><i data-lucide="play"></i> Create intent</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Payments</h2>
        <form class="filters" method="GET">
            <div><label>Search</label><input name="q" value="{{ $query['q'] ?? '' }}" placeholder="Payment reference"></div>
            <div><label>Status</label><input name="status" value="{{ $query['status'] ?? '' }}"></div>
            <div><label>Method</label><input name="method" value="{{ $query['method'] ?? '' }}"></div>
            <button class="btn ghost" type="submit">Filter</button>
        </form>
        @if ($payments === [])
            <p class="muted">No payments in scope.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Method</th>
                            <th>Kind</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Verified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $row)
                            <tr>
                                <td>{{ $row['paymentReference'] }}</td>
                                <td>{{ $row['method'] }}</td>
                                <td>{{ $row['kind'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>₹{{ number_format($row['amountRupees'], 2) }}</td>
                                <td>{{ $row['verifiedSource'] ?? '—' }}</td>
                                <td><a class="icon-btn" href="{{ route('payments.show', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <h2>Invoices</h2>
        @if ($invoices === [])
            <p class="muted">No invoices yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Kind</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Refunded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $row)
                            <tr>
                                <td>{{ $row['invoiceNumber'] }}</td>
                                <td>{{ $row['kind'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>₹{{ number_format($row['totalRupees'], 2) }}</td>
                                <td>₹{{ number_format(($row['paidPaise'] ?? 0) / 100, 2) }}</td>
                                <td>₹{{ number_format(($row['refundedPaise'] ?? 0) / 100, 2) }}</td>
                                <td><a class="icon-btn" href="{{ route('payments.invoice', $row['id']) }}"><i data-lucide="file-text"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
