@extends('layouts.app')

@section('title', 'Withdraw requests')
@section('heading', 'Withdraw requests')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="hand-coins"></i> Withdraw requests</h1>
            <p class="muted">Drivers request cash from their wallet. The amount stays frozen until you approve and pay, or reject it with a reason. Super Admin and Admin see every request. A State Head or District Head can approve only after <code>payout.approve</code> is granted, and only for drivers in their territory.</p>
        </div>
        <a class="btn ghost" href="{{ route('wallets.index') }}"><i data-lucide="wallet"></i> Wallets</a>
    </div>

    <form class="filters" method="GET">
        <div>
            <label>Driver name or phone</label>
            <input name="q" value="{{ $q }}" placeholder="Name or phone">
        </div>
        <div>
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                @foreach (['requested' => 'Requested', 'approved' => 'Approved', 'settled' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn" type="submit">Search</button>
        </div>
    </form>

    <section class="card">
        <h2>{{ $status === 'all' ? 'All requests' : ucfirst($status) }}</h2>
        @if ($rows === [])
            <p class="muted">No withdrawal requests in this filter.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Pay by</th>
                            <th>Status</th>
                            <th>Transfer</th>
                            <th>Requested</th>
                            @if ($canSettle)
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['publicRef'] }}</td>
                                <td>{{ $row['ownerName'] }}@if ($row['ownerPhone']) · {{ $row['ownerPhone'] }}@endif</td>
                                <td>₹{{ number_format($row['amountRupees'], 2) }}</td>
                                <td>{{ $row['payoutMethod'] ?: '—' }}@if ($row['destination']) · {{ $row['destination'] }}@endif</td>
                                <td><span class="pill">{{ $row['status'] }}</span></td>
                                <td>{{ $row['transferRef'] ?: '—' }}</td>
                                <td>{{ $row['createdAt'] }}</td>
                                @if ($canSettle)
                                    <td>
                                        @if (! in_array($row['status'], ['settled', 'rejected'], true))
                                            <form method="POST" action="{{ route('withdrawals.decide', $row['id']) }}" class="filters" style="margin:0">
                                                @csrf
                                                <input name="transfer_ref" placeholder="Bank/UPI ref" value="{{ $row['transferRef'] }}">
                                                <input name="note" placeholder="Reason if rejecting" value="{{ $row['note'] }}">
                                                @if ($row['status'] === 'requested')
                                                    <button class="btn" name="decision" value="approve">Approve &amp; pay</button>
                                                @endif
                                                @if ($row['status'] === 'approved')
                                                    <button class="btn" name="decision" value="settle">Mark paid</button>
                                                @endif
                                                <button class="btn ghost" name="decision" value="reject">Reject</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
