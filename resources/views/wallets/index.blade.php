@extends('layouts.app')

@section('title', 'Wallets')
@section('heading', 'Wallet ledger')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="wallet"></i> Wallets & ledger</h1>
            <p class="muted">Every credit or debit is an immutable ledger row: transaction id, wallet, booking, user, type, amount, commission, previous/new balance, payment reference, status, and time.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if ($canViewWithdrawals ?? false)
                <a class="btn ghost" href="{{ route('withdrawals.index') }}"><i data-lucide="hand-coins"></i> Withdraw requests</a>
            @endif
            @can('platform.admin')
                <a class="btn ghost" href="{{ route('wallets.commission') }}"><i data-lucide="percent"></i> Commission rule</a>
            @endcan
        </div>
    </div>

    @if (!empty($platformEarnings))
        <section class="card">
            <h2>Admin / platform earnings</h2>
            <p class="muted">Commission credited to the platform wallet. This is admin earning, not a driver or fleet wallet.</p>
            <div class="kpis">
                <div class="kpi">
                    <div class="label">Platform wallet</div>
                    <b>₹{{ number_format($platformEarnings['balanceRupees'], 2) }}</b>
                </div>
                <div class="kpi">
                    <div class="label">Today commission</div>
                    <b>₹{{ number_format($platformEarnings['todayRupees'], 2) }}</b>
                </div>
                <div class="kpi">
                    <div class="label">This month</div>
                    <b>₹{{ number_format($platformEarnings['monthRupees'], 2) }}</b>
                </div>
            </div>
            @if (!empty($platformEarnings['walletId']))
                <p><a class="btn ghost" href="{{ route('wallets.show', $platformEarnings['walletId']) }}">Open platform wallet</a></p>
            @endif
        </section>
    @endif

    @if ($canCredit)
        <section class="card">
            <h2>Add wallet money</h2>
            <p class="muted">Search for a customer, driver, or partner, enter the amount in rupees, and add it to their wallet.</p>
            <form class="filters" method="GET">
                <div>
                    <label>Find user</label>
                    <input name="user_q" value="{{ $userQuery }}" placeholder="Name, phone, email, or user ID">
                </div>
                <button class="btn ghost" type="submit">Find</button>
            </form>
            @if ($creditUsers === [])
                <p class="muted">No users match that search.</p>
            @else
                <form method="POST" action="{{ route('wallets.credit') }}">
                    @csrf
                    <div class="filters">
                        <div>
                            <label>User</label>
                            <select name="user_id" required>
                                @foreach ($creditUsers as $person)
                                    <option value="{{ $person['id'] }}" @selected((string) old('user_id') === (string) $person['id'])>
                                        {{ $person['name'] }}
                                        @if ($person['phone'] !== '') · {{ $person['phone'] }} @endif
                                        · {{ $person['role'] }}
                                        · ₹{{ number_format($person['balanceRupees'], 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Amount (₹)</label>
                            <input name="amount_rupees" type="number" min="1" max="500000" step="0.01" value="{{ old('amount_rupees') }}" required>
                        </div>
                        <div>
                            <label>Note</label>
                            <input name="note" value="{{ old('note') }}" placeholder="Optional reason">
                        </div>
                    </div>
                    <button class="btn" type="submit"><i data-lucide="plus"></i> Custom transfer / add money</button>
                </form>
            @endif
        </section>
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
                    <div>
                        <label>User</label>
                        @include('partials.name-picker', ['name' => 'owner_user_id', 'options' => $pickerUsers ?? [], 'required' => true, 'empty' => 'Search user name or phone'])
                    </div>
                    <div>
                        <label>Credit / Debit</label>
                        <select name="direction">
                            <option value="CREDIT">CREDIT</option>
                            <option value="DEBIT">DEBIT</option>
                        </select>
                    </div>
                    <div><label>Amount (₹)</label><input name="amount_rupees" type="number" min="0.01" step="0.01" required></div>
                    <div><label>Commission (₹)</label><input name="commission_rupees" type="number" min="0" step="0.01" value="0"></div>
                    <div>
                        <label>Booking</label>
                        @include('partials.name-picker', ['name' => 'booking_id', 'options' => $pickerBookings ?? [], 'empty' => 'Optional booking'])
                    </div>
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
                    <div>
                        <label>Booking</label>
                        @include('partials.name-picker', ['name' => 'booking_id', 'options' => $pickerBookings ?? [], 'required' => true, 'empty' => 'Search booking or customer'])
                    </div>
                </div>
                <button class="btn" type="submit"><i data-lucide="scale"></i> Settle</button>
            </form>
        </section>
    @endif

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
                                <td>₹{{ number_format($row['balanceRupees'], 2) }}@if (($row['joiningCreditRupees'] ?? 0) > 0) · credit ₹{{ number_format($row['joiningCreditRupees'], 2) }}@endif @if (($row['frozenRupees'] ?? 0) > 0) · frozen ₹{{ number_format($row['frozenRupees'], 2) }}@endif</td>
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
            <div>
                <label>Booking</label>
                @include('partials.name-picker', ['name' => 'booking_id', 'options' => $pickerBookings ?? [], 'value' => $query['booking_id'] ?? '', 'empty' => 'All bookings'])
            </div>
            <div>
                <label>User</label>
                @include('partials.name-picker', ['name' => 'user_id', 'options' => $pickerUsers ?? $creditUsers ?? [], 'value' => $query['user_id'] ?? '', 'empty' => 'All users'])
            </div>
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
