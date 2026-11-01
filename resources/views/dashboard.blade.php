@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="hero">
        <div>
            <h1>Good {{ now()->format('A') === 'AM' ? 'morning' : 'afternoon' }}, {{ auth()->user()->name }}</h1>
            <p class="muted">Live snapshot from the platform database — users, drivers, rides and revenue.</p>
        </div>
        @can('platform.admin')
            @if (!empty($states) && $states->count())
                <form method="GET" class="filters">
                    <select name="stateId" onchange="this.form.submit()">
                        <option value="">All states</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}" @selected((int) ($selectedStateId ?? 0) === (int) $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <select name="districtId" onchange="this.form.submit()">
                        <option value="">All districts</option>
                        @foreach ($districts ?? [] as $district)
                            <option value="{{ $district->id }}" @selected((int) ($selectedDistrictId ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        @endcan
        @can('users.create')
            <a class="btn" href="{{ route('users.create') }}"><i data-lucide="user-plus"></i> New user</a>
        @endcan
    </div>
    @livewire('api-health-badge')
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif
    @php
        $territoryFilter = !empty($selectedDistrictId) ? 'district' : (!empty($selectedStateId) ? 'state' : null);
        $kpiTiles = [
            ['totalUsers', 'Total users', 'users', route('users.index')],
            ['activeUsers', 'Using KarnaRide now', 'user-check', route('users.index')],
            ['totalDrivers', 'Drivers', 'id-card', route('drivers.index')],
            ['onlineDrivers', 'Online now', 'radio', route('drivers.index', ['online' => 1])],
            ['activeRides', 'Active rides', 'navigation', route('ops.module', ['module' => 'bookings', 'status' => 'ACTIVE'])],
            ['completedRides', 'Completed', 'circle-check', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['cancelledRides', 'Cancelled', 'circle-x', route('ops.module', ['module' => 'bookings', 'status' => 'CANCELLED'])],
            ['todayRevenueRupees', 'Today ₹', 'banknote', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['monthlyRevenueRupees', 'Month ₹', 'wallet', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])],
            ['commissionEarnedRupees', $territoryFilter ? ucfirst($territoryFilter).' commission ₹' : 'Platform commission ₹', 'percent', route('wallets.index')],
            ['fleetSettlementRupees', 'Fleet share ₹', 'warehouse', route('fleet-owners.index')],
            ['platformKeepRupees', $territoryFilter ? 'Platform share ₹' : 'Platform keep ₹', 'landmark', route('wallets.index')],
            ['pendingKyc', 'Pending KYC', 'badge-alert', route('drivers.index', ['kyc' => 'pending'])],
            ['pendingComplaints', 'Complaints', 'message-circle-warning', route('support.index')],
            ...((auth()->user()?->isStateHead() || auth()->user()?->isDistrictHead()) ? [] : [['pendingLeads', 'Website enquiries', 'inbox', route('leads.index')]]),
            ['activeAdvertisements', 'Ads live', 'megaphone', route('ads.index')],
        ];
        if ($territoryFilter) {
            $kpiTiles[] = ['districtEarningRupees', ucfirst($territoryFilter).' earnings ₹', 'badge-percent', route('ops.module', ['module' => 'bookings', 'status' => 'COMPLETED'])];
        }
    @endphp
    <div class="kpis">
        @foreach ($kpiTiles as [$key, $label, $icon, $href])
            <a class="kpi" href="{{ $href }}">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </a>
        @endforeach
    </div>
    <section class="card" style="margin-bottom:18px">
        <h2>Needs a look</h2>
        <div class="kpis">
            <a class="kpi" href="{{ route('withdrawals.index') }}"><div class="label">Withdraw requests</div><b>{{ $pulse['withdraws'] ?? 0 }}</b></a>
            <a class="kpi" href="{{ route('ops.corporate-plans') }}"><div class="label">Corporate plans</div><b>{{ $pulse['corporate'] ?? 0 }}</b></a>
            <a class="kpi" href="{{ route('ops.bulk-bookings') }}"><div class="label">Bulk bookings</div><b>{{ $pulse['bulk'] ?? 0 }}</b></a>
            <a class="kpi" href="{{ route('ops.tour-bookings') }}"><div class="label">Travel tickets</div><b>{{ $pulse['tours'] ?? 0 }}</b></a>
        </div>
        @if (!empty($pulse['banners']))
            <h3>Live banners</h3>
            <div class="table-wrap" style="display:flex;gap:12px;flex-wrap:wrap">
                @foreach ($pulse['banners'] as $banner)
                    <figure style="margin:0;max-width:220px">
                        <img src="{{ $banner['url'] }}" alt="{{ $banner['title'] }}" style="width:220px;height:110px;object-fit:cover;border-radius:12px">
                        <figcaption class="muted">{{ $banner['title'] }} · {{ $banner['status'] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <p class="muted">No published banner yet. Upload one under Advertising. The preview uses admin.karnaride.in.</p>
        @endif
    </section>
    <section class="card" style="margin-bottom:18px">
        <div class="toolbar">
            <h3 style="margin:0">Customers using KarnaRide now</h3>
            <span class="muted">Seen in the last 5 minutes</span>
        </div>
        <table class="data">
            <tbody>
            @forelse ($liveCustomers ?? [] as $row)
                <tr>
                    <td>
                        <div class="person">
                            <div class="avatar">{{ strtoupper(substr($row['name'] ?? 'C', 0, 1)) }}</div>
                            <div>
                                <strong>{{ $row['name'] ?? 'Customer' }}</strong>
                                <div class="muted">{{ $row['phone'] }} · {{ $row['address'] ?: 'Location pending' }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="pill ok">Live</span></td>
                    <td class="muted">{{ $row['lastSeenAt'] }}</td>
                </tr>
            @empty
                <tr><td class="muted">No customers are using the app right now.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card" style="margin-bottom:18px">
        <div class="toolbar">
            <h3 style="margin:0">Live bookings</h3>
            <span class="muted">Searching and on-trip rides</span>
        </div>
        <div class="table-wrap live-bookings-wrap">
        <table class="data">
            <thead>
                <tr><th>Ref</th><th>Service</th><th>Status</th><th>Customer</th><th>Driver</th><th>Route</th></tr>
            </thead>
            <tbody>
            @forelse ($liveRides ?? [] as $ride)
                <tr>
                    <td>
                        <a href="{{ route('ops.bookings.show', $ride->id) }}">{{ $ride->public_ref ?: ('#'.$ride->id) }}</a>
                        <div><a href="{{ route('live.map', array_filter(['focus' => $ride->vehicle_id ?: null, 'q' => $ride->vehicle_reg ?: null])) }}">Track</a></div>
                    </td>
                    <td>{{ str_replace('_', ' ', $ride->product) }}</td>
                    <td><span class="pill ok">{{ str_replace('_', ' ', $ride->status) }}</span></td>
                    <td>{{ $ride->customer ?: '—' }}<div class="muted">{{ $ride->customer_phone }}</div></td>
                    <td>{{ $ride->driver_name ?: 'Waiting' }}</td>
                    <td class="cell-wrap muted">{{ $ride->pickup_text }} → {{ $ride->drop_text }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No live bookings right now.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:18px">
        <div class="toolbar">
            <h3 style="margin:0">Document expiry</h3>
            <a href="{{ route('notifications.index') }}">Notification centre</a>
        </div>
        @forelse ($expiringDocuments ?? [] as $doc)
            <p>
                <a href="{{ route('drivers.show', $doc->driver_id) }}">{{ $doc->name }}</a>
                · {{ $doc->phone }} · {{ $doc->type }} · {{ $doc->status }}
                @if ($doc->expires_at)
                    · expires {{ $doc->expires_at }}
                @endif
            </p>
        @empty
            <p class="muted">No driver documents are expired or due in the next 15 days.</p>
        @endforelse
    </section>
    <div class="grid-2">
        <section class="card">
            <div class="toolbar">
                <h3 style="margin:0">Latest users</h3>
                <a href="{{ route('users.index') }}">View all</a>
            </div>
            <table class="data">
                <tbody>
                @forelse ($recentUsers as $row)
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
                        <td><span class="pill muted">{{ $row->role }}</span></td>
                        <td><a class="icon-btn" href="{{ route('users.show', $row) }}"><i data-lucide="arrow-up-right"></i></a></td>
                    </tr>
                @empty
                    <tr><td class="muted">No users yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        <section class="card">
            <div class="toolbar">
                <h3 style="margin:0">Latest drivers</h3>
                <a href="{{ route('drivers.index') }}">View all</a>
            </div>
            <table class="data">
                <tbody>
                @forelse ($recentDrivers as $row)
                    <tr>
                        <td>
                            <div class="person">
                                <div class="avatar">{{ strtoupper(substr($row->user->name ?? 'D', 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $row->user->name ?? 'Driver' }}</strong>
                                    <div class="muted">{{ $row->kyc_status }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($row->online)
                                <span class="pill ok">Online</span>
                            @else
                                <span class="pill muted">Offline</span>
                            @endif
                        </td>
                        <td><a class="icon-btn" href="{{ route('drivers.show', $row) }}"><i data-lucide="arrow-up-right"></i></a></td>
                    </tr>
                @empty
                    <tr><td class="muted">No drivers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
