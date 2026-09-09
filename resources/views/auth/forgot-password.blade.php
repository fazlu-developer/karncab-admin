@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h2>Reset password</h2>
    <p class="muted">Enter the operator email. If it exists, we send a reset link (logged locally in development).</p>
    <form method="POST" action="{{ route('password.email') }}" style="margin-top:20px">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <button class="btn" type="submit" style="width:100%;justify-content:center"><i data-lucide="mail"></i> Send reset link</button>
    </form>
    <p style="margin-top:16px"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
