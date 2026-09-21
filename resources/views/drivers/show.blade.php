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
        $missingDocs = collect($requiredDocs)->reject(fn ($type) => $docsByType->has($type));
    @endphp
    <div class="hero">
        <div>
            <h1>{{ $user->name ?? 'Driver' }}</h1>
            <p class="muted">{{ $user->phone ?? '—' }} · {{ $user->email ?? '' }}</p>
        </div>
        <div class="row-actions">
            <a class="btn ghost" href="{{ route('drivers.index') }}">List</a>
            @can('drivers.edit')
                <a class="btn ghost" href="{{ route('drivers.edit', $driver) }}"><i data-lucide="pencil"></i> Edit account</a>
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
        <p class="muted">Submitted {{ $driver->application_submitted_at ?: 'not yet' }} · {{ $stateName ?: '—' }} / {{ $districtName ?: ($driver->city ?: '—') }}</p>
    </section>

    <div class="grid-2">
        <section class="card">
            <h2>Onboarding information</h2>
            <dl class="dl">
                <div><dt>Name</dt><dd>{{ $user->name ?? '—' }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $user->phone ?? '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $user->email ?? '—' }}</dd></div>
                <div><dt>Date of birth</dt><dd>{{ $user->date_of_birth ?? '—' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $user->gender ?? '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $user->last_address ?? '—' }}</dd></div>
                <div><dt>State</dt><dd>{{ $stateName ?: '—' }}</dd></div>
                <div><dt>District</dt><dd>{{ $districtName ?: '—' }}</dd></div>
                <div><dt>Aadhaar</dt><dd>{{ $driver->aadhaar_last4 ? '••••'.$driver->aadhaar_last4 : '—' }}</dd></div>
                <div><dt>PAN</dt><dd>{{ $driver->pan_last4 ? '••••'.$driver->pan_last4 : '—' }}</dd></div>
                <div><dt>Licence expiry</dt><dd>{{ $driver->license_expires_at ?: '—' }}</dd></div>
                <div><dt>Licence</dt><dd>{{ $driver->license_no }}</dd></div>
                <div><dt>Emergency</dt><dd>{{ $driver->emergency_name ?: ($user->emergency_name ?? '—') }} {{ $driver->emergency_phone ?? $user->emergency_phone }}</dd></div>
            </dl>
        </section>
        <section class="card">
            <h2>Vehicle &amp; payout</h2>
            <dl class="dl">
                <div><dt>Family / category</dt><dd>{{ $driver->vehicle_family ?? '—' }} / {{ $vehicleCatalog[$vehicle->category ?? '']['label'] ?? ($vehicle->category ?? '—') }}</dd></div>
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

    @can('drivers.edit')
        <section class="card">
            <h2>Update onboarding info</h2>
            <form method="POST" action="{{ route('drivers.onboarding', $driver) }}">
                @csrf
                <div class="filters">
                    <div class="field"><label>Name</label><input name="name" value="{{ old('name', $user->name) }}"></div>
                    <div class="field"><label>Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}"></div>
                    <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $user->email) }}"></div>
                    <div class="field"><label>Date of birth</label><input name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth) }}"></div>
                    <div class="field">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select</option>
                            @foreach (['MALE' => 'Male', 'FEMALE' => 'Female', 'OTHER' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Address</label><input name="last_address" value="{{ old('last_address', $user->last_address) }}"></div>
                    @include('partials.geo-fields', [
                        'stateValue' => (string) old('state_id', $user->state_id ?: $driver->state_id),
                        'districtValue' => (string) old('district_id', $user->district_id ?: $driver->district_id),
                    ])
                    <div class="field"><label>City</label><input name="city" value="{{ old('city', $driver->city) }}"></div>
                    <div class="field"><label>Licence number</label><input name="license_no" value="{{ old('license_no', $driver->license_no) }}"></div>
                    <div class="field"><label>Licence expiry</label><input name="license_expires_at" type="date" value="{{ old('license_expires_at', $driver->license_expires_at) }}"></div>
                    <div class="field">
                        <label>Vehicle family</label>
                        <select name="vehicle_family">
                            <option value="">Select</option>
                            @foreach (['BIKE' => 'Bike', 'AUTO' => 'Auto', 'CAR' => 'Car'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('vehicle_family', $driver->vehicle_family) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Vehicle type</label>
                        <select name="category">
                            <option value="">Select</option>
                            @foreach ($vehicleCatalog ?? [] as $key => $meta)
                                <option value="{{ $key }}" @selected(old('category', $vehicle->category ?? '') === $key)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Registration number</label><input name="registration_no" value="{{ old('registration_no', $vehicle->registration_no ?? '') }}"></div>
                    <div class="field"><label>Brand</label><input name="brand" value="{{ old('brand', $vehicle->brand ?? '') }}"></div>
                    <div class="field"><label>Model</label><input name="model" value="{{ old('model', $vehicle->model ?? '') }}"></div>
                    <div class="field"><label>Year</label><input name="year" type="number" value="{{ old('year', $vehicle->year ?? '') }}"></div>
                    <div class="field"><label>Colour</label><input name="color" value="{{ old('color', $vehicle->color ?? '') }}"></div>
                    <div class="field"><label>Fuel</label><input name="fuel" value="{{ old('fuel', $vehicle->fuel ?? '') }}"></div>
                    <div class="field"><label>Emergency name</label><input name="emergency_name" value="{{ old('emergency_name', $driver->emergency_name ?: $user->emergency_name) }}"></div>
                    <div class="field"><label>Emergency phone</label><input name="emergency_phone" value="{{ old('emergency_phone', $driver->emergency_phone ?: $user->emergency_phone) }}"></div>
                    <div class="field"><label>Aadhaar (12 digits, optional update)</label><input name="aadhaar" inputmode="numeric"></div>
                    <div class="field"><label>PAN (optional update)</label><input name="pan"></div>
                    <div class="field"><label>Account holder</label><input name="bank_account_holder" value="{{ old('bank_account_holder', $driver->bank_account_holder) }}"></div>
                    <div class="field"><label>IFSC</label><input name="bank_ifsc" value="{{ old('bank_ifsc', $driver->bank_ifsc) }}"></div>
                    <div class="field"><label>Account number (optional update)</label><input name="account_number"></div>
                    <div class="field"><label>UPI</label><input name="upi_id" value="{{ old('upi_id', $driver->upi_id) }}"></div>
                    <div class="field">
                        <label>Driver type</label>
                        <select name="driver_type">
                            <option value="individual_driver" @selected(old('driver_type', $driver->fleet_owner_id ? 'fleet_driver' : 'individual_driver') === 'individual_driver')>Individual</option>
                            <option value="fleet_driver" @selected(old('driver_type', $driver->fleet_owner_id ? 'fleet_driver' : 'individual_driver') === 'fleet_driver')>Fleet driver</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Fleet owner</label>
                        <select name="fleet_owner_id">
                            <option value="">None</option>
                            @foreach ($fleetOwners ?? [] as $fleet)
                                <option value="{{ $fleet->id }}" @selected((string) old('fleet_owner_id', $driver->fleet_owner_id) === (string) $fleet->id)>
                                    {{ $fleet->trade_name ?: ($fleet->owner_name ?: 'Fleet #'.$fleet->id) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button class="btn" type="submit">Save onboarding</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Attachments</h2>
        <p class="muted">Required: {{ implode(', ', $requiredDocs) }}. Approve each file, then approve the application so the driver app opens Home.</p>
        @if ($missingDocs->isNotEmpty())
            <p class="muted">Still missing: {{ $missingDocs->implode(', ') }}</p>
        @endif
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
                    @if ($doc->isMissing())
                        <p class="error">The file is not on the server. Upload this document again to preview it.</p>
                    @elseif ($doc->fileUrl())
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
        @can('drivers.edit')
            <form method="POST" action="{{ route('drivers.documents.store', $driver) }}" enctype="multipart/form-data" style="margin-top:16px">
                @csrf
                <h3>Upload attachment</h3>
                <div class="filters">
                    <div class="field">
                        <label>Document type</label>
                        <select name="type" required>
                            @foreach ($docTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>File</label>
                        <input type="file" name="file" accept="image/*,application/pdf" required>
                    </div>
                    <div class="field">
                        <label>Expiry (licence / insurance)</label>
                        <input type="date" name="expires_at">
                    </div>
                </div>
                <button class="btn" type="submit">Upload attachment</button>
            </form>
        @endcan
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
