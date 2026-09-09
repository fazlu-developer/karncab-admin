@extends('layouts.guest')

@section('title', 'KarnaCab Admin')

@section('content')
    <h2>Management console</h2>
    <p class="muted">Laravel controllers on the platform database. Sign in to manage users, drivers and operations.</p>
    <p style="margin-top:20px"><a class="btn" href="{{ route('login') }}"><i data-lucide="log-in"></i> Operator login</a></p>
@endsection
