@extends('layouts.app')
@section('title', 'Districts')
@section('heading', 'Districts')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="map-pinned"></i> Service districts</h1>
            <p class="muted">Districts sit under a state. Drivers, fares and bookings use this list for KarnaRide coverage.</p>
        </div>
    </div>
    @can('district.create')
        <section class="card">
            <h2>Add district</h2>
            <form class="filters district-create" method="POST" action="{{ route('organization.districts.store') }}" enctype="multipart/form-data">
                @csrf
                <div>
                    <label>State</label>
                    <select name="state_id" required>
                        <option value="">Select state</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}" @selected((string) ($filters['state_id'] ?? '') === (string) $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>District name</label><input name="name" required placeholder="Patna"></div>
                <div><label>Code</label><input name="code" placeholder="PAT" maxlength="16"></div>
                <div><label>Photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </div>
                <button class="btn" type="submit">Add district</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <form class="filters district-filters" method="GET" action="{{ route('organization.districts') }}">
            <div>
                <label for="district-q">Search</label>
                <input id="district-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, code, or state">
            </div>
            <div>
                <label for="district-state">State</label>
                <select id="district-state" name="state_id">
                    <option value="">All states</option>
                    @foreach ($states as $state)
                        <option value="{{ $state->id }}" @selected((string) ($filters['state_id'] ?? '') === (string) $state->id)>{{ $state->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="district-status">Status</label>
                <select id="district-status" name="status">
                    <option value="">All statuses</option>
                    <option value="ACTIVE" @selected(($filters['status'] ?? '') === 'ACTIVE')>ACTIVE</option>
                    <option value="INACTIVE" @selected(($filters['status'] ?? '') === 'INACTIVE')>INACTIVE</option>
                </select>
            </div>
            <button class="btn" type="submit"><i data-lucide="search"></i> Search</button>
            @if (($filters['q'] ?? '') !== '' || ($filters['state_id'] ?? '') !== '' || ($filters['status'] ?? '') !== '')
                <a class="btn ghost" href="{{ route('organization.districts') }}">Clear</a>
            @endif
        </form>
        <p class="muted district-meta">
            @if ($districts->total() === 0)
                No districts match this search.
            @else
                Showing {{ $districts->firstItem() }}–{{ $districts->lastItem() }} of {{ $districts->total() }}
            @endif
        </p>
        <div class="district-list">
            @forelse ($districts as $row)
                <article class="district-item">
                    <div class="district-row">
                        @if (!empty($row->photo_url))
                            <img src="{{ $row->photo_url }}" alt="">
                        @else
                            <div class="district-photo">—</div>
                        @endif
                        <div class="district-name">
                            <strong>{{ $row->name }}</strong>
                            <span class="muted">{{ $row->state_name }} · {{ $row->code ?: 'No code' }} · #{{ $row->id }}</span>
                        </div>
                        <span class="district-status">
                            <span class="pill {{ ($row->status ?? 'ACTIVE') === 'ACTIVE' ? 'ok' : 'muted' }}">{{ $row->status ?? 'ACTIVE' }}</span>
                        </span>
                    </div>
                    @can('district.update')
                        <details class="district-edit">
                            <summary>Edit</summary>
                            <form class="district-edit-form" method="POST" action="{{ route('organization.districts.update', $row->id) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label>State</label>
                                    <select name="state_id" required>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" @selected((int) $row->state_id === (int) $state->id)>{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>District name</label>
                                    <input name="name" value="{{ $row->name }}" required>
                                </div>
                                <div>
                                    <label>Code</label>
                                    <input name="code" value="{{ $row->code ?? '' }}" maxlength="16">
                                </div>
                                <div>
                                    <label>Photo</label>
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div>
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="ACTIVE" @selected(($row->status ?? 'ACTIVE') === 'ACTIVE')>ACTIVE</option>
                                        <option value="INACTIVE" @selected(($row->status ?? '') === 'INACTIVE')>INACTIVE</option>
                                    </select>
                                </div>
                                <button class="btn" type="submit">Save</button>
                            </form>
                        </details>
                    @endcan
                </article>
            @empty
                <p class="muted">No districts in scope. Add one after the parent state exists.</p>
            @endforelse
        </div>
        <div class="pager">{{ $districts->links('pagination.admin') }}</div>
    </section>
@endsection
