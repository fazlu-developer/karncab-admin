@extends('layouts.app')

@section('title', 'Fare Management')
@section('heading', 'Fare Management')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="banknote"></i> Fare Management</h1>
            <p class="muted">Module key: <code>fare</code>. Live service states: {{ implode(', ', $liveStates) }}. Customer and driver apps quote from these <code>fare_rules</code> rows. Other states show Coming soon.</p>
        </div>
    </div>

    <section class="card">
        <h2>Add fare rule</h2>
        <form method="POST" action="{{ route('fare.store') }}">
            @csrf
            <div class="filters">
                <div>
                    <label>Product</label>
                    <select name="product" required>
                        @foreach ($products as $product)
                            <option value="{{ $product }}">{{ $product }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Vehicle</label>
                    <select name="category" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>District (blank = Bihar &amp; Delhi default)</label>
                    <select name="district_id">
                        <option value="">All live districts</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district->id }}">{{ $district->state_name }} · {{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Min km</label><input name="min_km" type="number" step="0.1" value="2" required></div>
                <div><label>Included km</label><input name="included_km" type="number" step="0.1" value="2" required></div>
                <div><label>₹ / km</label><input name="per_km_rupees" type="number" step="0.01" value="12" required></div>
                <div><label>₹ extra km</label><input name="extra_km_rupees" type="number" step="0.01" value="14" required></div>
                <div><label>₹ waiting / min</label><input name="waiting_per_min_rupees" type="number" step="0.01" value="1" required></div>
                <div><label>Night %</label><input name="night_percent" type="number" value="20" required></div>
                <div><label>GST %</label><input name="gst_percent" type="number" value="5" required></div>
                <div><label>Rental hours</label><input name="rental_hours" type="number" placeholder="for RENTAL"></div>
            </div>
            <label><input type="checkbox" name="active" value="1" checked> Active</label>
            <button class="btn" type="submit">Save fare rule</button>
        </form>
    </section>

    <section class="card">
        <h2>Current rules</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Vehicle</th>
                        <th>Area</th>
                        <th>Min / included km</th>
                        <th>₹/km</th>
                        <th>Extra ₹/km</th>
                        <th>Wait ₹/min</th>
                        <th>Night</th>
                        <th>GST</th>
                        <th>On</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rules as $row)
                    <tr>
                        <td colspan="11">
                            <form method="POST" action="{{ route('fare.update', $row->id) }}" class="filters" style="align-items:end">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label>Product</label>
                                    <select name="product">
                                        @foreach ($products as $product)
                                            <option value="{{ $product }}" @selected($row->product === $product)>{{ $product }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>Vehicle</label>
                                    <select name="category">
                                        @foreach ($categories as $category)
                                            <option value="{{ $category }}" @selected($row->category === $category)>{{ $category }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>District</label>
                                    <select name="district_id">
                                        <option value="">All live districts</option>
                                        @foreach ($districts as $district)
                                            <option value="{{ $district->id }}" @selected((int) $row->district_id === (int) $district->id)>{{ $district->state_name }} · {{ $district->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><label>Min km</label><input name="min_km" type="number" step="0.1" value="{{ $row->min_km }}"></div>
                                <div><label>Included km</label><input name="included_km" type="number" step="0.1" value="{{ $row->included_km }}"></div>
                                <div><label>₹/km</label><input name="per_km_rupees" type="number" step="0.01" value="{{ $row->per_km_paise / 100 }}"></div>
                                <div><label>Extra ₹/km</label><input name="extra_km_rupees" type="number" step="0.01" value="{{ $row->extra_km_paise / 100 }}"></div>
                                <div><label>Wait ₹/min</label><input name="waiting_per_min_rupees" type="number" step="0.01" value="{{ $row->waiting_paise_per_min / 100 }}"></div>
                                <div><label>Night %</label><input name="night_percent" type="number" value="{{ $row->night_percent }}"></div>
                                <div><label>GST %</label><input name="gst_percent" type="number" value="{{ $row->gst_percent }}"></div>
                                <div><label>Rental hours</label><input name="rental_hours" type="number" value="{{ $row->rental_hours }}"></div>
                                <div>
                                    <label>Active</label>
                                    <label><input type="checkbox" name="active" value="1" @checked($row->active)> On</label>
                                </div>
                                <button class="btn" type="submit">Update</button>
                            </form>
                            <p class="muted">#{{ $row->id }} · {{ $row->state_name ?: 'Default' }} {{ $row->district_name }}</p>
                        </td>
                    </tr>
                @empty
                    <tr><td class="muted">No fare rules yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
