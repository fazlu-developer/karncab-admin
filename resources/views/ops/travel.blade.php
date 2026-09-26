@extends('layouts.app')
@section('title', 'Travel Packages')
@section('heading', 'Travel Packages')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="map"></i> Travel &amp; tour packages</h1>
            <p class="muted">Publish packages with cover image, cab / traveller assignment, itinerary and live prices for the customer app Travel &amp; Tour service. After a booking is paid, assign a driver from Assign Drivers.</p>
        </div>
    </div>
    @can('travel.edit')
        <section class="card">
            <h2>Add package</h2>
            <form method="POST" action="{{ route('ops.travel.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required placeholder="Manali Tour"></div>
                    <div><label>From (origin)</label><input name="origin" value="Saharsa, Bihar"></div>
                    <div><label>Destination</label><input name="destination" placeholder="Manali, Himachal Pradesh"></div>
                    <div><label>Region</label><input name="region" placeholder="Himachal"></div>
                    <div>
                        <label>Category</label>
                        <select name="category">
                            @foreach (['OUTSTATION' => 'Outstation Tour', 'SIGHTSEEING' => 'Local Sightseeing', 'WEEKEND' => 'Weekend Getaway', 'PILGRIMAGE' => 'Pilgrimage', 'CORPORATE' => 'Corporate Travel', 'PACKAGES' => 'Travel Packages'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Assign vehicle</label>
                        <select name="vehicle_category">
                            <option value="SEDAN">Sedan / Private cab</option>
                            <option value="SUV">SUV</option>
                            <option value="TRAVELLER">Traveller (12 seater)</option>
                        </select>
                    </div>
                    <div><label>Price / person (₹)</label><input name="price_rupees" type="number" min="0" step="1" required></div>
                    <div><label>Nights</label><input name="nights" type="number" min="0" value="3"></div>
                    <div><label>Duration label</label><input name="duration_label" placeholder="3N / 4D"></div>
                    <div><label>Km included</label><input name="km_included" type="number" min="0" value="900"></div>
                    <div><label>Min travellers</label><input name="min_pax" type="number" min="1" value="2"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="PUBLISHED">PUBLISHED</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="ARCHIVED">ARCHIVED</option>
                        </select>
                    </div>
                    <div><label>Cover image</label><input type="file" name="image" accept="image/*"></div>
                    <div><label><input type="checkbox" name="popular" value="1"> Popular</label></div>
                </div>
                <div class="filters" style="margin-top:12px">
                    <div style="grid-column:1/-1"><label>Highlights (one per line)</label><textarea name="highlights" rows="3" placeholder="Visit Rohtang Pass"></textarea></div>
                    <div style="grid-column:1/-1"><label>Inclusions</label><textarea name="inclusions" rows="2" placeholder="Hotel stay, private cab, breakfast"></textarea></div>
                    <div style="grid-column:1/-1"><label>Exclusions</label><textarea name="exclusions" rows="2"></textarea></div>
                    <div style="grid-column:1/-1"><label>Itinerary (Day | Title | Detail, one per line)</label><textarea name="itinerary" rows="4" placeholder="Day 1 | Saharsa → Manali | Overnight journey"></textarea></div>
                </div>
                <button class="btn" type="submit" style="margin-top:12px">Save package</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Packages</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Image</th><th>Title</th><th>Route</th><th>Vehicle</th><th>Price</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($packages as $row)
                    @php
                        $itineraryLines = '';
                        $decoded = is_string($row->itinerary ?? null) ? json_decode($row->itinerary, true) : [];
                        if (is_array($decoded)) {
                            $itineraryLines = collect($decoded)->map(fn ($item) => trim(($item['day'] ?? '').' | '.($item['title'] ?? '').' | '.($item['detail'] ?? ''), ' |'))->implode("\n");
                        }
                        $highlights = is_string($row->highlights ?? null) ? json_decode($row->highlights, true) : [];
                        $highlightText = is_array($highlights) ? implode("\n", $highlights) : ($row->highlights ?? '');
                    @endphp
                    <tr>
                        <td>
                            @if (!empty($row->image_url))
                                <img src="{{ $row->image_url }}" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:8px">
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->title ?? $row->name ?? '—' }}<br><span class="muted">{{ $row->category ?? '' }} {{ !empty($row->popular) ? '· Popular' : '' }}</span></td>
                        <td>{{ $row->origin ?? 'Saharsa' }} → {{ $row->destination ?? '—' }}</td>
                        <td>{{ $row->vehicle_label ?? $row->vehicle_category ?? '—' }}</td>
                        <td>₹{{ number_format(((int) ($row->price_paise ?? 0)) / 100, 0) }}</td>
                        <td>{{ $row->status ?? '—' }}</td>
                        <td>
                            @can('travel.edit')
                                <form method="POST" action="{{ route('ops.travel.update', $row->id) }}" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <div class="filters">
                                        <input name="title" value="{{ $row->title ?? $row->name }}" required>
                                        <input name="origin" value="{{ $row->origin ?? '' }}" placeholder="From">
                                        <input name="destination" value="{{ $row->destination ?? '' }}" placeholder="To">
                                        <input name="region" value="{{ $row->region ?? '' }}" placeholder="Region">
                                        <select name="category">
                                            @foreach (['OUTSTATION','SIGHTSEEING','WEEKEND','PILGRIMAGE','CORPORATE','PACKAGES'] as $cat)
                                                <option value="{{ $cat }}" @selected(($row->category ?? '') === $cat)>{{ $cat }}</option>
                                            @endforeach
                                        </select>
                                        <select name="vehicle_category">
                                            @foreach (['SEDAN' => 'Sedan', 'SUV' => 'SUV', 'TRAVELLER' => 'Traveller'] as $key => $label)
                                                <option value="{{ $key }}" @selected(($row->vehicle_category ?? '') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="price_rupees" type="number" min="0" value="{{ (int) round(((int) ($row->price_paise ?? 0)) / 100) }}" style="max-width:100px">
                                        <input name="nights" type="number" min="0" value="{{ (int) ($row->nights ?? 0) }}" style="max-width:80px">
                                        <input name="duration_label" value="{{ $row->duration_label ?? '' }}" placeholder="3N / 4D">
                                        <input name="km_included" type="number" min="0" value="{{ (int) ($row->km_included ?? 0) }}">
                                        <input name="min_pax" type="number" min="1" value="{{ (int) ($row->min_pax ?? 2) }}">
                                        <select name="status">
                                            @foreach (['DRAFT','PUBLISHED','ARCHIVED'] as $st)
                                                <option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <input type="file" name="image" accept="image/*">
                                        <label><input type="checkbox" name="popular" value="1" @checked(!empty($row->popular))> Popular</label>
                                    </div>
                                    <textarea name="highlights" rows="2" placeholder="Highlights">{{ $highlightText }}</textarea>
                                    <textarea name="inclusions" rows="2">{{ $row->inclusions ?? '' }}</textarea>
                                    <textarea name="exclusions" rows="2">{{ $row->exclusions ?? '' }}</textarea>
                                    <textarea name="itinerary" rows="3">{{ $itineraryLines }}</textarea>
                                    <button class="btn ghost" type="submit">Save</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No travel packages yet. Add one above to show it in the customer app.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>Recent travel bookings</h2>
        <p class="muted">Paid tours also appear under <a href="{{ route('ops.assign-drivers') }}?product=TRAVEL">Assign Drivers → Travel &amp; Tour</a> so ops can assign a cab and driver.</p>
        <table class="data">
            <thead><tr><th>ID</th><th>Ref</th><th>Status</th><th>When</th><th>Guests</th><th>Fare</th></tr></thead>
            <tbody>
            @forelse ($bookings as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->public_ref ?? '—' }}</td>
                    <td>{{ $row->status ?? '—' }}</td>
                    <td>{{ $row->travel_date ?? '—' }}</td>
                    <td>{{ $row->guests ?? '—' }}</td>
                    <td>₹{{ number_format(((int) ($row->quote_paise ?? 0)) / 100, 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No travel bookings yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
