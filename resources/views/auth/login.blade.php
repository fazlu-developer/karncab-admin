@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2>Sign in</h2>
    <p class="muted">Use your operator email to open the management console.</p>
    <form method="POST" action="{{ route('login') }}" style="margin-top:20px">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
        </div>
        <label class="remember"><input type="checkbox" name="remember" value="1"> Remember this device</label>
        <button class="btn" type="submit" style="width:100%;justify-content:center"><i data-lucide="log-in"></i> Continue</button>
    </form>
    <p style="margin-top:16px"><a href="{{ route('password.request') }}"><i data-lucide="key-round"></i> Forgot password?</a></p>
    <p class="muted">New operator? <a href="{{ route('register') }}">Request access</a></p>
@endsection
