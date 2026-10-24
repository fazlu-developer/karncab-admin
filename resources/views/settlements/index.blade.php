@extends('layouts.app')

@section('title', 'Commission settlement')
@section('heading', 'Commission settlement')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="banknote"></i> Fleet earning settlement</h1>
            <p class="muted">Settle fleet owner earnings for your territory. Super Admin and Admin see every fleet. A State Head sees that state. A District Head sees only their district. Driver withdraw requests stay with Super Admin / Admin.</p>
        </div>
        @canany(['commission.view', 'platform.admin'])
            <a class="btn ghost" href="{{ route('wallets.commission') }}"><i data-lucide="percent"></i> Commission rule</a>
        @endcanany
    </div>

    <section class="card">
        <h2>Weekly wallet credit · {{ $weeklyPay['period']['label'] }}</h2>
        <p class="muted">This credits the beneficiary wallet once for the week. Money already posted on the trip is not paid again. Commission settlement and driver withdraw are separate.</p>
        <form class="filters" method="GET">
            <div>
                <label>Any day in the week</label>
                <input type="date" name="week" value="{{ $weeklyPay['period']['key'] }}">
            </div>
            <div>
                <label>&nbsp;</label>
                <button class="btn" type="submit">Show week</button>
            </div>
            <div>
                <label>&nbsp;</label>
                <a class="btn ghost" href="{{ route('settlements.statement', ['week' => $weeklyPay['period']['key']]) }}">Download statement</a>
            </div>
        </form>
        @if ($weeklyPay['canPayDistrict'])
            <h3>District Heads</h3>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Head</th><th>Recorded</th><th>Already in wallet</th><th>To credit</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($weeklyPay['districts'] as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong><div class="muted">{{ $row['phone'] }}</div></td>
                            <td>₹{{ number_format($row['recordedRupees'], 2) }}</td>
                            <td>₹{{ number_format($row['alreadyRupees'], 2) }}</td>
                            <td>₹{{ number_format($row['dueRupees'], 2) }}</td>
                            <td>
                                @if ($row['settled'])
                                    <span class="muted">Settled</span>
                                @else
                                    <form method="POST" action="{{ route('settlements.weekly.district', $row['userId']) }}">
                                        @csrf
                                        <input type="hidden" name="week" value="{{ $weeklyPay['period']['key'] }}">
                                        <button class="btn" type="submit">Credit district wallet</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No district heads in this scope.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
        @if ($weeklyPay['canPayFleet'])
            <h3>Fleet owners</h3>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Fleet</th><th>Share</th><th>Recorded</th><th>Already in wallet</th><th>To credit</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($weeklyPay['fleets'] as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong><div class="muted">{{ $row['phone'] }}</div></td>
                            <td>{{ $row['share'] ?? '50% default' }}</td>
                            <td>₹{{ number_format($row['recordedRupees'], 2) }}</td>
                            <td>₹{{ number_format($row['alreadyRupees'], 2) }}</td>
                            <td>₹{{ number_format($row['dueRupees'], 2) }}</td>
                            <td>
                                @if ($row['settled'])
                                    <span class="muted">Settled</span>
                                @else
                                    <form method="POST" action="{{ route('settlements.weekly.fleet', $row['fleetId']) }}">
                                        @csrf
                                        <input type="hidden" name="week" value="{{ $weeklyPay['period']['key'] }}">
                                        <button class="btn" type="submit">Credit fleet wallet</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No fleets in this scope.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="kpis">
        <div class="kpi">
            <div class="label"><i data-lucide="percent"></i> Platform commission</div>
            <b>₹{{ number_format($commissionRupees, 0) }}</b>
        </div>
        <div class="kpi">
            <div class="label"><i data-lucide="warehouse"></i> Fleet share credited</div>
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
                                @if (auth()->user()?->can('payments.edit') || auth()->user()?->can('platform.admin') || auth()->user()?->isStateHead() || auth()->user()?->isDistrictHead())
                                    <form method="POST" action="{{ route('settlements.payout', $row['id']) }}" onsubmit="return confirm('Mark ₹{{ number_format($row['walletRupees'], 0) }} as paid to this fleet owner bank account?');">
                                        @csrf
                                        <input name="bank_reference" placeholder="Bank UTR" required style="max-width:140px">
                                        <button class="btn" type="submit">Settle bank payment</button>
                                    </form>
                                @endif
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
        <h2>Last week · {{ $weekLabel }}</h2>
        <p class="muted">Completed bookings for the closed week. Fleet commission is what the fleet owner collects. District commission is what the district head collects. Enter the bank UTR when you send the money.</p>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Fleet</th>
                        <th>District head</th>
                        <th>Bookings</th>
                        <th>Fare</th>
                        <th>Fleet commission</th>
                        <th>District commission</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($fleetWeeks as $row)
                    <tr>
                        <td><strong>{{ $row['name'] }}</strong><div class="muted">{{ $row['district'] }} · {{ $row['phone'] }}</div></td>
                        <td>{{ $row['districtHead'] }}</td>
                        <td>{{ $row['bookings'] }}</td>
                        <td>₹{{ number_format($row['grossRupees'], 0) }}</td>
                        <td>₹{{ number_format($row['fleetRupees'], 0) }}</td>
                        <td>₹{{ number_format($row['districtRupees'], 0) }}</td>
                        <td>
                            @if ($row['fleetRupees'] > 0)
                                @if (auth()->user()?->can('payments.edit') || auth()->user()?->can('platform.admin') || auth()->user()?->isStateHead() || auth()->user()?->isDistrictHead())
                                    <form method="POST" action="{{ route('settlements.payout', $row['id']) }}" onsubmit="return confirm('Record a bank transfer of ₹{{ number_format($row['fleetRupees'], 0) }} to this fleet owner?');">
                                        @csrf
                                        <input type="hidden" name="amount_rupees" value="{{ number_format($row['fleetRupees'], 2, '.', '') }}">
                                        <input type="hidden" name="week_label" value="{{ $weekLabel }}">
                                        <input name="bank_reference" placeholder="Bank UTR" required style="max-width:140px">
                                        <button class="btn" type="submit">Pay fleet</button>
                                    </form>
                                @endif
                            @else
                                <span class="muted">Nothing to pay</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No completed fleet bookings in {{ $weekLabel }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>District head settlement</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>District</th><th>District head</th><th>Bookings</th><th>Commission</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($districtWeeks as $row)
                    <tr>
                        <td>{{ $row['district'] }}</td>
                        <td>{{ $row['districtHead'] }}</td>
                        <td>{{ $row['bookings'] }}</td>
                        <td>₹{{ number_format($row['amountRupees'], 0) }}</td>
                        <td>
                            @if ($row['userId'] < 1)
                                <span class="muted">Assign a district head</span>
                            @elseif ($row['amountRupees'] > 0)
                                @if (auth()->user()?->can('payments.edit') || auth()->user()?->can('platform.admin') || auth()->user()?->isStateHead())
                                    <form method="POST" action="{{ route('settlements.district-payout', $row['userId']) }}">
                                        @csrf
                                        <input type="hidden" name="amount_rupees" value="{{ number_format($row['amountRupees'], 2, '.', '') }}">
                                        <input type="hidden" name="week_label" value="{{ $weekLabel }}">
                                        <input name="bank_reference" placeholder="Bank UTR" required style="max-width:140px">
                                        <button class="btn" type="submit">Pay district head</button>
                                    </form>
                                @endif
                            @else
                                <span class="muted">Nothing to pay</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No district commission for {{ $weekLabel }}.</td></tr>
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
