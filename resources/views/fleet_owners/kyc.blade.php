@extends('layouts.app')
@section('title', 'Fleet Owner KYC')
@section('heading', 'Fleet Owner KYC')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="file-badge"></i> Fleet Owner KYC</h1>
            <p class="muted">Preview company documents, then approve or reject. Open a fleet owner for the full company record.</p>
        </div>
    </div>

    @forelse ($owners as $owner)
        @php
            $kyc = strtolower((string) ($owner->kyc_status ?? ''));
            $docs = json_decode((string) ($owner->documents_json ?? '[]'), true) ?: [];
            $rejectReason = $owner->kyc_rejected_reason ?? ($owner->rejected_reason ?? '');
        @endphp
        <section class="card">
            <div class="hero" style="margin:0">
                <div>
                    <h2 style="margin:0">{{ $owner->trade_name ?: 'Incomplete company' }}</h2>
                    <p class="muted">{{ $owner->owner_name }} · {{ $owner->phone ?? '—' }} · {{ $owner->email ?? '' }}</p>
                </div>
                <a class="btn ghost" href="{{ route('fleet-owners.show', $owner->id) }}">Open record</a>
            </div>
            <p>
                <span class="pill {{ in_array($kyc, ['approved', 'verified'], true) ? 'ok' : ($kyc === 'rejected' ? 'bad' : 'warn') }}">
                    {{ $kyc !== '' ? str_replace('_', ' ', $kyc) : ($owner->status ?? 'pending') }}
                </span>
            </p>
            <dl class="meta">
                <div><dt>Company type</dt><dd>{{ $owner->company_type ?: '—' }}</dd></div>
                <div><dt>GSTIN</dt><dd>{{ $owner->gstin ?: '—' }}</dd></div>
                <div><dt>PAN</dt><dd>{{ $owner->pan ?: '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $owner->address ?: '—' }}</dd></div>
                <div><dt>State / district</dt><dd>{{ $owner->state_name ?? '—' }} · {{ $owner->district_name ?? '—' }}</dd></div>
            </dl>
            @if ($rejectReason)
                <p class="error">{{ $rejectReason }}</p>
            @endif
            <div class="doc-grid">
                @forelse ($docs as $doc)
                    @php
                        $mime = strtolower((string) ($doc['mime'] ?? ''));
                        $name = strtolower((string) ($doc['originalName'] ?? ''));
                        $image = str_starts_with($mime, 'image/') || (bool) preg_match('/\.(jpg|jpeg|png|webp)$/', $name);
                        $pdf = str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');
                        $fileUrl = ! empty($doc['type']) ? route('fleet-owners.documents.file', [$owner->id, $doc['type']]) : null;
                    @endphp
                    <article class="doc-card">
                        <strong>{{ str_replace('_', ' ', $doc['type'] ?? 'Document') }}</strong>
                        <p class="muted">{{ $doc['originalName'] ?? 'file' }} · {{ $doc['status'] ?? 'pending' }}</p>
                        @if ($fileUrl && $image)
                            <a href="{{ $fileUrl }}" target="_blank" rel="noopener">
                                <img src="{{ $fileUrl }}" alt="{{ $doc['type'] ?? 'Document' }}" style="width:100%;max-height:160px;object-fit:cover;border-radius:8px">
                            </a>
                        @elseif ($fileUrl && $pdf)
                            <iframe src="{{ $fileUrl }}" title="{{ $doc['type'] ?? 'Document' }}" style="width:100%;height:160px;border:0"></iframe>
                        @endif
                        @if ($fileUrl)
                            <p><a href="{{ $fileUrl }}" target="_blank" rel="noopener">Open preview</a></p>
                        @endif
                        @if (! empty($doc['type']))
                            <form method="POST" action="{{ route('fleet-owners.documents.destroy', [$owner->id, $doc['type']]) }}" onsubmit="return confirm('Delete this document?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Delete</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="muted">No documents uploaded.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('fleet-owners.documents.store', $owner->id) }}" enctype="multipart/form-data" style="margin-top:12px">
                @csrf
                <div class="filters">
                    <div class="field">
                        <label>Replace document</label>
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
                <button class="btn ghost" type="submit">Save file</button>
            </form>
            <div class="row-actions" style="margin-top:12px">
                <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}">
                    @csrf
                    <input type="hidden" name="approve" value="1">
                    <button class="btn" type="submit">Approve</button>
                </form>
                <form method="POST" action="{{ route('fleet-owners.verify', $owner->id) }}">
                    @csrf
                    <input type="hidden" name="approve" value="0">
                    <input name="reason" value="{{ $rejectReason }}" placeholder="Reject reason" required>
                    <button class="btn ghost" type="submit">Reject</button>
                </form>
            </div>
        </section>
    @empty
        <section class="card"><p class="muted">No fleet owners yet.</p></section>
    @endforelse
@endsection
