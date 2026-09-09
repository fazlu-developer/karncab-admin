@extends('layouts.app')

@section('title', $user->name)
@section('heading', 'User')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $user->name }}</h1>
            <p class="muted">{{ $user->email }} · {{ $user->phone ?: 'No phone' }}</p>
        </div>
        <div class="row-actions">
            <a class="btn ghost" href="{{ route('users.index') }}"><i data-lucide="list"></i> List</a>
            @can('users.edit')
                <a class="btn" href="{{ route('users.edit', $user) }}"><i data-lucide="pencil"></i> Edit</a>
            @endcan
        </div>
    </div>
    <section class="card">
        <p>
            <span class="pill muted"><i data-lucide="shield"></i> {{ $user->role }}</span>
            <span class="pill {{ $user->status === 'ACTIVE' ? 'ok' : ($user->status === 'PENDING' ? 'warn' : 'bad') }}">
                <i data-lucide="{{ $user->status === 'ACTIVE' ? 'check-circle' : 'alert-circle' }}"></i>
                {{ $user->status }}
            </span>
        </p>
        @if ($user->driver)
            <p>
                <i data-lucide="id-card"></i>
                Driver profile <a href="{{ route('drivers.show', $user->driver) }}">#{{ $user->driver->id }}</a>
                · KYC {{ $user->driver->kyc_status }}
            </p>
        @endif
        <p class="muted">Created {{ $user->created_at }}</p>
    </section>
@endsection
