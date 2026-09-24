@extends('layouts.app')
@section('title', $owner->trade_name ?: 'Fleet owner')
@section('heading', 'Fleet owner application')
@section('content')
    @php
        $kyc = strtolower((string) ($owner->kyc_status ?? ''));
        $docs = json_decode((string) ($owner->documents_json ?? '[]'), true) ?: [];
    @endphp
    <div class="hero">
        <div>
            <h1>{{ $owner->trade_name ?: 'Incomplete company' }}</h1>
            <p class="muted">{{ $owner->owner_name }} · {{ $owner->phone ?? '—' }} · {{ $owner->email ?? '' }}</p>
        </div>
        <div>
            @can('fleet.manage')
                <a class="btn" href="{{ route('fleet-owners.edit', $owner->id) }}">Edit info</a>
            @endcan
            <a class="btn ghost" href="{{ route('fleet-owners.index') }}">List</a>
        </div>
    </div>

    <section class="card">
        <p>
            <span class="pill {{ in_array($kyc, ['approved', 'verified'], true) ? 'ok' : ($kyc === 'rejected' ? 'bad' : 'warn') }}">
                {{ $kyc !== '' ? str_replace('_', ' ', $kyc) : $owner->status }}
            </span>
            <span class="pill muted">{{ $owner->status }}</span>
        </p>
        <dl class="meta">
            <div><dt>Company type</dt><dd>{{ $owner->company_type ?: '—' }}</dd></div>
            <div><dt>GSTIN</dt><dd>{{ $owner->gstin ?: '—' }}</dd></div>
            <div><dt>PAN</dt><dd>{{ $owner->pan ?: '—' }}</dd></div>
            <div><dt>Address</dt><dd>{{ $owner->address ?: '—' }}</dd></div>
            <div><dt>State / district</dt><dd>{{ $owner->state_name ?? '—' }} · {{ $owner->district_name ?? '—' }}</dd></div>
            <div><dt>Submitted</dt><dd>{{ $owner->submitted_at ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Company documents</h2>
        @forelse ($docs as $doc)
            <p>
                {{ $doc['type'] ?? 'Document' }} · {{ $doc['originalName'] ?? 'file' }} · {{ $doc['status'] ?? 'pending' }}
                @if (! empty($doc['type']))
                    <a href="{{ route('fleet-owners.documents.file', [$owner->id, $doc['type']]) }}" target="_blank" rel="noopener">View</a>
                @endif
            </p>
        @empty
            <p class="muted">No company documents uploaded yet.</p>
        @endforelse
        @can('fleet.manage')
            <form method="POST" action="{{ route('fleet-owners.documents.store', $owner->id) }}" enctype="multipart/form-data" style="margin-top:16px">
                @csrf
                <h3>Upload or replace attachment</h3>
                <div class="filters">
                    <div class="field">
                        <label>Document type</label>
                        <select name="type" required>
                            @foreach (['GST', 'PAN', 'COMPANY_REG', 'ADDRESS_PROOF', 'BANK', 'TRADE_LICENSE'] as $type)
                                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>File</label>
                        <input type="file" name="file" accept="image/*,application/pdf" required>
                    </div>
                </div>
                <button class="btn" type="submit">Save attachment</button>
            </form>
        @endcan
    </section>

    @can('fleet.manage')
        @if (! in_array($kyc, ['approved', 'verified'], true) || strtoupper((string) $owner->status) !== 'ACTIVE')
            <section class="card">
                <h2>Admin verification</h2>
                <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="approve" value="1">
                    <button class="btn" type="submit">Verify fleet owner</button>
                </form>
                <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}" style="display:inline;margin-left:8px">
                    @csrf
                    <input type="hidden" name="approve" value="0">
                    <input name="reason" placeholder="Reject reason" style="max-width:240px">
                    <button class="btn ghost" type="submit">Reject</button>
                </form>
            </section>
        @endif
    @endcan
@endsection
