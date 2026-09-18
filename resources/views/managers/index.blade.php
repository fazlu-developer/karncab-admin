@extends('layouts.app')
@section('title', 'Managers')
@section('heading', 'Managers')
@section('content')
    <div class="hero">
        <div>
            <h1>Managers</h1>
            <p class="muted">Permission-based admin employees. The server enforces each ability; hiding a menu is not enough.</p>
        </div>
        @if(auth()->user()->isPrivilegedOperator())
            <a class="btn" href="{{ route('managers.create') }}">Add manager</a>
        @endif
    </div>
    <section class="card">
        <table class="data">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Scope</th><th></th></tr></thead>
            <tbody>
            @forelse ($managers as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->email }}</td>
                    <td>{{ $row->status }}</td>
                    <td>
                        @if ($row->state_id)
                            {{ \App\Platform\GeoCatalog::stateName((int) $row->state_id) ?: 'State' }}
                            @if ($row->district_id)
                                · {{ \App\Platform\GeoCatalog::districtName((int) $row->district_id) }}
                            @endif
                        @elseif ($row->district_id)
                            {{ \App\Platform\GeoCatalog::districtName((int) $row->district_id) }}
                        @else
                            Global
                        @endif
                    </td>
                    <td>@if(auth()->user()->isPrivilegedOperator())<a href="{{ route('managers.edit', $row) }}">Permissions</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No managers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
