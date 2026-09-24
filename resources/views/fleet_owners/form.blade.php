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
            <div class="field"><label>Company type</label><input name="company_type" value="{{ old('company_type', $owner->company_type ?? '') }}" placeholder="PRIVATE_LIMITED"></div>
            <div class="field"><label>GSTIN</label><input name="gstin" value="{{ old('gstin', $owner->gstin ?? '') }}"></div>
            <div class="field"><label>PAN</label><input name="pan" value="{{ old('pan', $owner->pan ?? '') }}"></div>
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
