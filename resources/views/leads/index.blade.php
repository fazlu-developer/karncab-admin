@extends('layouts.app')

@section('title', 'Website leads')
@section('heading', 'Website leads')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="inbox"></i> Website enquiries</h1>
            <p class="muted">Contact and product forms from karnacab.in. Each submission is emailed to ops and stored here.</p>
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
                <label>Type</label>
                <select name="type">
                    <option value="">All</option>
                    @foreach ($catalog['types'] as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Search</label>
                <input name="q" value="{{ request('q') }}" placeholder="Name, phone, email">
            </div>
            <button class="btn" type="submit">Filter</button>
        </div>
    </form>
    <section class="card">
        @forelse ($leads as $row)
            <p>
                <a href="{{ route('leads.show', $row['id']) }}">#{{ $row['id'] }}</a>
                · {{ $row['type'] }} · {{ $row['status'] }}
                · {{ $row['name'] }} · {{ $row['phone'] }}
                · {{ \Illuminate\Support\Str::limit($row['message'], 80) }}
            </p>
        @empty
            <p class="muted">No website leads yet.</p>
        @endforelse
    </section>
@endsection
