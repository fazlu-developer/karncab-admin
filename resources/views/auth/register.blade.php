@extends('layouts.guest')

@section('title', 'New operator')

@section('content')
    <h2>Request operator access</h2>
    <p class="muted">The first account becomes Admin. Later accounts stay pending until approved.</p>
    <form method="POST" action="{{ route('register') }}" style="margin-top:20px">
        @csrf
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            @error('password') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required>
        </div>
        <div class="field">
            <label for="comment">Comment</label>
            <textarea id="comment" name="comment" rows="3" maxlength="500" placeholder="District, role, or why you need access">{{ old('comment') }}</textarea>
            @error('comment') <div class="error">{{ $message }}</div> @enderror
        </div>
        <button class="btn" type="submit" style="width:100%;justify-content:center">Request access</button>
    </form>
    <p style="margin-top:16px"><a href="{{ route('login') }}">Already have access?</a></p>
@endsection
