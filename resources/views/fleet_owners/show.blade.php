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
            @php
                $mime = strtolower((string) ($doc['mime'] ?? ''));
                $name = strtolower((string) ($doc['originalName'] ?? ''));
                $image = str_starts_with($mime, 'image/') || (bool) preg_match('/\.(jpg|jpeg|png|webp)$/', $name);
                $pdf = str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');
                $fileUrl = ! empty($doc['type']) ? route('fleet-owners.documents.file', [$owner->id, $doc['type']]) : null;
            @endphp
            <article style="border-top:1px solid #eee;padding:12px 0">
                <p>
                    <strong>{{ str_replace('_', ' ', $doc['type'] ?? 'Document') }}</strong>
                    · {{ $doc['originalName'] ?? 'file' }}
                    · <span class="pill muted">{{ $doc['status'] ?? 'pending' }}</span>
                </p>
                @if ($fileUrl && $image)
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $fileUrl }}" alt="{{ $doc['type'] ?? 'Document' }}" style="width:220px;height:150px;object-fit:cover;border-radius:8px">
                    </a>
                @elseif ($fileUrl && $pdf)
                    <iframe src="{{ $fileUrl }}" title="{{ $doc['type'] ?? 'Document' }}" style="width:100%;max-width:420px;height:220px;border:0;border-radius:8px"></iframe>
                @endif
                @if ($fileUrl)
                    <p><a class="btn ghost" href="{{ $fileUrl }}" target="_blank" rel="noopener">Open preview</a></p>
                @endif
                @can('fleet.manage')
                    @if (! empty($doc['type']))
                        <form method="POST" action="{{ route('fleet-owners.documents.destroy', [$owner->id, $doc['type']]) }}" onsubmit="return confirm('Delete this document?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">Delete</button>
                        </form>
                    @endif
                @endcan
            </article>
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
        <section class="card">
            <h2>Approve or reject</h2>
            @php
                $rejectReason = $owner->kyc_rejected_reason ?? ($owner->rejected_reason ?? '');
            @endphp
            @if ($rejectReason)
                <p class="error">{{ $rejectReason }}</p>
            @endif
            <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}" style="margin-bottom:12px">
                @csrf
                <input type="hidden" name="approve" value="1">
                <button class="btn" type="submit">Approve</button>
            </form>
            <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}">
                @csrf
                <input type="hidden" name="approve" value="0">
                <div class="field">
                    <label>Reject reason</label>
                    <input name="reason" value="{{ $rejectReason }}" placeholder="Why this KYC is rejected" required>
                </div>
                <button class="btn ghost" type="submit">Reject</button>
            </form>
        </section>
    @endcan
@endsection
