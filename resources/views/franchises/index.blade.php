@extends('layouts.app')

@section('title', 'District franchise')
@section('heading', 'District franchise')

@section('content')
    <div class="hero">
        <div>
            <h1>District applications</h1>
            <p class="muted">Apply a District Head for a district, upload KYC, then <strong>Verify &amp; activate</strong>. Only one ACTIVE District Head holds each district seat. The exclusive franchise role is retired.</p>
        </div>
        <a class="btn ghost" href="{{ route('ops.module', 'district-heads') }}">District Heads</a>
    </div>
    @can('franchise.manage')
        <section class="card">
            <h3 style="margin-top:0">New District Head application</h3>
            <form method="POST" action="{{ route('franchises.store') }}">
                @csrf
                <input type="hidden" name="kind" value="DISTRICT_HEAD">
                <div class="field"><label>Trade / office name</label><input name="trade_name" required></div>
                @include('partials.geo-fields', [
                    'required' => true,
                    'emptyState' => 'Select state',
                    'emptyDistrict' => 'Select district',
                ])
                <div class="field"><label>Owner name</label><input name="name" required></div>
                <div class="field"><label>Owner email</label><input name="email" type="email" required></div>
                <div class="field"><label>Phone</label><input name="phone"></div>
                <div class="field"><label>Password</label><input name="password" type="password" required></div>
                <div class="field"><label>Application fee (₹)</label><input name="fee_amount_rupees" type="number" min="0" step="0.01" value="0"></div>
                <button class="btn" type="submit"><i data-lucide="plus"></i> Apply</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Trade</th>
                        <th>Kind</th>
                        <th>District</th>
                        <th>Owner</th>
                        <th>KYC</th>
                        <th>Status</th>
                        <th>Seat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['tradeName'] }}</strong></td>
                        <td><span class="pill muted">{{ $row['kind'] }}</span></td>
                        <td>{{ $row['districtName'] ?: ('#'.$row['districtId']) }}</td>
                        <td>
                            {{ $row['ownerName'] ?: ('User #'.$row['ownerUserId']) }}
                            @if ($row['ownerEmail'])
                                <div class="muted">{{ $row['ownerEmail'] }}</div>
                            @endif
                        </td>
                        <td><span class="pill muted">{{ $row['kycStatus'] }}</span></td>
                        <td>
                            <span class="pill {{ $row['status'] === 'ACTIVE' ? 'ok' : ($row['status'] === 'TERMINATED' ? 'bad' : 'warn') }}">{{ $row['status'] }}</span>
                        </td>
                        <td>{{ $row['exclusiveSeat'] ? 'Held' : '—' }}</td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('franchises.show', $row['id']) }}" title="Open"><i data-lucide="eye"></i></a>
                            @if ($row['status'] !== 'ACTIVE' && $row['status'] !== 'TERMINATED')
                                @canany(['franchise.manage', 'kyc.approve', 'platform.admin'])
                                    <form method="POST" action="{{ route('franchises.verify', $row['id']) }}">
                                        @csrf
                                        <button class="btn" type="submit">Verify</button>
                                    </form>
                                @endcanany
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No franchise applications.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
