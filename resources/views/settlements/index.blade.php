@extends('layouts.app')

@section('title', 'Commission settlement')
@section('heading', 'Commission settlement')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="banknote"></i> Platform commission & fleet 5%</h1>
            <p class="muted">Completed trips take platform commission. Fleet owners get their configured 5% of fare as a wallet credit in the fleet owner app. Settle here when you pay them out.</p>
        </div>
        <a class="btn ghost" href="{{ route('wallets.commission') }}"><i data-lucide="percent"></i> Commission rule</a>
    </div>

    <div class="kpis">
        <div class="kpi">
            <div class="label"><i data-lucide="percent"></i> Platform commission</div>
            <b>₹{{ number_format($commissionRupees, 0) }}</b>
        </div>
        <div class="kpi">
            <div class="label"><i data-lucide="warehouse"></i> Fleet 5% credited</div>
            <b>₹{{ number_format($fleetShareRupees, 0) }}</b>
        </div>
        <div class="kpi">
            <div class="label"><i data-lucide="landmark"></i> Platform keep</div>
            <b>₹{{ number_format($platformKeepRupees, 0) }}</b>
        </div>
        <div class="kpi">
            <div class="label"><i data-lucide="circle-check"></i> Fleet paid out</div>
            <b>₹{{ number_format($fleetPaidRupees, 0) }}</b>
        </div>
    </div>

    <section class="card">
        <h2>Fleet owners</h2>
        <p class="muted">Credit appears in the fleet owner app wallet as soon as a trip completes. Settle payout when you transfer the money.</p>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Fleet</th>
                        <th>Share</th>
                        <th>Credited</th>
                        <th>Paid out</th>
                        <th>Wallet</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($owners as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['name'] }}</strong>
                            <div class="muted">{{ $row['phone'] }}</div>
                        </td>
                        <td>{{ number_format($row['percent'], 2) }}%</td>
                        <td>₹{{ number_format($row['creditedRupees'], 0) }}</td>
                        <td>₹{{ number_format($row['paidRupees'], 0) }}</td>
                        <td>₹{{ number_format($row['walletRupees'], 0) }}</td>
                        <td>
                            @if ($row['walletRupees'] > 0)
                                @canany(['payments.edit', 'platform.admin'])
                                    <form method="POST" action="{{ route('settlements.payout', $row['id']) }}" onsubmit="return confirm('Mark ₹{{ number_format($row['walletRupees'], 0) }} as paid to this fleet owner?');">
                                        @csrf
                                        <button class="btn" type="submit">Settle payout</button>
                                    </form>
                                @endcanany
                            @else
                                <span class="muted">Settled</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No fleet commission credits yet. They appear after a fleet driver completes a trip.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Recent ledger</h2>
        <table class="data">
            <thead>
                <tr><th>When</th><th>Who</th><th>Type</th><th>Amount</th><th>Note</th></tr>
            </thead>
            <tbody>
            @forelse ($recent as $row)
                <tr>
                    <td class="muted">{{ $row->created_at }}</td>
                    <td>{{ $row->owner_name ?: '—' }}<div class="muted">{{ $row->owner_phone }}</div></td>
                    <td>{{ str_replace('_', ' ', $row->kind) }} · {{ $row->direction }}</td>
                    <td>₹{{ number_format(((int) $row->amount_paise) / 100, 0) }}</td>
                    <td class="muted">{{ $row->note }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No commission ledger rows yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
