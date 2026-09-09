@extends('layouts.app')

@section('title', 'Live fleet map')
@section('heading', 'Live fleet map')

@section('content')
    <div class="hero">
        <div>
            <h1>Live fleet map</h1>
            <p class="muted">Vehicles with a last known location in {{ $state['stateName'] }}.</p>
        </div>
    </div>
    <div id="state-map" class="map-canvas"></div>
    <section class="card">
        <table class="data">
            <thead><tr><th>Vehicle</th><th>Status</th><th>Lat</th><th>Lng</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['registrationNo'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['lat'] }}</td>
                    <td>{{ $row['lng'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No live positions in this state.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const points = @json($rows);
        const map = L.map('state-map').setView(points[0] ? [points[0].lat, points[0].lng] : [25.6, 85.1], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        points.forEach((p) => L.marker([p.lat, p.lng]).addTo(map).bindPopup(p.registrationNo || ('#' + p.id)));
    </script>
@endsection
