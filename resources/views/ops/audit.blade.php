@extends('layouts.app')
@section('title', 'Audit Logs')
@section('heading', 'Audit Logs')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="scroll-text"></i> Audit logs</h1>
            <p class="muted">Operator actions stored in platform_audit_events — organization, branding, bookings and access changes.</p>
        </div>
    </div>
    <form class="card" method="GET">
        <div class="filters">
            <div><label>Domain</label><input name="domain" value="{{ $query['domain'] ?? '' }}" placeholder="organization"></div>
            <div><label>Search</label><input name="q" value="{{ $query['q'] ?? '' }}" placeholder="Action or entity"></div>
            <button class="btn" type="submit">Filter</button>
        </div>
    </form>
    <section class="card">
        <table class="data">
            <thead><tr><th>When</th><th>Actor</th><th>Domain</th><th>Action</th><th>Entity</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->created_at }}</td>
                    <td>{{ $row->actor_user_id ?? '—' }} · {{ $row->actor_role ?? '' }}</td>
                    <td>{{ $row->domain }}</td>
                    <td>{{ $row->action }}</td>
                    <td>{{ $row->entity_type }} #{{ $row->entity_id }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No audit events yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
