@extends('layouts.app')

@section('title', 'Drivers')
@section('heading', 'Drivers')

@section('content')
    <div class="hero">
        <div>
            <h1>Drivers</h1>
            <p class="muted">Search by name, phone, license or KYC. Open a driver to review the full profile and attachments.</p>
        </div>
        @can('drivers.create')
            <a class="btn" href="{{ route('drivers.create') }}"><i data-lucide="plus"></i> Add driver</a>
        @endcan
    </div>
    <section class="card">
        <form class="filters" method="GET">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search driver, license, KYC">
            <select name="kyc">
                <option value="">All KYC</option>
                @foreach (['pending','under_review','verified','rejected'] as $kyc)
                    <option value="{{ $kyc }}" @selected(($filters['kyc'] ?? '') === $kyc)>{{ $kyc }}</option>
                @endforeach
            </select>
            <select name="online">
                <option value="">Duty</option>
                <option value="1" @selected(($filters['online'] ?? '') === '1')>Online</option>
                <option value="0" @selected(($filters['online'] ?? '') === '0')>Offline</option>
            </select>
            <button class="btn ghost" type="submit"><i data-lucide="search"></i> Search</button>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table class="data">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>License</th>
                        <th>KYC</th>
                        <th>Duty</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($drivers as $row)
                    <tr>
                        <td>
                            <div class="person">
                                <div class="avatar">{{ strtoupper(substr($row->user->name ?? 'D', 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $row->user->name ?? '—' }}</strong>
                                    <div class="muted">{{ $row->user->phone ?? $row->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $row->license_no ?? '—' }}</td>
                        <td><span class="pill {{ $row->kyc_status === 'verified' ? 'ok' : ($row->kyc_status === 'rejected' ? 'bad' : 'warn') }}">{{ $row->kyc_status }}</span></td>
                        <td>
                            @if ($row->online)
                                <span class="pill ok"><i data-lucide="radio"></i> Online</span>
                            @else
                                <span class="pill muted">Offline</span>
                            @endif
                        </td>
                        <td class="row-actions">
                            <a class="icon-btn" href="{{ route('drivers.show', $row) }}"><i data-lucide="eye"></i></a>
                            @can('drivers.edit')
                                <a class="icon-btn" href="{{ route('drivers.edit', $row) }}"><i data-lucide="pencil"></i></a>
                                <form method="POST" action="{{ route('drivers.destroy', $row) }}" onsubmit="return confirm('Remove this driver profile?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger"><i data-lucide="trash-2"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No drivers match those filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager">{{ $drivers->links('pagination.admin') }}</div>
    </section>
@endsection
