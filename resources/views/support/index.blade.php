@extends('layouts.app')

@section('title', 'Support tickets')
@section('heading', 'Support')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="headset"></i> Complaint & support</h1>
            <p class="muted">{{ $catalog['flow'] }}. {{ $catalog['note'] }}</p>
        </div>
    </div>
    <form class="card" method="GET">
        <div class="filters">
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach ($catalog['statuses'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    @foreach ($catalog['categories'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Filter</button>
        </div>
    </form>
    <section class="card">
        @forelse ($tickets as $row)
            <p>
                <a href="{{ route('support.show', $row['id']) }}">{{ $row['ticketId'] }}</a>
                · {{ $row['status'] }} · {{ $row['priority'] }} · {{ $row['category'] }}
                · {{ $row['user']['name'] }} · {{ $row['subject'] }}
            </p>
        @empty
            <p class="muted">No tickets in this territory.</p>
        @endforelse
    </section>
@endsection
