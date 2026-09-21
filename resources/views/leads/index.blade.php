@extends('layouts.app')

@section('title', 'Website enquiries')
@section('heading', 'Website enquiries')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="inbox"></i> Website enquiries</h1>
            <p class="muted">Contact and product forms from the public website. Each row is one submitted enquiry.</p>
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
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>When</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>District</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($leads as $row)
                <tr>
                    <td><a href="{{ route('leads.show', $row['id']) }}">#{{ $row['id'] }}</a></td>
                    <td>{{ $row['createdAt'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['phone'] }}</td>
                    <td>{{ $row['email'] ?: '—' }}</td>
                    <td>{{ $row['district'] ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['message'], 80) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No website enquiries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
