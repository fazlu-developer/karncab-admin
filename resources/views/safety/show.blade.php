@extends('layouts.app')

@section('title', $incident['incidentId'])
@section('heading', 'SOS')

@section('content')
    <p><a href="{{ route('safety.index') }}"><i data-lucide="arrow-left"></i> Safety</a></p>
    <section class="card">
        <h1>{{ $incident['incidentId'] }}</h1>
        <p>{{ $incident['type'] }} · {{ $incident['status'] }} · {{ $incident['timestamp'] }}</p>
        <p>User: {{ $incident['user']['firstName'] }} · last-4 {{ $incident['user']['phoneLast4'] ?? '—' }}</p>
        <p>Booking: {{ $incident['bookingRef'] ?? 'none' }}</p>
        <p>Location: {{ $incident['location']['text'] ?? '—' }}
            @if($incident['location']['mapsUrl'])
                · <a href="{{ $incident['location']['mapsUrl'] }}">{{ $incident['location']['lat'] }}, {{ $incident['location']['lng'] }}</a>
            @endif
        </p>
        <p>Emergency contact: {{ $incident['emergencyContact']['name'] ?? '—' }} · {{ $incident['emergencyContact']['phone'] ?? '—' }}</p>
        <p>{{ $incident['description'] }}</p>
    </section>
    @can('safety.edit')
        <section class="card">
            <h2>Investigation</h2>
            <form method="POST" action="{{ route('safety.review', $incident['id']) }}">
                @csrf @method('PATCH')
                <div class="filters">
                    <div>
                        <label>Status</label>
                        <select name="status">
                            @foreach ($catalog['statuses'] as $status)
                                <option value="{{ $status }}" @selected($incident['status'] === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Internal note</label><input name="admin_note" value="{{ $incident['adminNote'] }}"></div>
                </div>
                <button class="btn" type="submit">Save</button>
            </form>
        </section>
    @endcan
@endsection
