@extends('layouts.app')
@section('title', 'Service Management')
@section('heading', 'Service Management')
@section('content')
    @php
        $photo = function (?string $path) {
            $path = trim((string) $path);
            if ($path === '') {
                return '';
            }
            return str_starts_with($path, 'http') ? $path : url($path);
        };
        $pickedFor = fn ($row) => \App\Http\Controllers\Ops\WorkspaceController::selectedVehicleKeys($row);
    @endphp
    <div class="hero">
        <div>
            <h1><i data-lucide="settings-2"></i> Services and vehicles</h1>
            <p class="muted">Customer home shows these 12 services. After a customer picks a service, the app lists only the vehicles you tick below. Drivers first pick Bike / Auto / Car / Loading, then a type. Optional photos: if you upload one the apps use it, otherwise the built-in vehicle picture is shown.</p>
        </div>
        <a class="btn ghost" href="{{ route('fare.index') }}"><i data-lucide="banknote"></i> Set fares</a>
    </div>

    <div class="catalog-steps">
        <div><b>1. Services</b><span>12 booking tiles on the customer app</span></div>
        <div><b>2. Categories</b><span>Bike, Auto, Car, Loading for driver signup</span></div>
        <div><b>3. Types</b><span>Mini, Scooty, Truck… shown after the category</span></div>
    </div>

    <section class="card">
        <h2>1. Customer services</h2>
        <p class="muted">Tick vehicles for each service, then Save. Fare Management uses the same list.</p>
        <div class="catalog-grid">
            @foreach ($rows as $row)
                @php $picked = $pickedFor($row); $src = $photo($row->image_url ?? ''); @endphp
                <article class="catalog-card">
                    <div class="catalog-thumb">
                        @if ($src)
                            <img src="{{ $src }}" alt="">
                        @else
                            <span>App default</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('ops.services.update', $row->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input name="title" value="{{ $row->title }}" required>
                        <input name="subtitle" value="{{ $row->subtitle }}" placeholder="Short line on the app">
                        <input type="hidden" name="service_group" value="{{ $row->service_group }}">
                        <input type="hidden" name="category_key" value="{{ data_get($row, 'category_key') }}">
                        <input type="hidden" name="seats" value="{{ data_get($row, 'seats', 4) }}">
                        <div class="catalog-meta">
                            <label>Sort <input name="sort_order" type="number" value="{{ $row->sort_order }}"></label>
                            <label class="inline"><input type="checkbox" name="active" value="1" @checked($row->active)> On app</label>
                        </div>
                        <label>Service photo (optional)<input type="file" name="image" accept="image/*"></label>
                        @if ($src)
                            <label class="inline"><input type="checkbox" name="remove_image" value="1"> Remove photo</label>
                        @endif
                        @foreach ($vehicleFamilies as $family)
                            <p class="catalog-family">{{ $family['label'] }}</p>
                            <div class="catalog-checks">
                                @foreach ($family['types'] as $type)
                                    <label class="inline"><input type="checkbox" name="vehicle_keys[]" value="{{ $type['key'] }}" @checked(in_array($type['key'], $picked, true))> {{ $type['label'] }}</label>
                                @endforeach
                            </div>
                        @endforeach
                        <div class="catalog-actions">
                            <button class="btn" type="submit">Save</button>
                        </div>
                    </form>
                    @if (! in_array(\App\Services\VehicleCatalogAdmin::fareProduct((string) data_get($row, 'category_key'), (string) $row->title, (string) $row->slug), $canonicalKeys, true))
                        <form method="POST" action="{{ route('ops.services.destroy', $row->id) }}" onsubmit="return confirm('Remove this service from the apps?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn ghost" type="submit">Remove extra row</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
        <details class="catalog-add">
            <summary>Add another service</summary>
            <form method="POST" action="{{ route('ops.services.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required placeholder="City Ride"></div>
                    <div><label>Slug</label><input name="slug" placeholder="city-ride"></div>
                    <div><label>Subtitle</label><input name="subtitle" placeholder="Local trips"></div>
                    <div>
                        <label>Group</label>
                        <select name="service_group">
                            <option value="RIDE">Ride</option>
                            <option value="PARCEL">Parcel</option>
                        </select>
                    </div>
                    <div><label>Booking key</label><input name="category_key" placeholder="LOCAL_CAB"></div>
                    <div><label>Photo</label><input type="file" name="image" accept="image/*"></div>
                    <label class="inline" style="align-self:end"><input type="checkbox" name="active" value="1" checked> On app</label>
                    <button class="btn" type="submit">Add service</button>
                </div>
                @foreach ($vehicleFamilies as $family)
                    <p class="catalog-family">{{ $family['label'] }}</p>
                    <div class="catalog-checks">
                        @foreach ($family['types'] as $type)
                            <label class="inline"><input type="checkbox" name="vehicle_keys[]" value="{{ $type['key'] }}"> {{ $type['label'] }}</label>
                        @endforeach
                    </div>
                @endforeach
            </form>
        </details>
    </section>

    <section class="card">
        <h2>2. Driver categories (Bike, Auto, Car, Loading)</h2>
        <p class="muted">Onboarding first screen. Photo is optional — the app uses its own Bike/Auto/Car/Loading picture if empty.</p>
        <div class="catalog-grid catalog-grid-4">
            @foreach ($vehicleFamilies as $family)
                @php $fsrc = $photo($family['image_url'] ?? ''); @endphp
                <article class="catalog-card">
                    <div class="catalog-thumb">
                        @if ($fsrc)
                            <img src="{{ $fsrc }}" alt="">
                        @else
                            <span>{{ $family['label'] }} default</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('ops.services.families.update', $family['key']) }}" enctype="multipart/form-data">
                        @csrf
                        <input name="family_label" value="{{ $family['label'] }}" required>
                        <label>Category photo (optional)<input type="file" name="image" accept="image/*"></label>
                        @if ($fsrc)
                            <label class="inline"><input type="checkbox" name="remove_image" value="1"> Remove photo</label>
                        @endif
                        <button class="btn" type="submit">Save category</button>
                    </form>
                    <p class="muted" style="margin:10px 0 0">Types: {{ collect($family['types'])->pluck('label')->join(', ') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>3. Vehicle types</h2>
        <p class="muted">Second onboarding screen and the customer quote list. Upload a photo per type, or leave empty for the app default (bike.png, sedan.png, …).</p>
        <form method="POST" action="{{ route('ops.services.vehicles.store') }}" class="filters" enctype="multipart/form-data">
            @csrf
            <div>
                <label>Category</label>
                <select name="family_key" id="vehicle-family-key" onchange="document.getElementById('new-family-fields').style.display=this.value==='__NEW__'?'flex':'none'">
                    @foreach ($vehicleFamilies as $family)
                        <option value="{{ $family['key'] }}">{{ $family['label'] }}</option>
                    @endforeach
                    <option value="__NEW__">+ New category</option>
                </select>
            </div>
            <div id="new-family-fields" style="display:none;gap:12px">
                <div><label>New category key</label><input name="new_family_key" placeholder="LOADING"></div>
                <div><label>New category name</label><input name="new_family_label" placeholder="Loading"></div>
            </div>
            <div><label>Type name</label><input name="label" required placeholder="Scooty"></div>
            <div><label>Type key</label><input name="key" placeholder="SCOOTY"></div>
            <div><label>Seats</label><input name="seats" type="number" min="0" max="60" value="4"></div>
            <div><label>Photo (optional)</label><input type="file" name="image" accept="image/*"></div>
            <label class="inline" style="align-self:end"><input type="checkbox" name="active" value="1" checked> Active</label>
            <button class="btn" type="submit">Add type</button>
        </form>
        @foreach ($vehicleFamilies as $family)
            <h3 style="margin:18px 0 8px">{{ $family['label'] }}</h3>
            <div class="catalog-grid catalog-grid-4">
                @foreach ($family['types'] as $type)
                    @php $tsrc = $photo($type['image_url'] ?? ''); @endphp
                    <article class="catalog-card">
                        <div class="catalog-thumb">
                            @if ($tsrc)
                                <img src="{{ $tsrc }}" alt="">
                            @else
                                <span>Default {{ $type['label'] }}</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('ops.services.vehicles.update', $type['id']) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="family_key" value="{{ $family['key'] }}">
                            <input type="hidden" name="family_label" value="{{ $family['label'] }}">
                            <input name="label" value="{{ $type['label'] }}" required>
                            <input name="key" value="{{ $type['key'] }}" required>
                            <div class="catalog-meta">
                                <label>Seats <input name="seats" type="number" min="0" value="{{ $type['seats'] }}"></label>
                                <label>Sort <input name="sort_order" type="number" value="{{ $type['sort_order'] }}"></label>
                                <label class="inline"><input type="checkbox" name="active" value="1" @checked($type['active'])> On</label>
                            </div>
                            <label>Type photo (optional)<input type="file" name="image" accept="image/*"></label>
                            @if ($tsrc)
                                <label class="inline"><input type="checkbox" name="remove_image" value="1"> Remove photo</label>
                            @endif
                            <div class="catalog-actions">
                                <button class="btn" type="submit">Save</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('ops.services.vehicles.destroy', $type['id']) }}" onsubmit="return confirm('Remove this type from onboarding and quotes?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn ghost" type="submit">Remove</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endforeach
    </section>

    <section class="card">
        <h2>Home offers</h2>
        <p class="muted">Banners on the customer home and View Offers screen.</p>
        <form method="POST" action="{{ route('ops.services.offers') }}" enctype="multipart/form-data" class="filters">
            @csrf
            <div><label>Title</label><input name="title" required placeholder="FLAT 10% OFF"></div>
            <div><label>Subtitle</label><input name="subtitle" placeholder="On First Ride"></div>
            <div><label>Code</label><input name="code" placeholder="KARNA10"></div>
            <div><label>Link</label><input name="link_url" placeholder="https://"></div>
            <div><label>Banner image</label><input type="file" name="image" accept="image/*"></div>
            <button class="btn" type="submit">Add offer</button>
        </form>
        @if (!empty($offers))
            <table class="data" style="margin-top:16px">
                <tbody>
                @foreach ($offers as $index => $offer)
                    <tr>
                        <td>
                            @if (!empty($offer['imageUrl']))
                                <img src="{{ $offer['imageUrl'] }}" alt="" style="max-height:48px">
                            @endif
                        </td>
                        <td>{{ $offer['title'] ?? '' }}</td>
                        <td>{{ $offer['code'] ?? '' }}</td>
                        <td>
                            <form method="POST" action="{{ route('ops.services.offers.destroy', $index) }}" style="margin:0">
                                @csrf
                                @method('DELETE')
                                <button class="btn ghost" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
