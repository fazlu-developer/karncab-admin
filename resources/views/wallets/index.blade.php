@extends('layouts.app')

@section('title', 'Wallets')
@section('heading', 'Wallet ledger')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="wallet"></i> Wallets & ledger</h1>
            <p class="muted">Every credit or debit is an immutable ledger row: transaction id, wallet, booking, user, type, amount, commission, previous/new balance, payment reference, status, and time.</p>
        </div>
        <a class="btn ghost" href="{{ route('wallets.commission') }}"><i data-lucide="percent"></i> Commission rule</a>
    </div>

    @can('payments.edit')
        <section class="card">
            <h2>Post ledger row</h2>
            <form method="POST" action="{{ route('wallets.post') }}">
                @csrf
                <div class="filters">
                    <div>
                        <label>Account</label>
                        <select name="owner_type" required>
                            @foreach ($accounts as $account)
                                <option value="{{ $account }}">{{ $account }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>User ID</label><input name="owner_user_id" type="number" required></div>
                    <div>
                        <label>Credit / Debit</label>
                        <select name="direction">
                            <option value="CREDIT">CREDIT</option>
                            <option value="DEBIT">DEBIT</option>
                        </select>
                    </div>
                    <div><label>Amount (paise)</label><input name="amount_paise" type="number" min="1" required></div>
                    <div><label>Commission (paise)</label><input name="commission_paise" type="number" min="0" value="0"></div>
                    <div><label>Booking ID</label><input name="booking_id" type="number"></div>
                    <div><label>Type</label><input name="kind" value="adjustment"></div>
                    <div><label>Payment reference</label><input name="payment_ref"></div>
                    <div><label>Note</label><input name="note"></div>
                </div>
                <button class="btn" type="submit"><i data-lucide="plus"></i> Post</button>
            </form>
        </section>
        <section class="card">
            <h2>Settle completed booking</h2>
            <form method="POST" action="{{ route('wallets.settle') }}">
                @csrf
                <div class="filters">
                    <div><label>Booking ID</label><input name="booking_id" type="number" required></div>
                </div>
                <button class="btn" type="submit"><i data-lucide="scale"></i> Settle</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Wallets</h2>
        <form class="filters" method="GET">
            <div>
                <label>Account</label>
                <select name="owner_type">
                    <option value="">All</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account }}" @selected(($query['owner_type'] ?? '') === $account)>{{ $account }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn ghost" type="submit">Filter</button>
        </form>
        @if ($wallets === [])
            <p class="muted">No wallets in your territory yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Wallet ID</th>
                            <th>Account</th>
                            <th>User ID</th>
                            <th>Owner</th>
                            <th>Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($wallets as $row)
                            <tr>
                                <td>{{ $row['walletId'] }}</td>
                                <td>{{ $row['ownerType'] }}</td>
                                <td>{{ $row['userId'] }}</td>
                                <td>{{ $row['ownerName'] }}</td>
                                <td>₹{{ number_format($row['balanceRupees'], 2) }}</td>
                                <td><a class="icon-btn" href="{{ route('wallets.show', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <h2>Ledger</h2>
        <form class="filters" method="GET">
            <div><label>Search</label><input name="q" value="{{ $query['q'] ?? '' }}" placeholder="Txn / payment ref"></div>
            <div><label>Booking ID</label><input name="booking_id" value="{{ $query['booking_id'] ?? '' }}"></div>
            <div><label>User ID</label><input name="user_id" value="{{ $query['user_id'] ?? '' }}"></div>
            <div>
                <label>Direction</label>
                <select name="direction">
                    <option value="">All</option>
                    <option value="CREDIT" @selected(($query['direction'] ?? '') === 'CREDIT')>CREDIT</option>
                    <option value="DEBIT" @selected(($query['direction'] ?? '') === 'DEBIT')>DEBIT</option>
                </select>
            </div>
            <button class="btn ghost" type="submit">Search</button>
        </form>
        @include('wallets.partials.ledger-table', ['rows' => $ledger])
    </section>
@endsection
