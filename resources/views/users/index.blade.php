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
            <div class="role-filter">
                @php
                    $pickedRoles = $filters['roles'] ?? [];
                    $roleLabel = $pickedRoles === [] ? 'All roles' : implode(', ', array_map(fn ($role) => str_replace('_', ' ', $role), $pickedRoles));
                @endphp
                <button type="button" class="role-filter-btn" id="role-filter-btn" aria-expanded="false">{{ $roleLabel }}</button>
                <div class="role-filter-panel" id="role-options" hidden>
                    <input type="search" id="role-search" placeholder="Search roles" autocomplete="off">
                    @foreach ($roles as $role)
                        <label data-label="{{ strtolower(str_replace('_', ' ', $role)) }}">
                            <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $pickedRoles, true))>
                            <span>{{ str_replace('_', ' ', $role) }}</span>
                        </label>
                    @endforeach
                    <p class="muted">Pick up to 2 roles.</p>
                </div>
            </div>
            <select name="status">
                <option value="">All status</option>
                @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @if (!empty($canSwitchGeo))
                @include('partials.geo-fields', [
                    'stateValue' => (string) ($filters['state_id'] ?? ''),
                    'districtValue' => (string) ($filters['district_id'] ?? ''),
                    'emptyState' => 'All states',
                    'emptyDistrict' => 'All districts',
                ])
            @endif
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
            var button = document.getElementById('role-filter-btn');
            if (!box || !search || !button) return;
            function label() {
                var names = [];
                box.querySelectorAll('input:checked').forEach(function (input) {
                    var text = input.parentElement ? input.parentElement.querySelector('span') : null;
                    names.push(text ? text.textContent : input.value);
                });
                button.textContent = names.length ? names.join(', ') : 'All roles';
            }
            button.addEventListener('click', function () {
                var open = box.hasAttribute('hidden');
                if (open) box.removeAttribute('hidden');
                else box.setAttribute('hidden', '');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) search.focus();
            });
            document.addEventListener('click', function (event) {
                if (!button.parentElement.contains(event.target)) {
                    box.setAttribute('hidden', '');
                    button.setAttribute('aria-expanded', 'false');
                }
            });
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
                label();
            });
        })();
    </script>
@endsection
