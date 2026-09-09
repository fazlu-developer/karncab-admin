@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'New user')
@section('heading', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $user->exists ? 'Edit user' : 'Create user' }}</h1>
            <p class="muted">Password is stored as a hash. Bank and device secrets are never shown.</p>
        </div>
        <a class="btn ghost" href="{{ route('users.index') }}">Back to list</a>
    </div>
    <section class="card" style="max-width:820px">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
            @csrf
            @if ($user->exists) @method('PUT') @endif
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
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ $user->exists ? 'New password (optional)' : 'Password' }}</label>
                <input name="password" type="password" {{ $user->exists ? '' : 'required' }}>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <button class="btn" type="submit"><i data-lucide="save"></i> Save</button>
        </form>
    </section>
@endsection
