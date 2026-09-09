@extends('layouts.app')

@section('title', $ticket['ticketId'])
@section('heading', 'Ticket')

@section('content')
    <p><a href="{{ route('support.index') }}"><i data-lucide="arrow-left"></i> Tickets</a></p>
    <section class="card">
        <h1>{{ $ticket['ticketId'] }}</h1>
        <p>{{ $ticket['status'] }} · {{ $ticket['priority'] }} · {{ $ticket['category'] }}</p>
        <p>User: {{ $ticket['user']['name'] }} ({{ $ticket['user']['id'] }})</p>
        <p>Booking: {{ $ticket['booking']['publicRef'] ?? 'none' }}</p>
        <p>Subject: {{ $ticket['subject'] }}</p>
        <p>{{ $ticket['description'] }}</p>
        <p>Assigned: {{ $ticket['assignedAgent']['name'] ?? '—' }}</p>
        <p>Opened {{ $ticket['createdAt'] }} · Closed {{ $ticket['closedAt'] ?? '—' }}</p>
        @if ($ticket['resolution'])
            <p>Resolution: {{ $ticket['resolution'] }}</p>
        @endif
    </section>
    @can('safety.edit')
        <section class="card">
            <h2>Workflow</h2>
            @if ($ticket['status'] === 'open')
                <form method="POST" action="{{ route('support.assign', $ticket['id']) }}">@csrf<button class="btn" type="submit">Assign to me</button></form>
            @endif
            @foreach ($ticket['nextStatuses'] as $next)
                <form method="POST" action="{{ route('support.transition', $ticket['id']) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $next }}">
                    @if ($next === 'resolved')
                        <div><label>Resolution</label><input name="resolution" required></div>
                    @endif
                    <button class="btn ghost" type="submit">Move to {{ $next }}</button>
                </form>
            @endforeach
            <form method="POST" action="{{ route('support.reply', $ticket['id']) }}">
                @csrf
                <div><label>Reply</label><input name="body" required></div>
                <button class="btn ghost" type="submit">Send reply</button>
            </form>
            <form method="POST" action="{{ route('support.attach', $ticket['id']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" required>
                <button class="btn ghost" type="submit">Attach</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>History</h2>
        @forelse ($ticket['history'] as $item)
            <p>
                {{ $item['createdAt'] }} ·
                @if (($item['kind'] ?? '') === 'event')
                    {{ $item['action'] }} {{ $item['fromStatus'] }} → {{ $item['toStatus'] }} {{ $item['note'] }}
                @elseif (($item['kind'] ?? '') === 'attachment')
                    file {{ $item['name'] }}
                @else
                    {{ !empty($item['fromStaff']) ? 'Agent' : 'User' }}: {{ $item['body'] ?? '' }}
                @endif
            </p>
        @empty
            <p class="muted">No history yet.</p>
        @endforelse
    </section>
@endsection
