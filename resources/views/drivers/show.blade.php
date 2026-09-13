@extends('layouts.app')

@section('title', $driver->user->name ?? 'Driver')
@section('heading', 'Driver application')

@section('content')
    @php
        $user = $driver->user;
        $vehicle = $driver->vehicles->first();
        $docsByType = $driver->documents->keyBy('type');
        $requiredReady = collect($requiredDocs)->every(fn ($type) => in_array(optional($docsByType->get($type))->status, ['verified', 'approved'], true));
        $expiredDocs = $driver->documents->filter(fn ($doc) => $doc->status === 'expired' || ($doc->expires_at && $doc->expires_at->isPast()));
    @endphp
    <div class="hero">
        <div>
            <h1>{{ $user->name ?? 'Driver' }}</h1>
            <p class="muted">{{ $user->phone ?? '—' }} · {{ $user->email ?? '' }}</p>
        </div>
        <div class="row-actions">
            <a class="btn ghost" href="{{ route('drivers.index') }}">List</a>
            @can('drivers.edit')
                <a class="btn ghost" href="{{ route('drivers.edit', $driver) }}"><i data-lucide="pencil"></i> Edit</a>
            @endcan
        </div>
    </div>

    <section class="card">
        <p>
            <span class="pill {{ in_array($driver->kyc_status, ['verified', 'approved'], true) ? 'ok' : ($driver->kyc_status === 'rejected' ? 'bad' : 'warn') }}">
                {{ str_replace('_', ' ', $driver->kyc_status) }}
            </span>
            <span class="pill {{ $user?->status === 'ACTIVE' ? 'ok' : 'muted' }}">{{ $user->status ?? '—' }}</span>
            @if ($driver->online)
                <span class="pill ok">Online</span>
            @else
                <span class="pill muted">Offline</span>
            @endif
        </p>
        @if ($driver->kyc_rejected_reason)
            <p class="error">{{ $driver->kyc_rejected_reason }}</p>
        @endif
        <p class="muted">Submitted {{ $driver->application_submitted_at ?: 'not yet' }} · City {{ $driver->city ?: '—' }}</p>
    </section>

    <div class="grid-2">
        <section class="card">
            <h2>Personal details</h2>
            <dl class="dl">
                <div><dt>Name</dt><dd>{{ $user->name ?? '—' }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $user->phone ?? '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $user->email ?? '—' }}</dd></div>
                <div><dt>Date of birth</dt><dd>{{ $user->date_of_birth ?? '—' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $user->gender ?? '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $user->last_address ?? '—' }}</dd></div>
                <div><dt>State / city</dt><dd>{{ $user->state_id ?? '—' }} / {{ $driver->city ?: '—' }}</dd></div>
                <div><dt>Aadhaar</dt><dd>{{ $driver->aadhaar_last4 ? '••••'.$driver->aadhaar_last4 : '—' }}</dd></div>
                <div><dt>PAN</dt><dd>{{ $driver->pan_last4 ? '••••'.$driver->pan_last4 : '—' }}</dd></div>
                <div><dt>Licence expiry</dt><dd>{{ $driver->license_expires_at ?: '—' }}</dd></div>
                <div><dt>Licence</dt><dd>{{ $driver->license_no }}</dd></div>
                <div><dt>Emergency</dt><dd>{{ $driver->emergency_name ?: '—' }} {{ $driver->emergency_phone }}</dd></div>
            </dl>
        </section>
        <section class="card">
            <h2>Vehicle &amp; payout</h2>
            <dl class="dl">
                <div><dt>Family / category</dt><dd>{{ $driver->vehicle_family ?? '—' }} / {{ $vehicle->category ?? '—' }}</dd></div>
                <div><dt>Registration</dt><dd>{{ $vehicle->registration_no ?? '—' }}</dd></div>
                <div><dt>Make / model</dt><dd>{{ trim(($vehicle->brand ?? '').' '.($vehicle->model ?? '')) ?: '—' }}</dd></div>
                <div><dt>Year / colour</dt><dd>{{ $vehicle->year ?? '—' }} · {{ $vehicle->color ?? '—' }}</dd></div>
                <div><dt>Fuel</dt><dd>{{ $vehicle->fuel ?? '—' }}</dd></div>
                <div><dt>Account holder</dt><dd>{{ $driver->bank_account_holder ?: '—' }}</dd></div>
                <div><dt>IFSC</dt><dd>{{ $driver->bank_ifsc ?: '—' }}</dd></div>
                <div><dt>Account</dt><dd>{{ $driver->bank_account_last4 ? '••••'.$driver->bank_account_last4 : '—' }}</dd></div>
                <div><dt>UPI</dt><dd>{{ $driver->upi_id ?: '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="card">
        <h2>Attachments</h2>
        <p class="muted">Required: {{ implode(', ', $requiredDocs) }}. Approve each file, then approve the application so the driver app opens Home.</p>
        <div class="doc-grid">
            @forelse ($driver->documents as $doc)
                <article class="doc-card">
                    <div class="doc-head">
                        <strong>{{ $doc->label() }}</strong>
                        <span class="pill {{ $doc->status === 'verified' ? 'ok' : ($doc->status === 'rejected' ? 'bad' : 'warn') }}">{{ $doc->status }}</span>
                    </div>
                    @if ($doc->expires_at)
                        <p class="muted">Expires {{ $doc->expires_at->toDateString() }}</p>
                    @endif
                    @if ($doc->fileUrl())
                        @if ($doc->isImage())
                            <a href="{{ $doc->fileUrl() }}" target="_blank" rel="noopener">
                                <img class="doc-thumb" src="{{ $doc->fileUrl() }}" alt="{{ $doc->label() }}">
                            </a>
                        @else
                            <a class="btn ghost" href="{{ $doc->fileUrl() }}" target="_blank" rel="noopener">Open file</a>
                        @endif
                    @else
                        <p class="muted">No preview available.</p>
                    @endif
                    <p class="muted">{{ $doc->original_name }} · {{ $doc->mime }}</p>
                    @if ($doc->rejection_reason)
                        <p class="error">{{ $doc->rejection_reason }}</p>
                    @endif
                    @can('drivers.edit')
                        <form class="doc-actions" method="POST" action="{{ route('drivers.documents.review', [$driver, $doc]) }}">
                            @csrf
                            <input type="hidden" name="status" value="verified">
                            <button class="btn" type="submit">Approve file</button>
                        </form>
                        <form class="doc-actions" method="POST" action="{{ route('drivers.documents.review', [$driver, $doc]) }}">
                            @csrf
                            <input type="hidden" name="status" value="rejected">
                            <input name="rejection_reason" placeholder="Rejection reason" required>
                            <button class="btn danger" type="submit">Reject file</button>
                        </form>
                    @endcan
                </article>
            @empty
                <p class="muted">No documents uploaded yet.</p>
            @endforelse
        </div>
    </section>

    @can('drivers.approve')
        <section class="card">
            <h2>Update driver status</h2>
            <p class="muted">{{ $requiredReady ? 'Required files are verified. Approving opens Home in the driver app after refresh.' : 'Review images below. You can still approve the application if the profile is complete enough.' }}</p>
            @if (isset($expiredDocs) && $expiredDocs->isNotEmpty())
                <p class="error">Expired: {{ $expiredDocs->map->label()->implode(', ') }}</p>
            @endif
            <div class="row-actions" style="margin-top:12px">
                <form method="POST" action="{{ route('drivers.kyc.review', $driver) }}">
                    @csrf
                    <input type="hidden" name="status" value="verified">
                    <label class="muted" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                        <input type="checkbox" name="force" value="1" {{ $requiredReady ? '' : 'checked' }}>
                        Approve anyway
                    </label>
                    <button class="btn" type="submit">Approve application</button>
                </form>
                <form method="POST" action="{{ route('drivers.kyc.review', $driver) }}" class="filters" style="flex:1">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    <input name="reason" placeholder="Rejection reason" required>
                    <button class="btn danger" type="submit">Reject application</button>
                </form>
            </div>
        </section>
    @endcan
@endsection
