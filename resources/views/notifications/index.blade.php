@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="bell"></i> Notification system</h1>
            <p class="muted">{{ $catalog['architecture'] }} {{ $catalog['note'] }}</p>
        </div>
    </div>
    <section class="card">
        <h2>Channels</h2>
        <p>{{ implode(' · ', $catalog['channels']) }}</p>
        <h2>Events</h2>
        <p>{{ implode(' · ', $catalog['events']) }}</p>
    </section>
    @can('platform.admin')
        <section class="card">
            <h2>Broadcast</h2>
            <form method="POST" action="{{ route('ops.notify') }}">
                @csrf
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Body</label><textarea name="body" rows="3" required></textarea></div>
                <div class="field">
                    <label>Audience</label>
                    <select name="audience">
                        <option value="customer">Customers</option>
                        <option value="driver">Drivers</option>
                        <option value="ops">Operations</option>
                    </select>
                </div>
                <button class="btn" type="submit">Send via NotificationService</button>
            </form>
        </section>
        <section class="card">
            <h2>Dispatch event</h2>
            <form method="POST" action="{{ route('notifications.dispatch') }}">
                @csrf
                <div class="filters">
                    <div><label>Platform user ID</label><input name="user_id" type="number" required></div>
                    <div>
                        <label>Event</label>
                        <select name="event">
                            @foreach ($catalog['events'] as $event)
                                <option value="{{ $event }}">{{ $event }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Ref</label><input name="ref"></div>
                </div>
                <button class="btn ghost" type="submit">Dispatch</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Delivery log</h2>
        @forelse ($deliveries as $row)
            <p>{{ $row['createdAt'] }} · {{ $row['event'] }} · {{ $row['channel'] }} · {{ $row['status'] }} · {{ $row['title'] }}</p>
        @empty
            <p class="muted">No deliveries yet.</p>
        @endforelse
    </section>
@endsection
