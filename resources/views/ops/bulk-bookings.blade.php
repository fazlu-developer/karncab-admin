@extends('layouts.app')
@section('title', 'Bulk Bookings')
@section('heading', 'Bulk Bookings')
@section('content')
    @php
        $bulkFamily = function (string $value): string {
            $value = strtoupper(trim($value));
            return match ($value) {
                'CAR', 'CAB', 'HATCHBACK', 'MINI', 'PREMIUM' => 'SEDAN',
                'VAN', 'TEMPO', 'TEMPO_TRAVELLER' => 'TRAVELLER',
                default => $value,
            };
        };
    @endphp
    <div class="hero">
        <div>
            <h1><i data-lucide="layers"></i> Bulk bookings</h1>
            <p class="muted">One customer books many cabs for one pickup and one drop. Confirm the request, wait for the {{ \App\Support\AdvancePayment::percent('BULK') }}% advance, then assign every cab. Search drivers by name, phone, or vehicle. The job stays off the map until all drivers are assigned.</p>
        </div>
    </div>
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Service</th>
                        <th>When</th>
                        <th>Route</th>
                        <th>Money</th>
                        <th>Cabs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['publicRef'] }}<br><span class="muted">{{ $row['statusLabel'] }}</span></td>
                        <td>{{ $row['tripKind'] }}<br><span class="muted">{{ $row['category'] }}</span></td>
                        <td>{{ $row['when'] ?: '—' }}</td>
                        <td>{{ $row['pickup'] ?: '—' }}<br><span class="muted">{{ $row['drop'] }}</span><br><span class="muted">{{ $row['contactName'] }} {{ $row['contactPhone'] }}</span></td>
                        <td>
                            ₹{{ number_format($row['totalRupees'], 0) }} total<br>
                            <span class="muted">10% ₹{{ number_format($row['advanceRupees'], 0) }} · due ₹{{ number_format($row['balanceRupees'], 0) }}</span>
                        </td>
                        <td>
                            {{ $row['assignedCount'] }}/{{ $row['vehicleCount'] }}
                            @foreach ($row['cabs'] as $cab)
                                <div class="muted">Cab {{ $cab['cabNo'] }} {{ $cab['category'] }} {{ $cab['driverName'] ? '· '.$cab['driverName'].' '.$cab['plate'] : '· unassigned' }} · {{ $cab['phase'] }}</div>
                            @endforeach
                        </td>
                        <td>
                            @can('bookings.manage')
                                @if ($row['status'] === 'REQUESTED' || $row['status'] === 'pending_review')
                                    <form method="POST" action="{{ route('ops.bulk-bookings.confirm', $row['id']) }}">
                                        @csrf
                                        <button class="btn" type="submit">Confirm</button>
                                    </form>
                                    <form method="POST" action="{{ route('ops.bulk-bookings.reject', $row['id']) }}" style="margin-top:8px">
                                        @csrf
                                        <input name="reason" required placeholder="Reject reason" maxlength="240">
                                        <button class="btn ghost" type="submit">Reject</button>
                                    </form>
                                @elseif ($row['status'] === 'CONFIRMED_UNPAID')
                                    <span class="muted">Waiting for the customer to pay {{ \App\Support\AdvancePayment::percent('BULK') }}%. Driver assignment stays locked.</span>
                                @elseif ($row['assignRemaining'])
                                    <div class="muted" style="margin-bottom:6px">Assign remaining drivers. Pick exactly {{ $row['vehicleCount'] }} different drivers. Search by name, phone, or plate.</div>
                                    <form method="POST" action="{{ route('ops.bulk-bookings.assign', $row['id']) }}">
                                        @csrf
                                        @foreach ($row['cabs'] as $index => $cab)
                                            <label>Cab {{ $cab['cabNo'] }} · {{ $cab['category'] }}</label>
                                            @php
                                                $cabDrivers = array_values(array_filter($drivers, fn ($driver) => $bulkFamily($driver['category'] ?? '') !== '' && $bulkFamily($driver['category'] ?? '') === $bulkFamily($cab['category'] ?? '')));
                                            @endphp
                                            @include('partials.name-picker', ['name' => 'driver_ids[]', 'options' => $cabDrivers, 'required' => true, 'empty' => 'Search name, phone, or vehicle'])
                                        @endforeach
                                        <button class="btn" type="submit">Assign all drivers</button>
                                    </form>
                                @endif
                            @endcan
                            @if ($row['rejectReason'])
                                <div class="muted">{{ $row['rejectReason'] }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No bulk bookings yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
