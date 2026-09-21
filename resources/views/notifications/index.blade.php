@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="bell"></i> Notifications</h1>
            <p class="muted">One place to send a broadcast or test a booking event. Apps receive in-app + push; OTP stays SMS/email only.</p>
        </div>
    </div>
    <section class="card">
        <h2>How it works</h2>
        <ol>
            <li>Pick an audience and write a title + message to broadcast now.</li>
            <li>Or pick a customer/driver user ID and an event (ride started, payment, KYC) to send the same template the apps use.</li>
            <li>The delivery log below is what actually went out — channel, status, and title.</li>
        </ol>
        <p class="muted">Channels: {{ implode(', ', $catalog['channels']) }}</p>
    </section>
    <section class="card">
        <h2>Event templates</h2>
        <table class="data">
            <thead><tr><th>Event</th><th>Customer sees</th><th>Channels</th></tr></thead>
            <tbody>
            @foreach (($catalog['templates'] ?? []) as $event => $tpl)
                <tr>
                    <td><code>{{ $event }}</code></td>
                    <td><strong>{{ $tpl['title'] }}</strong><div class="muted">{{ $tpl['body'] }}</div></td>
                    <td>{{ implode(', ', $tpl['channels'] ?? []) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @can('platform.admin')
        <section class="card">
            <h2>Broadcast to an audience</h2>
            <form method="POST" action="{{ route('ops.notify') }}">
                @csrf
                <div class="field"><label>Title</label><input name="title" required placeholder="Festival offer"></div>
                <div class="field"><label>Message</label><textarea name="body" rows="3" required placeholder="What should customers or drivers read?"></textarea></div>
                <div class="field">
                    <label>Who receives this</label>
                    <select name="audience">
                        <option value="customer">Customers</option>
                        <option value="driver">Drivers</option>
                        <option value="ops">Operations staff</option>
                    </select>
                </div>
                <button class="btn" type="submit">Send broadcast</button>
            </form>
        </section>
        <section class="card">
            <h2>Send one event to a user</h2>
            <form method="POST" action="{{ route('notifications.dispatch') }}">
                @csrf
                <div class="filters">
                    <div><label>Platform user ID</label><input name="user_id" type="number" required placeholder="From Users list"></div>
                    <div>
                        <label>Event</label>
                        <select name="event">
                            @foreach ($catalog['events'] as $event)
                                <option value="{{ $event }}">{{ $event }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Booking / ref</label><input name="ref" placeholder="KC123"></div>
                </div>
                <button class="btn ghost" type="submit">Send test</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Expired driver documents</h2>
        @forelse ($expiredDocuments ?? [] as $row)
            <p>
                <a href="{{ route('drivers.show', $row['driverId']) }}">{{ $row['driver'] }}</a>
                · {{ $row['phone'] }} · {{ $row['type'] }} · {{ $row['status'] }}
                @if (!empty($row['expiresAt']))
                    · expires {{ $row['expiresAt'] }}
                @endif
            </p>
        @empty
            <p class="muted">No expired driver documents right now.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Delivery log</h2>
        <table class="data">
            <thead><tr><th>When</th><th>Event</th><th>Channel</th><th>Status</th><th>Title</th></tr></thead>
            <tbody>
            @forelse ($deliveries as $row)
                <tr>
                    <td>{{ $row['createdAt'] ?? $row['created_at'] ?? '—' }}</td>
                    <td>{{ $row['event'] ?? '—' }}</td>
                    <td>{{ $row['channel'] ?? '—' }}</td>
                    <td>{{ $row['status'] ?? '—' }}</td>
                    <td>{{ $row['title'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No deliveries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
