@extends('layouts.app')
@section('title', 'State & district heads')
@section('heading', 'State & district heads')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="user-cog"></i> State & district heads</h1>
            <p class="muted">People assigned to each territory. A State Head sees every district in that state. A District Head sees only their district.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @can('users.create')
                <a class="btn" href="{{ route('users.create') }}"><i data-lucide="user-plus"></i> Create user</a>
            @endcan
            @can('fleet.view')
                <a class="btn ghost" href="{{ route('fleet-owners.index') }}">Fleet owners</a>
            @endcan
        </div>
    </div>

    <section class="card">
        <div class="toolbar">
            <h2 style="margin:0">State heads</h2>
            <span class="muted">{{ count($states) }} states</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>State</th>
                        <th>Head</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($states as $row)
                    <tr>
                        <td><strong>{{ $row->name }}</strong>@if ($row->code)<div class="muted">{{ $row->code }}</div>@endif</td>
                        <td>{{ $row->head_name ?: 'Not assigned' }}</td>
                        <td>{{ $row->head_phone ?: '—' }}</td>
                        <td>{{ $row->head_email ?: '—' }}</td>
                        <td>{{ $row->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No states in your view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="toolbar">
            <h2 style="margin:0">District heads</h2>
            <span class="muted">{{ count($districts) }} districts</span>
        </div>
        <div class="filters" style="margin-bottom:12px">
            <input data-table-filter="district-heads-table" placeholder="Search district, head, phone">
        </div>
        <div class="table-wrap">
            <table class="data" id="district-heads-table">
                <thead>
                    <tr>
                        <th>District</th>
                        <th>State</th>
                        <th>Head</th>
                        <th>Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($districts as $row)
                    <tr>
                        <td><strong>{{ $row->name }}</strong></td>
                        <td>{{ $row->state_name }}</td>
                        <td>{{ $row->head_name ?: 'Not assigned' }}</td>
                        <td>{{ $row->head_phone ?: '—' }}</td>
                        <td>{{ $row->head_email ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No districts in your view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
