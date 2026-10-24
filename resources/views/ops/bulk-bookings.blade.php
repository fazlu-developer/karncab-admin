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
            <p class="muted">One customer books many cabs for one pickup and one drop. Confirm the request, wait for the 10% advance, then assign every cab. The job stays off the map until all drivers are assigned.</p>
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
                                    <span class="muted">Waiting for the customer to pay 10%. Driver assignment stays locked.</span>
                                @elseif ($row['assignRemaining'])
                                    <div class="muted" style="margin-bottom:6px">Assign remaining drivers. Pick exactly {{ $row['vehicleCount'] }} different drivers.</div>
                                    <form method="POST" action="{{ route('ops.bulk-bookings.assign', $row['id']) }}">
                                        @csrf
                                        @foreach ($row['cabs'] as $index => $cab)
                                            <label>Cab {{ $cab['cabNo'] }} · {{ $cab['category'] }}</label>
                                            <input type="search" placeholder="Search name or phone" oninput="filterBulkDrivers(this)" style="margin-bottom:4px">
                                            <select name="driver_ids[]" required>
                                                <option value="">Search driver name or phone</option>
                                                @foreach ($drivers as $driver)
                                                    @continue($bulkFamily($driver['category']) === '' || $bulkFamily($driver['category']) !== $bulkFamily($cab['category']))
                                                    <option value="{{ $driver['id'] }}">{{ $driver['label'] }}</option>
                                                @endforeach
                                            </select>
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
    <script>
        function filterBulkDrivers(input) {
            const q = input.value.toLowerCase();
            const select = input.nextElementSibling;
            if (!select) return;
            for (const opt of select.options) {
                if (!opt.value) continue;
                opt.hidden = q !== '' && !opt.text.toLowerCase().includes(q);
            }
        }
    </script>
@endsection
