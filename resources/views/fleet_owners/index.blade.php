@extends('layouts.app')
@section('title', 'Fleet Owners')
@section('heading', 'Fleet Owners / Operators')
@section('content')
    <div class="hero">
        <div>
            <h1>Fleet Owners</h1>
            <p class="muted">App sign-ups stay under review until you verify the company. Then the fleet owner can add drivers and vehicles.</p>
        </div>
        @can('fleet.manage')
            <a class="btn" href="{{ route('fleet-owners.create') }}">Add Fleet Owner</a>
        @endcan
    </div>
    <section class="card">
        <table class="data">
            <thead><tr><th>Business</th><th>Owner</th><th>Mobile</th><th>Company</th><th>KYC</th><th>Status</th><th>District</th><th></th></tr></thead>
            <tbody>
            @forelse ($owners as $row)
                @php
                    $kyc = strtolower((string) ($row->kyc_status ?? ''));
                    $status = strtoupper((string) ($row->status ?? 'ACTIVE'));
                @endphp
                <tr>
                    <td><a href="{{ route('fleet-owners.show', $row->id) }}">{{ $row->trade_name ?: 'Incomplete company' }}</a></td>
                    <td>{{ $row->owner_name }}</td>
                    <td>{{ $row->phone ?? '—' }}</td>
                    <td>{{ $row->company_type ?? '—' }}</td>
                    <td>
                        <span class="pill {{ in_array($kyc, ['approved', 'verified'], true) ? 'ok' : ($kyc === 'rejected' ? 'bad' : 'warn') }}">
                            {{ $kyc !== '' ? str_replace('_', ' ', $kyc) : ($status === 'ACTIVE' ? 'approved' : 'pending') }}
                        </span>
                    </td>
                    <td>{{ $status }}</td>
                    <td>{{ $row->district_name ?? '—' }}{{ $row->state_name ? ' · '.$row->state_name : '' }}</td>
                    <td><a class="icon-btn" href="{{ route('fleet-owners.show', $row->id) }}" title="Review"><i data-lucide="eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No fleet owners in scope.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
