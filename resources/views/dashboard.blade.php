@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="hero">
        <div>
            <h1>Good {{ now()->format('A') === 'AM' ? 'morning' : 'afternoon' }}, {{ auth()->user()->name }}</h1>
            <p class="muted">Live snapshot from the platform database — users, drivers, rides and revenue.</p>
        </div>
        @can('users.create')
            <a class="btn" href="{{ route('users.create') }}"><i data-lucide="user-plus"></i> New user</a>
        @endcan
    </div>
    @livewire('api-health-badge')
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif
    <div class="kpis">
        @foreach ([
            ['totalUsers', 'Total users', 'users'],
            ['activeUsers', 'Using KarnaCab now', 'user-check'],
            ['totalDrivers', 'Drivers', 'id-card'],
            ['onlineDrivers', 'Online now', 'radio'],
            ['activeRides', 'Active rides', 'navigation'],
            ['completedRides', 'Completed', 'circle-check'],
            ['cancelledRides', 'Cancelled', 'circle-x'],
            ['todayRevenueRupees', "Today ₹", 'banknote'],
            ['monthlyRevenueRupees', 'Month ₹', 'wallet'],
            ['pendingKyc', 'Pending KYC', 'badge-alert'],
            ['pendingComplaints', 'Complaints', 'message-circle-warning'],
            ['activeAdvertisements', 'Ads live', 'megaphone'],
        ] as [$key, $label, $icon])
            <div class="kpi">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $kpis[$key] ?? 0 }}</b>
            </div>
        @endforeach
    </div>
    <section class="card" style="margin-bottom:18px">
        <div class="toolbar">
            <h3 style="margin:0">Customers using KarnaCab now</h3>
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
