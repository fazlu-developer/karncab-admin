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
        <div>
            <label>Vehicle</label>
            <input name="q" value="{{ $q ?? '' }}" placeholder="Number, driver, or ID">
        </div>
        <div>
            <label>Type</label>
            <select name="family">
                <option value="">All types</option>
                <option value="BIKE" @selected(($family ?? '') === 'BIKE')>Bike</option>
                <option value="AUTO" @selected(($family ?? '') === 'AUTO')>Auto</option>
                <option value="CAR" @selected(($family ?? '') === 'CAR')>Car</option>
            </select>
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All statuses</option>
                @foreach ($snapshot['statuses'] as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="vehicle" value="{{ (int) ($vehicleId ?? 0) }}">
        <button class="btn" type="submit">Track</button>
        <a class="btn ghost" href="{{ route('live.map') }}">Clear</a>
    </form>
    <div id="live-map" class="map-canvas" style="height:560px"></div>
    <section class="card">
            <p class="muted">Every vehicle with a location stays on the map together. The number on each pin is the vehicle. Click a row to zoom to that one.</p>
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
                    <tr data-vehicle-id="{{ $row['vehicleId'] }}" style="cursor:pointer">
                        <td>{{ $row['vehicleNumber'] }}</td>
                        <td>{{ $row['driverName'] ?: '—' }}</td>
                        <td>{{ $row['vehicleFamily'] ?? $row['vehicleType'] }}</td>
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
                        <td>
                            @if (!empty($row['currentBooking']['id']))
                                <a href="{{ route('ops.bookings.show', $row['currentBooking']['id']) }}">{{ $row['currentBooking']['publicRef'] ?? 'Open' }}</a>
                            @else
                                —
                            @endif
                        </td>
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
        const qFilter = @json($q ?? '');
        const familyFilter = @json($family ?? '');
        const lockedVehicle = @json((int) ($vehicleId ?? 0));
        const focusQuery = @json((int) request('focus', 0));
        const bookingBase = @json(url('/bookings'));
        let focusId = lockedVehicle || focusQuery;
        let map, markers = {}, lastRows = [];

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
        }

        function familyOf(row) {
            const family = String(row.vehicleFamily || '').toUpperCase();
            if (family === 'BIKE' || family === 'AUTO' || family === 'CAR') return family;
            const type = String(row.vehicleType || '').toUpperCase();
            if (type === 'BIKE') return 'BIKE';
            if (type === 'AUTO' || type === 'E_RICKSHAW') return 'AUTO';
            return 'CAR';
        }

        function iconSvg(family, selected, heading) {
            const ring = selected ? '#f5c518' : 'transparent';
            const turn = Number.isFinite(Number(heading)) ? Number(heading) : 0;
            let body;
            if (family === 'BIKE') {
                body = `<circle cx="32" cy="14" r="6" fill="#111"/><circle cx="32" cy="14" r="2.2" fill="#d0d4da"/>
                    <circle cx="32" cy="50" r="7" fill="#111"/><circle cx="32" cy="50" r="2.4" fill="#d0d4da"/>
                    <rect x="28" y="16" width="8" height="30" rx="4" fill="#1a1a1a"/>
                    <rect x="22" y="28" width="20" height="12" rx="4" fill="#111"/>`;
            } else if (family === 'AUTO') {
                body = `<rect x="16" y="22" width="32" height="34" rx="6" fill="#f5c518" stroke="#1a1a1a" stroke-width="1.5"/>
                    <rect x="18" y="10" width="28" height="22" rx="7" fill="#e6a100" stroke="#1a1a1a" stroke-width="1.5"/>
                    <rect x="22" y="14" width="20" height="10" rx="3" fill="#9fd4f0"/>
                    <rect x="12" y="28" width="6" height="10" rx="2" fill="#111"/><rect x="46" y="28" width="6" height="10" rx="2" fill="#111"/>
                    <rect x="12" y="44" width="6" height="10" rx="2" fill="#111"/><rect x="46" y="44" width="6" height="10" rx="2" fill="#111"/>`;
            } else {
                body = `<ellipse cx="32" cy="58" rx="16" ry="4" fill="rgba(0,0,0,.28)"/>
                    <rect x="8" y="16" width="7" height="12" rx="2.5" fill="#1a1a1a"/><rect x="49" y="16" width="7" height="12" rx="2.5" fill="#1a1a1a"/>
                    <rect x="8" y="40" width="7" height="12" rx="2.5" fill="#1a1a1a"/><rect x="49" y="40" width="7" height="12" rx="2.5" fill="#1a1a1a"/>
                    <circle cx="11.5" cy="22" r="1.6" fill="#d8d8d8"/><circle cx="52.5" cy="22" r="1.6" fill="#d8d8d8"/>
                    <circle cx="11.5" cy="46" r="1.6" fill="#d8d8d8"/><circle cx="52.5" cy="46" r="1.6" fill="#d8d8d8"/>
                    <rect x="16" y="8" width="32" height="50" rx="12" fill="#f7f8fa" stroke="#1b7a32" stroke-width="1.6"/>
                    <rect x="20" y="16" width="24" height="11" rx="3" fill="#6aa8cc"/>
                    <rect x="20" y="29" width="24" height="12" rx="3" fill="#e7eaee"/>
                    <rect x="20" y="43" width="24" height="7" rx="2" fill="#8ebcd4"/>
                    <rect x="20" y="10" width="8" height="3" rx="1" fill="#fff4c2"/><rect x="36" y="10" width="8" height="3" rx="1" fill="#fff4c2"/>
                    <rect x="20" y="52" width="8" height="3" rx="1" fill="#e10600"/><rect x="36" y="52" width="8" height="3" rx="1" fill="#e10600"/>
                    <rect x="12" y="24" width="5" height="4" rx="1.5" fill="#dfe3e8"/><rect x="47" y="24" width="5" height="4" rx="1.5" fill="#dfe3e8"/>`;
            }
            return `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 64 64">
                <circle cx="32" cy="32" r="31" fill="none" stroke="${ring}" stroke-width="3"/>
                <g transform="rotate(${turn} 32 32)">${body}</g>
            </svg>`;
        }

        function iconUrl(family, selected, heading) {
            return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(iconSvg(family, selected, heading));
        }

        function leafletIcon(family, selected, heading) {
            return L.icon({
                iconUrl: iconUrl(family, selected, heading),
                iconSize: selected ? [48, 48] : [40, 40],
                iconAnchor: selected ? [24, 24] : [20, 20],
                popupAnchor: [0, -18],
            });
        }

        function popupHtml(row) {
            const loc = row.currentLocation;
            const booking = row.currentBooking;
            return `<strong>${esc(row.vehicleNumber)}</strong><br>` +
                `Driver: ${esc(row.driverName || '—')}<br>` +
                `Type: ${esc(familyOf(row))} · ${esc(row.vehicleType || '—')}<br>` +
                `Status: ${esc(row.statusLabel || row.driverStatus)}<br>` +
                `Updated: ${esc(row.lastUpdate || '—')}<br>` +
                `Booking: ${booking ? '<a href="' + bookingBase + '/' + encodeURIComponent(booking.id) + '">' + esc(booking.publicRef) + '</a> (' + esc(booking.status) + ')' : '—'}<br>` +
                (loc ? `${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}${loc.stale ? ' (stale)' : ''}` : 'No fix');
        }

        function initLeaflet(center) {
            map = L.map('live-map').setView(center, 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        }

        function initGoogle(center) {
            map = new google.maps.Map(document.getElementById('live-map'), { center: { lat: center[0], lng: center[1] }, zoom: 8 });
        }

        function googleIcon(family, selected, heading) {
            const size = selected ? 48 : 40;
            return {
                url: iconUrl(family, selected, heading),
                scaledSize: new google.maps.Size(size, size),
                anchor: new google.maps.Point(size / 2, size / 2),
            };
        }

        function upsert(row) {
            const loc = row.currentLocation;
            if (!loc) return;
            const id = String(row.vehicleId);
            const selected = focusId > 0 && Number(row.vehicleId) === Number(focusId);
            const family = familyOf(row);
            if (useGoogle) {
                if (!markers[id]) {
                    markers[id] = new google.maps.Marker({ map, position: { lat: loc.lat, lng: loc.lng }, icon: googleIcon(family, selected, loc.heading) });
                    markers[id].info = new google.maps.InfoWindow();
                    markers[id].addListener('click', () => {
                        focusVehicle(Number(row.vehicleId), false);
                        markers[id].info.open({ map, anchor: markers[id] });
                    });
                }
                markers[id].setPosition({ lat: loc.lat, lng: loc.lng });
                markers[id].setIcon(googleIcon(family, selected, loc.heading));
                markers[id].setZIndex(selected ? 999 : 1);
                markers[id].info.setContent(popupHtml(row));
                markers[id].setLabel({
                    text: String(row.vehicleNumber || row.vehicleId).slice(0, 12),
                    color: '#102216',
                    fontSize: '11px',
                    fontWeight: '700',
                });
            } else {
                if (!markers[id]) {
                    markers[id] = L.marker([loc.lat, loc.lng], { icon: leafletIcon(family, selected, loc.heading), zIndexOffset: selected ? 999 : 0 }).addTo(map);
                    markers[id].on('click', () => focusVehicle(Number(row.vehicleId), false));
                }
                markers[id].setLatLng([loc.lat, loc.lng]);
                markers[id].setIcon(leafletIcon(family, selected, loc.heading));
                markers[id].setZIndexOffset(selected ? 999 : 0);
                markers[id].bindPopup(popupHtml(row));
                markers[id].bindTooltip(String(row.vehicleNumber || row.vehicleId), { permanent: true, direction: 'top', offset: [0, -16], className: 'map-pin-label' });
            }
        }

        function fitAll() {
            const points = lastRows.filter((row) => row.currentLocation);
            if (!points.length || !map) return;
            if (useGoogle) {
                const bounds = new google.maps.LatLngBounds();
                points.forEach((row) => bounds.extend({ lat: row.currentLocation.lat, lng: row.currentLocation.lng }));
                map.fitBounds(bounds);
            } else if (window.L) {
                map.fitBounds(points.map((row) => [row.currentLocation.lat, row.currentLocation.lng]), { padding: [36, 36] });
            }
        }

        function prune(ids) {
            Object.keys(markers).forEach((id) => {
                if (ids.has(id)) return;
                if (useGoogle) markers[id].setMap(null);
                else map.removeLayer(markers[id]);
                delete markers[id];
            });
        }

        function panToRow(row) {
            const loc = row && row.currentLocation;
            if (!loc || !map) return;
            if (useGoogle) {
                map.panTo({ lat: loc.lat, lng: loc.lng });
                map.setZoom(Math.max(map.getZoom(), 14));
                const marker = markers[String(row.vehicleId)];
                if (marker && marker.info) marker.info.open({ map, anchor: marker });
            } else {
                map.setView([loc.lat, loc.lng], Math.max(map.getZoom(), 14));
                const marker = markers[String(row.vehicleId)];
                if (marker) marker.openPopup();
            }
        }

        function focusVehicle(id, pan) {
            focusId = Number(id) || 0;
            const hidden = document.querySelector('input[name="vehicle"]');
            if (hidden) hidden.value = focusId || '';
            lastRows.forEach(upsert);
            if (pan) {
                const row = lastRows.find((item) => Number(item.vehicleId) === focusId);
                panToRow(row);
            }
        }

        function bindTableClicks() {
            document.querySelectorAll('#live-table tbody tr[data-vehicle-id]').forEach((tr) => {
                tr.addEventListener('click', () => focusVehicle(Number(tr.getAttribute('data-vehicle-id')), true));
            });
        }

        function renderTable(vehicles) {
            const body = document.querySelector('#live-table tbody');
            if (!vehicles.length) {
                body.innerHTML = '<tr><td colspan="8" class="muted">No vehicles in this live scope.</td></tr>';
                return;
            }
            body.innerHTML = vehicles.map((row) => {
                const loc = row.currentLocation;
                const selected = focusId > 0 && Number(row.vehicleId) === Number(focusId);
                return `<tr data-vehicle-id="${esc(row.vehicleId)}" style="cursor:pointer;${selected ? 'background:#FFF4CC' : ''}">
                    <td>${esc(row.vehicleNumber)}</td>
                    <td>${esc(row.driverName || '—')}</td>
                    <td>${esc(familyOf(row))}${row.vehicleType ? ' · ' + esc(row.vehicleType) : ''}</td>
                    <td>${esc(row.statusLabel || '')}</td>
                    <td>${loc ? loc.lat.toFixed(5) + ', ' + loc.lng.toFixed(5) + (loc.stale ? ' (stale)' : '') : '—'}</td>
                    <td>${esc(row.lastUpdate || '—')}</td>
                    <td>${row.currentBooking ? '<a href="' + bookingBase + '/' + encodeURIComponent(row.currentBooking.id) + '">' + esc(row.currentBooking.publicRef) + '</a>' : '—'}</td>
                    <td>${esc(row.tripStatus || '')}</td>
                </tr>`;
            }).join('');
            bindTableClicks();
        }

        function applyRows(vehicles, follow) {
            lastRows = vehicles || [];
            const ids = new Set();
            lastRows.forEach((row) => {
                if (row.currentLocation) ids.add(String(row.vehicleId));
                upsert(row);
            });
            prune(ids);
            renderTable(lastRows);
            if (focusId && follow) {
                const row = lastRows.find((item) => Number(item.vehicleId) === Number(focusId));
                if (row && row.currentLocation) panToRow(row);
            } else if (!focusId) {
                fitAll();
            }
        }

        async function refresh() {
            const url = new URL(endpoint, window.location.origin);
            if (statusFilter) url.searchParams.set('status', statusFilter);
            if (qFilter) url.searchParams.set('q', qFilter);
            if (familyFilter) url.searchParams.set('family', familyFilter);
            if (lockedVehicle) url.searchParams.set('vehicle', String(lockedVehicle));
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            applyRows(data.vehicles || [], lockedVehicle > 0);
        }

        const seed = @json($snapshot['vehicles']);
        const first = (focusId ? seed.find((row) => Number(row.vehicleId) === Number(focusId) && row.currentLocation) : null)
            || seed.find((row) => row.currentLocation);
        const center = first ? [first.currentLocation.lat, first.currentLocation.lng] : [25.61, 85.14];
        if (useGoogle && window.google) {
            initGoogle(center);
        } else if (window.L) {
            initLeaflet(center);
        }
        applyRows(seed, lockedVehicle > 0 || focusQuery > 0);
        setInterval(refresh, pollMs);
    </script>
@endpush
