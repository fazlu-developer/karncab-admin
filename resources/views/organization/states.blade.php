@extends('layouts.app')
@section('title', 'States')
@section('heading', 'States')
@section('content')
    <section class="card">
        <table class="data">
            <thead><tr><th>ID</th><th>Name</th></tr></thead>
            <tbody>
            @forelse ($states as $state)
                <tr><td>{{ $state->id }}</td><td>{{ $state->name }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No states seeded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
