@extends('layouts.guest')

@section('title', 'New password')

@section('content')
    <h2>Choose a new password</h2>
    <form method="POST" action="{{ route('password.update') }}" style="margin-top:20px">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" required>
            @error('password') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required>
        </div>
        <button class="btn" type="submit" style="width:100%;justify-content:center">Update password</button>
    </form>
@endsection
