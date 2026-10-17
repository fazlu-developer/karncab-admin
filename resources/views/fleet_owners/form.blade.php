@extends('layouts.app')
@section('title', $owner ? 'Edit Fleet Owner' : 'Add Fleet Owner')
@section('heading', $owner ? 'Edit Fleet Owner' : 'Add Fleet Owner')
@section('content')
    <section class="card" style="max-width:720px">
        <form method="POST" action="{{ $owner ? route('fleet-owners.update', $owner->id) : route('fleet-owners.store') }}">
            @csrf
            <div class="field"><label>Fleet Owner Name</label><input name="name" value="{{ old('name', $owner->owner_name ?? '') }}" required></div>
            <div class="field"><label>Business Name</label><input name="trade_name" value="{{ old('trade_name', $owner->trade_name ?? '') }}" required></div>
            <div class="field"><label>Mobile</label><input name="phone" value="{{ old('phone', $owner->phone ?? '') }}"></div>
            <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $owner->email ?? '') }}" required></div>
            <div class="field"><label>Password</label><input name="password" type="password" {{ $owner ? '' : 'required' }} placeholder="{{ $owner ? 'Leave blank to keep the current password' : '' }}"></div>
            <div class="field"><label>Address</label><input name="address" value="{{ old('address', $owner->address ?? '') }}"></div>
            @php
                $companyTypes = [
                    'PROPRIETORSHIP' => 'Proprietorship',
                    'PARTNERSHIP' => 'Partnership',
                    'LLP' => 'LLP',
                    'PRIVATE_LIMITED' => 'Private Limited',
                    'PUBLIC_LIMITED' => 'Public Limited',
                    'OPC' => 'One Person Company',
                    'INDIVIDUAL' => 'Individual',
                ];
                $selectedType = (string) old('company_type', $owner->company_type ?? '');
            @endphp
            <div class="field">
                <label>Company type</label>
                <select name="company_type">
                    <option value="">Select company type</option>
                    @foreach ($companyTypes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                    @endforeach
                    @if ($selectedType !== '' && ! isset($companyTypes[$selectedType]))
                        <option value="{{ $selectedType }}" selected>{{ $selectedType }}</option>
                    @endif
                </select>
            </div>
            <div class="field"><label>GSTIN</label><input name="gstin" value="{{ old('gstin', $owner->gstin ?? '') }}"></div>
            <div class="field"><label>PAN</label><input name="pan" value="{{ old('pan', $owner->pan ?? '') }}"></div>
            <div class="field">
                <label>Fleet commission % of fare</label>
                <input name="commission_percent" type="number" min="0" max="100" step="0.01" value="{{ old('commission_percent', $owner->commission_percent ?? '') }}" placeholder="Leave blank to use default">
                <p class="muted">Blank uses the Ride Settings default fleet commission.</p>
            </div>
            @include('partials.geo-fields', [
                'required' => true,
                'stateValue' => (string) old('state_id', $owner->state_id ?? ''),
                'districtValue' => (string) old('district_id', $owner->district_id ?? ''),
            ])
            <div class="field">
                <label>Franchise (optional)</label>
                <select name="franchise_id">
                    <option value="">None</option>
                    @foreach ($franchises as $franchise)
                        <option value="{{ $franchise->id }}" @selected((string) old('franchise_id', $owner->franchise_id ?? '') === (string) $franchise->id)>{{ $franchise->trade_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach (['PENDING', 'ACTIVE', 'SUSPENDED'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $owner->status ?? 'PENDING') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">{{ $owner ? 'Save changes' : 'Save' }}</button>
        </form>
    </section>
@endsection
