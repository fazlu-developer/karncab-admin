@extends('layouts.app')

@section('title', 'Lead #'.$lead['id'])
@section('heading', 'Website lead')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $lead['name'] }}</h1>
            <p class="muted">{{ $lead['type'] }} · {{ $lead['status'] }} · {{ $lead['createdAt'] }}</p>
        </div>
        <a class="btn ghost" href="{{ route('leads.index') }}">All leads</a>
    </div>
    <section class="card">
        <p><b>Phone</b> {{ $lead['phone'] }}</p>
        <p><b>Email</b> {{ $lead['email'] ?: '—' }}</p>
        <p><b>District</b> {{ $lead['district'] ?: '—' }}</p>
        <p style="white-space:pre-wrap">{{ $lead['message'] }}</p>
        @if ($lead['payload'])
            <p class="muted" style="white-space:pre-wrap">{{ $lead['payload'] }}</p>
        @endif
        <form method="POST" action="{{ route('leads.update', $lead['id']) }}" style="margin-top:16px">
            @csrf
            @method('PATCH')
            <label>Status</label>
            <select name="status">
                @foreach ($catalog['statuses'] as $status)
                    <option value="{{ $status }}" @selected($lead['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <p><button class="btn" type="submit">Save status</button></p>
        </form>
    </section>
@endsection
