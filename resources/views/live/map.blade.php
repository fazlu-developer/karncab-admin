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
        <p class="muted">Click a row to focus that vehicle on the map. Bike, auto and car icons match the registered type.</p>
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
        const qFilter = @json($q ?? '');
        const familyFilter = @json($family ?? '');
        const lockedVehicle = @json((int) ($vehicleId ?? 0));
        let focusId = lockedVehicle;
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

        function iconSvg(family, selected) {
            const ring = selected ? '#b45309' : 'transparent';
            if (family === 'BIKE') {
                return `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 64 64">
                    <circle cx="32" cy="32" r="30" fill="#fff7ed" stroke="${ring}" stroke-width="4"/>
                    <circle cx="18" cy="46" r="8" fill="#111" stroke="#f59e0b" stroke-width="3"/>
                    <circle cx="48" cy="46" r="8" fill="#111" stroke="#f59e0b" stroke-width="3"/>
                    <path d="M18 46 L30 26 h12 L48 46" stroke="#111" stroke-width="3" fill="none"/>
                    <path d="M30 26 L38 14 h10" stroke="#111" stroke-width="3" fill="none"/>
                    <circle cx="38" cy="14" r="3.5" fill="#f59e0b"/>
                </svg>`;
            }
            if (family === 'AUTO') {
                return `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 64 64">
                    <circle cx="32" cy="32" r="30" fill="#fffbeb" stroke="${ring}" stroke-width="4"/>
                    <path d="M14 42 h36 v-10 l-10-12 H24 L14 32 z" fill="#facc15" stroke="#111" stroke-width="2"/>
                    <path d="M24 20 h16 v10 H24 z" fill="#eab308"/>
                    <circle cx="22" cy="46" r="6" fill="#111"/><circle cx="44" cy="46" r="6" fill="#111"/>
                </svg>`;
            }
            return `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 64 64">
                <circle cx="32" cy="32" r="30" fill="#eff6ff" stroke="${ring}" stroke-width="4"/>
                <path d="M12 38 h40 v8 H12 z" fill="#2563eb"/>
                <path d="M18 38 l6-12 h16 l6 12" fill="#1d4ed8"/>
                <circle cx="22" cy="48" r="5" fill="#111"/><circle cx="42" cy="48" r="5" fill="#111"/>
            </svg>`;
        }

        function iconUrl(family, selected) {
            return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(iconSvg(family, selected));
        }

        function leafletIcon(family, selected) {
            return L.icon({
                iconUrl: iconUrl(family, selected),
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
                `Booking: ${booking ? esc(booking.publicRef) + ' (' + esc(booking.status) + ')' : '—'}<br>` +
                (loc ? `${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}${loc.stale ? ' (stale)' : ''}` : 'No fix');
        }

        function initLeaflet(center) {
            map = L.map('live-map').setView(center, 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        }

        function initGoogle(center) {
            map = new google.maps.Map(document.getElementById('live-map'), { center: { lat: center[0], lng: center[1] }, zoom: 8 });
        }

        function googleIcon(family, selected) {
            const size = selected ? 48 : 40;
            return {
                url: iconUrl(family, selected),
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
                    markers[id] = new google.maps.Marker({ map, position: { lat: loc.lat, lng: loc.lng }, icon: googleIcon(family, selected) });
                    markers[id].info = new google.maps.InfoWindow();
                    markers[id].addListener('click', () => {
                        focusVehicle(Number(row.vehicleId), false);
                        markers[id].info.open({ map, anchor: markers[id] });
                    });
                }
                markers[id].setPosition({ lat: loc.lat, lng: loc.lng });
                markers[id].setIcon(googleIcon(family, selected));
                markers[id].setZIndex(selected ? 999 : 1);
                markers[id].info.setContent(popupHtml(row));
            } else {
                if (!markers[id]) {
                    markers[id] = L.marker([loc.lat, loc.lng], { icon: leafletIcon(family, selected), zIndexOffset: selected ? 999 : 0 }).addTo(map);
                    markers[id].on('click', () => focusVehicle(Number(row.vehicleId), false));
                }
                markers[id].setLatLng([loc.lat, loc.lng]);
                markers[id].setIcon(leafletIcon(family, selected));
                markers[id].setZIndexOffset(selected ? 999 : 0);
                markers[id].bindPopup(popupHtml(row));
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
                    <td>${row.currentBooking ? esc(row.currentBooking.publicRef) : '—'}</td>
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
            if (follow && focusId) {
                const row = lastRows.find((item) => Number(item.vehicleId) === Number(focusId));
                if (row && row.currentLocation) panToRow(row);
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
        applyRows(seed, lockedVehicle > 0);
        setInterval(refresh, pollMs);
    </script>
@endpush
