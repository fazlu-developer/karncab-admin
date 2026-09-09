@extends('layouts.app')

@section('title', 'Live fleet map')
@section('heading', 'Live fleet map')

@section('content')
    <div class="hero">
        <div>
            <h1>Live fleet map</h1>
            <p class="muted">
                Scope is enforced on the server:
                {{ $snapshot['scope']['unrestricted'] ? 'all territories' : $snapshot['scope']['role'] }}.
                Live GPS lives in cache ({{ $snapshot['realtime']['ttlSeconds'] }}s TTL), not on booking rows.
            </p>
        </div>
    </div>
    <div class="kpis">
        @foreach ($snapshot['statuses'] as $key => $label)
            <div class="kpi">
                <div class="label">{{ $label }}</div>
                <b>{{ $snapshot['counts'][$key] ?? 0 }}</b>
            </div>
        @endforeach
    </div>
    <form class="filters" method="GET" style="margin:12px 0">
        <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach ($snapshot['statuses'] as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div id="live-map" class="map-canvas" style="height:560px"></div>
    <section class="card">
        <div class="table-wrap">
            <table class="data" id="live-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Updated</th>
                        <th>Booking</th>
                        <th>Trip</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($snapshot['vehicles'] as $row)
                    <tr>
                        <td>{{ $row['vehicleNumber'] }}</td>
                        <td>{{ $row['driverName'] ?: '—' }}</td>
                        <td>{{ $row['vehicleType'] }}</td>
                        <td>{{ $row['statusLabel'] }}</td>
                        <td>
                            @if ($row['currentLocation'])
                                {{ number_format($row['currentLocation']['lat'], 5) }}, {{ number_format($row['currentLocation']['lng'], 5) }}
                                {{ $row['currentLocation']['stale'] ? '(stale)' : '' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['lastUpdate'] ?: '—' }}</td>
                        <td>{{ $row['currentBooking']['publicRef'] ?? '—' }}</td>
                        <td>{{ $row['tripStatus'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No vehicles in this live scope.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    @if ($googleKey)
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleKey }}"></script>
    @else
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @endif
    <script>
        const pollMs = {{ (int) $snapshot['realtime']['pollMs'] }};
        const useGoogle = {{ $googleKey ? 'true' : 'false' }};
        const endpoint = @json(url('/api/v1/live/map'));
        const statusFilter = @json($status);
        let map, markers = {};

        function popupHtml(row) {
            const loc = row.currentLocation;
            const booking = row.currentBooking;
            return `<strong>${row.vehicleNumber || ''}</strong><br>` +
                `Driver: ${row.driverName || '—'}<br>` +
                `Type: ${row.vehicleType || '—'}<br>` +
                `Status: ${row.statusLabel || row.driverStatus}<br>` +
                `Updated: ${row.lastUpdate || '—'}<br>` +
                `Booking: ${booking ? booking.publicRef + ' (' + booking.status + ')' : '—'}<br>` +
                (loc ? `${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}${loc.stale ? ' (stale)' : ''}` : 'No fix');
        }

        function initLeaflet(center) {
            map = L.map('live-map').setView(center, 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        }

        function initGoogle(center) {
            map = new google.maps.Map(document.getElementById('live-map'), { center: { lat: center[0], lng: center[1] }, zoom: 8 });
        }

        function upsert(row) {
            const loc = row.currentLocation;
            if (!loc) return;
            const id = String(row.vehicleId);
            if (useGoogle) {
                if (!markers[id]) {
                    markers[id] = new google.maps.Marker({ map, position: { lat: loc.lat, lng: loc.lng } });
                    markers[id].info = new google.maps.InfoWindow();
                    markers[id].addListener('click', () => markers[id].info.open({ map, anchor: markers[id] }));
                }
                markers[id].setPosition({ lat: loc.lat, lng: loc.lng });
                markers[id].info.setContent(popupHtml(row));
            } else {
                if (!markers[id]) {
                    markers[id] = L.marker([loc.lat, loc.lng]).addTo(map);
                }
                markers[id].setLatLng([loc.lat, loc.lng]).bindPopup(popupHtml(row));
            }
        }

        function renderTable(vehicles) {
            const body = document.querySelector('#live-table tbody');
            if (!vehicles.length) {
                body.innerHTML = '<tr><td colspan="8" class="muted">No vehicles in this live scope.</td></tr>';
                return;
            }
            body.innerHTML = vehicles.map((row) => {
                const loc = row.currentLocation;
                return `<tr>
                    <td>${row.vehicleNumber || ''}</td>
                    <td>${row.driverName || '—'}</td>
                    <td>${row.vehicleType || ''}</td>
                    <td>${row.statusLabel || ''}</td>
                    <td>${loc ? loc.lat.toFixed(5) + ', ' + loc.lng.toFixed(5) + (loc.stale ? ' (stale)' : '') : '—'}</td>
                    <td>${row.lastUpdate || '—'}</td>
                    <td>${row.currentBooking ? row.currentBooking.publicRef : '—'}</td>
                    <td>${row.tripStatus || ''}</td>
                </tr>`;
            }).join('');
        }

        async function refresh() {
            const url = new URL(endpoint, window.location.origin);
            if (statusFilter) url.searchParams.set('status', statusFilter);
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            renderTable(data.vehicles || []);
            (data.vehicles || []).forEach(upsert);
        }

        const seed = @json($snapshot['vehicles']);
        const first = seed.find((row) => row.currentLocation);
        const center = first ? [first.currentLocation.lat, first.currentLocation.lng] : [25.61, 85.14];
        if (useGoogle && window.google) {
            initGoogle(center);
        } else if (window.L) {
            initLeaflet(center);
        }
        seed.forEach(upsert);
        setInterval(refresh, pollMs);
    </script>
@endpush
