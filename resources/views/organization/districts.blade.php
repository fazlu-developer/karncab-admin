@extends('layouts.app')
@section('title', 'Districts')
@section('heading', 'Districts')
@section('content')
    <section class="card">
        <table class="data">
            <thead><tr><th>ID</th><th>District</th><th>State</th><th>Code</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($districts as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->state_name }}</td>
                    <td>{{ $row->code ?? '—' }}</td>
                    <td>{{ $row->status ?? 'ACTIVE' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No districts in scope.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
