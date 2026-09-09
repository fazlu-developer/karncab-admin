@extends('layouts.app')

@section('title', 'Safety & SOS')
@section('heading', 'Safety')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="shield-alert"></i> Safety, support & SOS</h1>
            <p class="muted">{{ $catalog['note'] }}</p>
        </div>
    </div>
    <section class="card">
        <h2>Open incidents</h2>
        @forelse ($incidents as $row)
            <p>
                <a href="{{ route('safety.show', $row['id']) }}">{{ $row['incidentId'] }}</a>
                · {{ $row['type'] }} · {{ $row['status'] }}
                · {{ $row['user']['firstName'] }}
                @if($row['bookingRef']) · {{ $row['bookingRef'] }}@endif
                @if($row['location']['mapsUrl']) · <a href="{{ $row['location']['mapsUrl'] }}">map</a>@endif
            </p>
        @empty
            <p class="muted">No incidents in this territory.</p>
        @endforelse
    </section>
@endsection
