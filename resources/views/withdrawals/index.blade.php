@extends('layouts.app')

@section('title', 'Withdraw requests')
@section('heading', 'Withdraw requests')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="hand-coins"></i> Withdraw requests</h1>
            <p class="muted">Drivers and partners request cash from their wallet. Approve, then settle after the bank or UPI transfer. Access is admin-only unless Super Admin grants <code>payout.view</code> / <code>payout.approve</code> on Roles.</p>
        </div>
        <a class="btn ghost" href="{{ route('wallets.index') }}"><i data-lucide="wallet"></i> Wallets</a>
    </div>

    <form class="filters" method="GET">
        <div>
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                @foreach (['requested' => 'Requested', 'approved' => 'Approved', 'settled' => 'Settled', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
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
                                <td>{{ $row['ownerName'] }} · {{ $row['userId'] }}@if ($row['ownerPhone']) · {{ $row['ownerPhone'] }}@endif</td>
                                <td>₹{{ number_format($row['amountRupees'], 2) }}</td>
                                <td><span class="pill">{{ $row['status'] }}</span></td>
                                <td>{{ $row['transferRef'] ?: '—' }}</td>
                                <td>{{ $row['createdAt'] }}</td>
                                @if ($canSettle)
                                    <td>
                                        @if (! in_array($row['status'], ['settled', 'rejected'], true))
                                            <form method="POST" action="{{ route('withdrawals.decide', $row['id']) }}" class="filters" style="margin:0">
                                                @csrf
                                                <input name="transfer_ref" placeholder="Bank/UPI ref" value="{{ $row['transferRef'] }}">
                                                @if ($row['status'] === 'requested')
                                                    <button class="btn ghost" name="decision" value="approve">Approve</button>
                                                @endif
                                                <button class="btn" name="decision" value="settle">Settle</button>
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
