@extends('layouts.app')
@section('title', 'Service Management')
@section('heading', 'Service Management')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="settings-2"></i> Ride and parcel services</h1>
            <p class="muted">Bike, Auto, Mini, Sedan, SUV and Traveller start here. Add more categories when KarnaCab launches a new product.</p>
        </div>
    </div>
    <section class="card">
        <h2>Add service</h2>
        <form method="POST" action="{{ route('ops.services.store') }}">
            @csrf
            <div class="filters">
                <div><label>Title</label><input name="title" required placeholder="Sedan"></div>
                <div><label>Slug</label><input name="slug" placeholder="sedan"></div>
                <div><label>Subtitle</label><input name="subtitle" placeholder="Comfort sedan"></div>
                <div>
                    <label>Group</label>
                    <select name="service_group">
                        <option value="RIDE">RIDE</option>
                        <option value="PARCEL">PARCEL</option>
                    </select>
                </div>
                <div><label>App category key</label><input name="category_key" placeholder="SEDAN"></div>
                <div><label>Sort</label><input name="sort_order" type="number" value="0"></div>
                <label style="align-self:end"><input type="checkbox" name="active" value="1" checked> Active</label>
                <button class="btn" type="submit">Add</button>
            </div>
        </form>
    </section>
    <section class="card">
        <table class="data">
            <thead><tr><th>Title</th><th>Group</th><th>Key</th><th>Sort</th><th>Active</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->title }}<div class="muted">{{ $row->slug }}</div></td>
                    <td>{{ $row->service_group }}</td>
                    <td>{{ $row->category_key }}</td>
                    <td>{{ $row->sort_order }}</td>
                    <td>{{ $row->active ? 'Yes' : 'No' }}</td>
                    <td>
                        <form method="POST" action="{{ route('ops.services.update', $row->id) }}" class="filters" style="margin:0" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input name="title" value="{{ $row->title }}" required>
                            <input name="subtitle" value="{{ $row->subtitle }}" placeholder="Subtitle">
                            <select name="service_group">
                                <option value="RIDE" @selected($row->service_group === 'RIDE')>RIDE</option>
                                <option value="PARCEL" @selected($row->service_group === 'PARCEL')>PARCEL</option>
                            </select>
                            <input name="category_key" value="{{ $row->category_key }}" style="max-width:110px">
                            <input name="sort_order" type="number" value="{{ $row->sort_order }}" style="max-width:70px">
                            <label><input type="checkbox" name="active" value="1" @checked($row->active)> Active</label>
                            <input type="file" name="image" accept="image/*">
                            <button class="btn ghost" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No catalog services yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2>Home offers</h2>
        <p class="muted">These banners show on the customer app home and the View Offers screen.</p>
        <form method="POST" action="{{ route('ops.services.offers') }}" enctype="multipart/form-data" class="filters">
            @csrf
            <div><label>Title</label><input name="title" required placeholder="FLAT 10% OFF"></div>
            <div><label>Subtitle</label><input name="subtitle" placeholder="On First Ride"></div>
            <div><label>Code</label><input name="code" placeholder="KARNA10"></div>
            <div><label>Link</label><input name="link_url" placeholder="https://"></div>
            <div><label>Banner image</label><input type="file" name="image" accept="image/*"></div>
            <button class="btn" type="submit">Add offer</button>
        </form>
    </section>
@endsection
