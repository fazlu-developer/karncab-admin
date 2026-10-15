@extends('layouts.app')

@section('title', $row['tradeName'])
@section('heading', 'Franchise')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $row['tradeName'] }}</h1>
            <p class="muted">{{ $row['kind'] }} · {{ $row['districtName'] ?: ('district #'.$row['districtId']) }} · {{ $row['ownerName'] ?: ('owner #'.$row['ownerUserId']) }} {{ $row['ownerEmail'] ? '· '.$row['ownerEmail'] : '' }}</p>
        </div>
        <a class="btn ghost" href="{{ route('franchises.index') }}"><i data-lucide="list"></i> List</a>
    </div>
    <section class="card">
        <p>
            <span class="pill {{ $row['status'] === 'ACTIVE' ? 'ok' : 'warn' }}">{{ $row['status'] }}</span>
            <span class="pill muted">KYC {{ $row['kycStatus'] }}</span>
            <span class="pill muted">Agreement {{ $row['agreementStatus'] }}</span>
            <span class="pill muted">Commission {{ $row['commissionPercent'] }}%</span>
            @if ($row['exclusiveSeat'])
                <span class="pill ok">Exclusive seat</span>
            @endif
        </p>
        <p class="muted">active_district_key: {{ $row['activeDistrictKey'] ?: 'null (not occupying the district)' }}</p>
        @if ($row['wallet'])
            <p>Wallet {{ $row['wallet']['ownerType'] }} · ₹{{ number_format($row['wallet']['balanceRupees'], 2) }}</p>
        @endif
        <p class="muted">Performance — vehicles {{ $row['performance']['vehicles'] }}, completed trips {{ $row['performance']['completedTrips'] }}, gross ₹{{ number_format($row['performance']['grossRupees'], 2) }}</p>
    </section>

    @canany(['franchise.manage', 'kyc.approve', 'platform.admin'])
        @if ($row['status'] !== 'ACTIVE' && $row['status'] !== 'TERMINATED')
            <section class="card">
                <h3 style="margin-top:0">Verify application</h3>
                <p class="muted">Documents uploaded for this District Head are listed under KYC. Verify marks KYC verified and sets the franchise ACTIVE so they can sign in.</p>
                <form method="POST" action="{{ route('franchises.verify', $row['id']) }}">
                    @csrf
                    <button class="btn" type="submit">Verify &amp; activate</button>
                </form>
            </section>
        @endif
        <section class="card">
            <h3 style="margin-top:0">Lifecycle</h3>
            <p class="muted">ACTIVE claims the unique district seat. Suspend, expire, terminate, or reassign releases or moves it in the database.</p>
            @foreach ($transitions[$row['status']] ?? [] as $next)
                <form method="POST" action="{{ route('franchises.lifecycle', $row['id']) }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="status" value="{{ $next }}">
                    @if (in_array($next, ['TERMINATED', 'SUSPENDED', 'EXPIRED'], true))
                        <input name="reason" placeholder="Reason" style="width:180px">
                    @endif
                    <button class="btn {{ $next === 'ACTIVE' ? '' : 'ghost' }}" type="submit">{{ $next }}</button>
                </form>
            @endforeach
        </section>
        <section class="card">
            <h3 style="margin-top:0">KYC</h3>
            <form method="POST" action="{{ route('franchises.kyc', $row['id']) }}">
                @csrf
                <div class="filters">
                    <select name="kyc_status">
                        @foreach (['pending','submitted','verified','rejected'] as $kyc)
                            <option value="{{ $kyc }}" @selected($row['kycStatus'] === $kyc)>{{ $kyc }}</option>
                        @endforeach
                    </select>
                    <input name="reason" placeholder="Note">
                    <button class="btn" type="submit">Save KYC</button>
                </div>
            </form>
            <form method="POST" action="{{ route('franchises.document', $row['id']) }}" enctype="multipart/form-data" style="margin-top:12px">
                @csrf
                <div class="filters">
                    <select name="type" required>
                        <option value="PAN">PAN</option>
                        <option value="AADHAAR">Aadhaar</option>
                        <option value="APPOINTMENT">Appointment letter</option>
                        <option value="ADDRESS">Address proof</option>
                        <option value="GST">GST</option>
                        <option value="PHOTO">Photo</option>
                    </select>
                    <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                    <button class="btn ghost" type="submit">Upload document</button>
                </div>
            </form>
            <div class="table-wrap" style="margin-top:12px">
                <table class="data">
                    <thead><tr><th>Type</th><th>File</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse (($row['kycFiles'] ?? []) as $doc)
                        <tr>
                            <td>{{ $doc['type'] }}</td>
                            <td>{{ $doc['originalName'] ?: ($doc['path'] ?: '—') }}</td>
                            <td><span class="pill muted">{{ $doc['status'] }}</span></td>
                            <td class="row-actions">
                                @if (! empty($doc['id']))
                                    <a class="btn ghost" href="{{ route('organization.district-kyc.file', $doc['id']) }}" target="_blank">Open</a>
                                    @if (! in_array($doc['status'], ['verified', 'rejected'], true))
                                        <form method="POST" action="{{ route('franchises.documents.review', [$row['id'], $doc['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="verified">
                                            <button class="btn" type="submit">Verify</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        @foreach ($row['documents'] as $doc)
                            @php $doc = (array) $doc; @endphp
                            <tr>
                                <td>{{ $doc['type'] ?? '' }}</td>
                                <td>{{ $doc['original_name'] ?? $doc['storage_key'] ?? '' }}</td>
                                <td>{{ $doc['status'] ?? '' }}</td>
                                <td>
                                    @if (! empty($doc['id']) && ! in_array($doc['status'] ?? '', ['verified', 'rejected'], true))
                                        <form method="POST" action="{{ route('franchises.documents.review', [$row['id'], $doc['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="verified">
                                            <button class="btn" type="submit">Verify</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <h3 style="margin-top:0">Agreement</h3>
            <form method="POST" action="{{ route('franchises.agreement', $row['id']) }}">
                @csrf
                <div class="filters">
                    <input name="version" value="v1">
                    <button class="btn" type="submit">Sign agreement</button>
                </div>
            </form>
        </section>
        <section class="card">
            <h3 style="margin-top:0">Fees</h3>
            <form method="POST" action="{{ route('franchises.fees', $row['id']) }}">
                @csrf
                <div class="filters">
                    <input name="kind" placeholder="Kind" value="onboarding" required>
                    <input name="amount_rupees" type="number" min="0.01" step="0.01" placeholder="Amount ₹" required>
                    <button class="btn" type="submit">Add fee</button>
                </div>
            </form>
            <div class="table-wrap" style="margin-top:12px">
                <table class="data">
                    <thead><tr><th>Kind</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($row['fees'] as $fee)
                        @php $fee = (array) $fee; @endphp
                        <tr>
                            <td>{{ $fee['kind'] }}</td>
                            <td>₹{{ number_format(((int) ($fee['amount_paise'] ?? 0)) / 100, 2) }}</td>
                            <td>{{ $fee['status'] }}</td>
                            <td>
                                @if (($fee['status'] ?? '') === 'due')
                                    <form method="POST" action="{{ route('franchises.fees.pay', [$row['id'], $fee['id']]) }}">
                                        @csrf
                                        <button class="btn ghost" type="submit">Mark paid</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <h3 style="margin-top:0">Commission</h3>
            <form method="POST" action="{{ route('franchises.commission', $row['id']) }}">
                @csrf
                <div class="filters">
                    <input name="commission_percent" type="number" step="0.01" min="0" max="100" value="{{ $row['commissionPercent'] }}">
                    <button class="btn" type="submit">Save</button>
                </div>
            </form>
        </section>
        <section class="card">
            <h3 style="margin-top:0">Renewal</h3>
            <form method="POST" action="{{ route('franchises.renewals', $row['id']) }}">
                @csrf
                <div class="filters">
                    <input type="date" name="period_start" required>
                    <input type="date" name="period_end" required>
                    <button class="btn" type="submit">Request renewal</button>
                </div>
            </form>
            @foreach ($row['renewals'] as $renewal)
                @php $renewal = (array) $renewal; @endphp
                <form method="POST" action="{{ route('franchises.renewals.decide', [$row['id'], $renewal['id']]) }}" style="margin-top:8px">
                    @csrf
                    <span class="muted">{{ $renewal['period_start'] }} → {{ $renewal['period_end'] }} ({{ $renewal['status'] }})</span>
                    @if (($renewal['status'] ?? '') === 'pending')
                        <button class="btn ghost" name="status" value="approved">Approve</button>
                        <button class="btn ghost" name="status" value="rejected">Reject</button>
                    @endif
                </form>
            @endforeach
        </section>
        <section class="card">
            <h3 style="margin-top:0">Territory / reassignment</h3>
            <form method="POST" action="{{ route('franchises.territory', $row['id']) }}">
                @csrf
                <div class="filters">
                    @include('partials.geo-fields', [
                        'required' => true,
                        'stateValue' => (string) ($row['stateId'] ?? ''),
                        'districtValue' => (string) ($row['districtId'] ?? ''),
                    ])
                    <button class="btn" type="submit">Reassign district</button>
                </div>
            </form>
        </section>
    @endcanany
    <section class="card">
        <h3 style="margin-top:0">Audit</h3>
        <ul>
            @foreach ($row['events'] as $event)
                @php $event = (array) $event; @endphp
                <li>{{ $event['created_at'] ?? '' }} · {{ $event['action'] }} {{ $event['from_status'] }} → {{ $event['to_status'] }} {{ $event['note'] }}</li>
            @endforeach
        </ul>
    </section>
@endsection
