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

    <form class="filters" method="GET" action="{{ route('organization.heads') }}" style="margin-bottom:14px">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search state, district, head, phone, or email">
        <button class="btn ghost" type="submit">Search</button>
        @if (($filters['q'] ?? '') !== '')
            <a class="btn ghost" href="{{ route('organization.heads') }}">Reset</a>
        @endif
    </form>

    <div class="kc-tabs" id="head-tabs" data-kc-tabs data-kc-open="states">
        <div class="kc-tabbar" role="tablist">
            <button type="button" class="kc-tab on" data-kc-tab="states">State heads ({{ $states->total() }})</button>
            <button type="button" class="kc-tab" data-kc-tab="districts">District heads ({{ $districts->total() }})</button>
        </div>
        <div class="kc-panel" data-kc-panel="states">
            <section class="card">
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
                            <tr><td colspan="5" class="muted">No states match this search.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:14px">{{ $states->links('pagination.admin') }}</div>
            </section>
        </div>
        <div class="kc-panel" data-kc-panel="districts" hidden>
            <section class="card">
                <div class="table-wrap">
                    <table class="data">
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
                            <tr><td colspan="5" class="muted">No districts match this search.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:14px">{{ $districts->links('pagination.admin') }}</div>
            </section>
        </div>
    </div>
@endsection
