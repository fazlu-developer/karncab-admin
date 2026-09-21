@extends('layouts.app')
@section('title', 'Districts')
@section('heading', 'Districts')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="map-pinned"></i> Service districts</h1>
            <p class="muted">Districts sit under a state. Drivers, fares and bookings use this list for KarnaCab coverage.</p>
        </div>
    </div>
    @can('district.create')
        <section class="card">
            <h2>Add district</h2>
            <form method="POST" action="{{ route('organization.districts.store') }}">
                @csrf
                <div class="filters">
                    <div>
                        <label>State</label>
                        <select name="state_id" required>
                            <option value="">Select state</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>District name</label><input name="name" required placeholder="Patna"></div>
                    <div><label>Code</label><input name="code" placeholder="PAT"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                    <button class="btn" type="submit">Add district</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <table class="data">
            <thead><tr><th>ID</th><th>District</th><th>State</th><th>Code</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($districts as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->state_name }}</td>
                    <td>{{ $row->code ?? '—' }}</td>
                    <td>{{ $row->status ?? 'ACTIVE' }}</td>
                    <td>
                        @can('district.update')
                            <form method="POST" action="{{ route('organization.districts.update', $row->id) }}" class="filters" style="margin:0">
                                @csrf
                                @method('PUT')
                                <select name="state_id" required>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" @selected((int) $row->state_id === (int) $state->id)>{{ $state->name }}</option>
                                    @endforeach
                                </select>
                                <input name="name" value="{{ $row->name }}" required>
                                <input name="code" value="{{ $row->code ?? '' }}" style="max-width:90px">
                                <select name="status">
                                    <option value="ACTIVE" @selected(($row->status ?? 'ACTIVE') === 'ACTIVE')>ACTIVE</option>
                                    <option value="INACTIVE" @selected(($row->status ?? '') === 'INACTIVE')>INACTIVE</option>
                                </select>
                                <button class="btn ghost" type="submit">Save</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No districts in scope. Add one after the parent state exists.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
