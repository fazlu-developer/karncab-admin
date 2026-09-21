@extends('layouts.app')
@section('title', 'States')
@section('heading', 'States')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="globe"></i> Service states</h1>
            <p class="muted">Add every state where KarnaCab operates. Districts are attached to a state on the next screen.</p>
        </div>
    </div>
    @can('state.create')
        <section class="card">
            <h2>Add state</h2>
            <form method="POST" action="{{ route('organization.states.store') }}">
                @csrf
                <div class="filters">
                    <div><label>State name</label><input name="name" required placeholder="Bihar"></div>
                    <div><label>Code</label><input name="code" placeholder="BR" maxlength="12"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                    <button class="btn" type="submit">Add state</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <table class="data">
            <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($states as $state)
                <tr>
                    <td>{{ $state->id }}</td>
                    <td>{{ $state->name }}</td>
                    <td>{{ $state->code ?? '—' }}</td>
                    <td>{{ $state->status ?? 'ACTIVE' }}</td>
                    <td>
                        @can('state.update')
                            <form method="POST" action="{{ route('organization.states.update', $state->id) }}" class="filters" style="margin:0">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $state->name }}" required>
                                <input name="code" value="{{ $state->code ?? '' }}" placeholder="Code" style="max-width:90px">
                                <select name="status">
                                    <option value="ACTIVE" @selected(($state->status ?? 'ACTIVE') === 'ACTIVE')>ACTIVE</option>
                                    <option value="INACTIVE" @selected(($state->status ?? '') === 'INACTIVE')>INACTIVE</option>
                                </select>
                                <button class="btn ghost" type="submit">Save</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No states yet. Add the first service state above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
