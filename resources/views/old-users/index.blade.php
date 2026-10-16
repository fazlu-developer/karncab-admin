@extends('layouts.app')

@section('title', 'Old users')
@section('heading', 'Old users')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="history"></i> Old website users</h1>
            <p class="muted">
                Source: <code>{{ $source ?: 'karnacab_website' }}</code> <code>users</code>
                @if ($sources)
                    ({{ collect($sources)->map(fn ($count, $db) => $db.' · '.$count)->implode(', ') }})
                @endif
                · Role 3 = customer, role 2 = driver. Tag = already on the new KarnaRide app or not.
            </p>
        </div>
        <form method="POST" action="{{ route('old-users.import') }}">
            @csrf
            <button class="btn" type="submit"><i data-lucide="download"></i> Import from old DB</button>
        </form>
    </div>

    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif
    @if (!empty($purgedTest))
        <p class="ok">Removed {{ $purgedTest }} test / dummy numbers from old users.</p>
    @endif

    <div class="kpis">
        <a class="kpi" href="{{ route('old-users.index') }}"><div class="label">All old users</div><b>{{ $counts['all'] }}</b></a>
        <a class="kpi" href="{{ route('old-users.index', ['role' => 'CUSTOMER']) }}"><div class="label">Customers (role 3)</div><b>{{ $counts['customer'] }}</b></a>
        <a class="kpi" href="{{ route('old-users.index', ['role' => 'DRIVER']) }}"><div class="label">Drivers (role 2)</div><b>{{ $counts['driver'] }}</b></a>
        <a class="kpi" href="{{ route('old-users.index', ['tag' => 'registered']) }}"><div class="label">Registered on new app</div><b>{{ $counts['registered'] }}</b></a>
        <a class="kpi" href="{{ route('old-users.index', ['tag' => 'pending']) }}"><div class="label">Not registered</div><b>{{ $counts['pending'] }}</b></a>
    </div>

    <section class="card">
        <form class="filters" method="GET" action="{{ route('old-users.index') }}">
            <input name="q" value="{{ $filters['q'] }}" placeholder="Search name, phone, email, city, old ID">
            <select name="role">
                <option value="">All old roles</option>
                <option value="CUSTOMER" @selected($filters['role'] === 'CUSTOMER')>Customer (role 3)</option>
                <option value="DRIVER" @selected($filters['role'] === 'DRIVER')>Driver (role 2)</option>
                <option value="ADMIN" @selected($filters['role'] === 'ADMIN')>Admin (role 1)</option>
                <option value="OTHER" @selected($filters['role'] === 'OTHER')>Other role</option>
            </select>
            <select name="tag">
                <option value="">All tags</option>
                <option value="registered" @selected($filters['tag'] === 'registered')>Registered</option>
                <option value="pending" @selected($filters['tag'] === 'pending')>Not registered</option>
            </select>
            <select name="per_page">
                <option value="25" @selected($filters['per_page'] === 25)>25 / page</option>
                <option value="50" @selected($filters['per_page'] === 50)>50 / page</option>
                <option value="100" @selected($filters['per_page'] === 100)>100 / page</option>
            </select>
            <button class="btn ghost" type="submit"><i data-lucide="search"></i> Search</button>
            <a class="btn ghost" href="{{ route('old-users.index') }}">Reset</a>
        </form>
        <form method="POST" action="{{ route('old-users.import') }}" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px">
            @csrf
            <input type="file" name="dump" accept=".csv,.txt,.sql" required>
            <button class="btn ghost" type="submit"><i data-lucide="upload"></i> Upload phpMyAdmin CSV / SQL</button>
            <span class="muted">If phpMyAdmin shows 1,852 users on live <code>karnacab_website</code>, export that <code>users</code> table and upload it here.</span>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table class="data">
                <thead>
                    <tr>
                        <th>Old ID</th>
                        <th>Name</th>
                        <th>Number</th>
                        <th>Email</th>
                        <th>Old type</th>
                        <th>Place</th>
                        <th>New platform</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['sourceId'] }}</td>
                        <td>
                            <strong>{{ $row['name'] }}</strong>
                            @if ($row['gender'])
                                <div class="muted">{{ $row['gender'] }}</div>
                            @endif
                        </td>
                        <td>{{ $row['phone'] ?: '—' }}</td>
                        <td>{{ $row['email'] ?: '—' }}</td>
                        <td>
                            <span class="pill {{ $row['roleId'] === 2 ? 'warn' : ($row['roleId'] === 3 ? 'ok' : 'muted') }}">
                                {{ $row['oldRole'] }} · role {{ $row['roleId'] }}
                            </span>
                        </td>
                        <td>{{ implode(' · ', array_filter([$row['city'], $row['district'], $row['state']])) ?: '—' }}</td>
                        <td>
                            @if ($row['registered'])
                                <span class="pill ok">Registered</span>
                                <div class="muted">as {{ $row['platformRole'] }}{{ $row['platformName'] ? ' · '.$row['platformName'] : '' }}</div>
                            @else
                                <span class="pill bad">Not registered</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No old users match this search. Import from DB or upload the live users CSV.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:16px">
            <p class="muted" style="margin:0">
                @if ($rows->total())
                    Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}
                @else
                    Showing 0 of 0
                @endif
            </p>
            {{ $rows->links('pagination.admin') }}
        </div>
    </section>
@endsection
