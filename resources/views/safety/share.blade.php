<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KarnaCab trip {{ $share['publicRef'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="console" style="display:block;padding:2rem">
    <section class="card">
        <h1>Live trip {{ $share['publicRef'] }}</h1>
        <p>Passenger {{ $share['rider']['firstName'] }} · {{ $share['status'] }}</p>
        @if ($share['driver'])
            <p>Driver {{ $share['driver']['firstName'] }} · {{ $share['driver']['kycVerified'] ? 'Verified' : 'Unverified' }} · {{ $share['driver']['rating'] }}★</p>
        @endif
        @if ($share['vehicle'])
            <p>{{ $share['vehicle']['category'] }} · {{ $share['vehicle']['plateHint'] }}</p>
        @endif
        @if ($share['lastLocation']['mapsUrl'])
            <p><a href="{{ $share['lastLocation']['mapsUrl'] }}">Open map</a></p>
        @endif
        <p class="muted">OTP and full phone numbers are never shown on this page.</p>
    </section>
</div>
</body>
</html>
