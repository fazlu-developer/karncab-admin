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
            <div style="min-width:240px">
                <input type="search" id="role-search" placeholder="Search roles" autocomplete="off">
                <div id="role-options" style="max-height:148px;overflow:auto;border:1px solid #d7d7d7;border-radius:8px;padding:8px;margin-top:6px;background:#fff">
                    @foreach ($roles as $role)
                        <label data-label="{{ strtolower(str_replace('_', ' ', $role)) }}" style="display:flex;gap:8px;align-items:center;padding:4px 0">
                            <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $filters['roles'] ?? [], true))>
                            {{ str_replace('_', ' ', $role) }}
                        </label>
                    @endforeach
                </div>
                <p class="muted" style="margin:4px 0 0">Pick up to 2 roles.</p>
            </div>
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
                        <th>State</th>
                        <th>District</th>
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
                        <td>{{ $row->state_name ?: '—' }}</td>
                        <td>{{ $row->district_name ?: '—' }}</td>
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
                                @if($row->role !== 'SUPER_ADMIN')
                                <form method="POST" action="{{ route('users.destroy', $row) }}" onsubmit="return confirm('Delete this user?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" title="Delete"><i data-lucide="trash-2"></i></button>
                                </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No users match those filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager">{{ $users->links('pagination.admin') }}</div>
    </section>
    <script>
        (function () {
            var box = document.getElementById('role-options');
            var search = document.getElementById('role-search');
            if (!box || !search) return;
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                box.querySelectorAll('label').forEach(function (label) {
                    var text = label.getAttribute('data-label') || '';
                    label.style.display = text.indexOf(q) >= 0 ? 'flex' : 'none';
                });
            });
            box.addEventListener('change', function (event) {
                var input = event.target;
                if (!input || input.type !== 'checkbox') return;
                if (box.querySelectorAll('input:checked').length > 2) input.checked = false;
            });
        })();
    </script>
@endsection
