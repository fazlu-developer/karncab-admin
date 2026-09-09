@extends('layouts.app')

@section('title', $driver->exists ? 'Edit driver' : 'New driver')
@section('heading', $driver->exists ? 'Edit driver' : 'New driver')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $driver->exists ? 'Edit driver' : 'Register driver' }}</h1>
            <p class="muted">Creates or updates the driver row and the linked user.</p>
        </div>
        <a class="btn ghost" href="{{ route('drivers.index') }}">Back to list</a>
    </div>
    <section class="card" style="max-width:820px">
        <form method="POST" action="{{ $driver->exists ? route('drivers.update', $driver) : route('drivers.store') }}">
            @csrf
            @if ($driver->exists) @method('PUT') @endif
            <div class="field">
                <label>Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Phone</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}">
            </div>
            <div class="field">
                <label>Account status</label>
                <select name="status">
                    @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status ?? 'ACTIVE') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>License number</label>
                <input name="license_no" value="{{ old('license_no', $driver->license_no) }}" required>
            </div>
            <div class="field">
                <label>KYC</label>
                <select name="kyc_status">
                    @foreach (['pending','under_review','verified','rejected'] as $kyc)
                        <option value="{{ $kyc }}" @selected(old('kyc_status', $driver->kyc_status) === $kyc)>{{ $kyc }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label><input type="checkbox" name="online" value="1" @checked(old('online', $driver->online))> Online / on duty</label>
            </div>
            <div class="field">
                <label>{{ $driver->exists ? 'New password (optional)' : 'Password' }}</label>
                <input name="password" type="password" {{ $driver->exists ? '' : 'required' }}>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <button class="btn" type="submit"><i data-lucide="save"></i> Save driver</button>
        </form>
    </section>
@endsection
