@extends('layouts.app')

@section('title', 'Users')
@section('heading', 'Users')

@section('content')
    <div class="hero">
        <div>
            <h1>Users</h1>
            <p class="muted">Search, filter and manage every platform account.</p>
        </div>
        @can('users.create')
            <a class="btn" href="{{ route('users.create') }}"><i data-lucide="plus"></i> Add user</a>
        @endcan
    </div>
    <section class="card">
        <form class="filters" method="GET">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, email, phone">
            <select name="role">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">All status</option>
                @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="btn ghost" type="submit"><i data-lucide="search"></i> Search</button>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table class="data">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($users as $row)
                    <tr>
                        <td>
                            <div class="person">
                                <div class="avatar">{{ strtoupper(substr($row->name, 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $row->name }}</strong>
                                    <div class="muted">{{ $row->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="pill muted"><i data-lucide="shield"></i> {{ $row->role }}</span></td>
                        <td>
                            <span class="pill {{ $row->status === 'ACTIVE' ? 'ok' : ($row->status === 'PENDING' ? 'warn' : 'bad') }}">{{ $row->status }}</span>
                        </td>
                        <td>{{ $row->phone ?: '—' }}</td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('users.show', $row) }}" title="View"><i data-lucide="eye"></i></a>
                            @can('users.edit')
                                <a class="icon-btn" href="{{ route('users.edit', $row) }}" title="Edit"><i data-lucide="pencil"></i></a>
                            @endcan
                            @can('users.delete')
                                <form method="POST" action="{{ route('users.destroy', $row) }}" onsubmit="return confirm('Delete this user?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" title="Delete"><i data-lucide="trash-2"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No users match those filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager">{{ $users->links('pagination.admin') }}</div>
    </section>
@endsection
