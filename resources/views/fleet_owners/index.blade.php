@extends('layouts.app')
@section('title', 'Fleet Owners')
@section('heading', 'Fleet Owners / Operators')
@section('content')
    <div class="hero">
        <div>
            <h1>Fleet Owners</h1>
            <p class="muted">Operator means Fleet Owner. A district may have many fleet owners.</p>
        </div>
        @can('fleet.manage')
            <a class="btn" href="{{ route('fleet-owners.create') }}">Add Fleet Owner</a>
        @endcan
    </div>
    <section class="card">
        <table class="data">
            <thead><tr><th>Business</th><th>Owner</th><th>Email</th><th>State</th><th>District</th><th>Franchise</th></tr></thead>
            <tbody>
            @forelse ($owners as $row)
                <tr>
                    <td>{{ $row->trade_name }}</td>
                    <td>{{ $row->owner_name }}</td>
                    <td>{{ $row->email }}</td>
                    <td>{{ $row->state_name ?? '—' }}</td>
                    <td>{{ $row->district_name ?? '—' }}</td>
                    <td>{{ $row->franchise_id ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No fleet owners in scope.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
