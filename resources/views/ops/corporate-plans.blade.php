@extends('layouts.app')
@section('title', 'Corporate Plans')
@section('heading', 'Corporate Plans')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="briefcase"></i> Corporate travel plans</h1>
            <p class="muted">One corporate plan is one car for every day in the period. Approve it, then the customer pays 10% and the remaining 90% to KarnaRide. Assign one driver after the plan is paid in full. That driver is credited on this plan id and withdraws later.</p>
        </div>
    </div>
    @can('corporate.edit')
        <section class="card">
            <h2>Add plan</h2>
            <form method="POST" action="{{ route('ops.corporate-plans.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required placeholder="On-Demand Booking"></div>
                    <div><label>Key</label><input name="plan_key" placeholder="ON_DEMAND"></div>
                    <div><label>Subtitle</label><input name="subtitle" placeholder="Instant booking for business travel"></div>
                    <div>
                        <label>Pricing</label>
                        <select name="pricing_mode">
                            <option value="VEHICLE">Live vehicle fare</option>
                            <option value="FIXED">Fixed plan price (₹)</option>
                            <option value="QUOTE">Custom quote (no sticker price)</option>
                        </select>
                    </div>
                    <div><label>Fixed price (₹)</label><input name="price_rupees" type="number" min="0" step="1" value="0"></div>
                    <div><label>Price label</label><input name="price_label" placeholder="Custom Get Quote"></div>
                    <div><label>Days</label><input name="duration_days" type="number" min="1" step="1" placeholder="7, 15 or 30"></div>
                    <div><label>GST %</label><input name="gst_percent" type="number" min="0" step="0.01" value="5"></div>
                    <div><label>Sort</label><input name="sort_order" type="number" min="0" value="1"></div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="PUBLISHED">PUBLISHED</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="ARCHIVED">ARCHIVED</option>
                        </select>
                    </div>
                    <div style="grid-column:1/-1"><label>Highlights (one per line)</label><textarea name="highlights" rows="3" placeholder="Pay per trip"></textarea></div>
                </div>
                <button class="btn" type="submit" style="margin-top:12px">Save plan</button>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Plans</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Plan</th><th>Pricing</th><th>GST</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($plans as $row)
                    @php
                        $highlights = is_string($row->highlights ?? null) ? json_decode($row->highlights, true) : [];
                        $highlightText = is_array($highlights) ? implode("\n", $highlights) : ($row->highlights ?? '');
                    @endphp
                    <tr>
                        <td>{{ $row->title }}<br><span class="muted">{{ $row->plan_key }} · {{ $row->subtitle }}</span></td>
                        <td>
                            {{ $row->pricing_mode }}
                            @if ((int) ($row->price_paise ?? 0) > 0)
                                · ₹{{ number_format(((int) $row->price_paise) / 100, 0) }}
                            @elseif (!empty($row->price_label))
                                · {{ $row->price_label }}
                            @else
                                · live fare
                            @endif
                        </td>
                        <td>{{ $row->gst_percent }}%</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            @can('corporate.edit')
                                <form method="POST" action="{{ route('ops.corporate-plans.update', $row->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="filters">
                                        <input name="title" value="{{ $row->title }}" required>
                                        <input name="plan_key" value="{{ $row->plan_key }}">
                                        <input name="subtitle" value="{{ $row->subtitle }}">
                                        <select name="pricing_mode">
                                            @foreach (['VEHICLE' => 'Live vehicle fare', 'FIXED' => 'Fixed price', 'QUOTE' => 'Custom quote'] as $key => $label)
                                                <option value="{{ $key }}" @selected(($row->pricing_mode ?? '') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="price_rupees" type="number" min="0" value="{{ (int) round(((int) ($row->price_paise ?? 0)) / 100) }}">
                                        <input name="price_label" value="{{ $row->price_label }}" placeholder="Label">
                                        <input name="duration_days" type="number" min="1" value="{{ $row->duration_days }}" placeholder="Days">
                                        <input name="gst_percent" type="number" step="0.01" value="{{ $row->gst_percent ?? 5 }}">
                                        <input name="sort_order" type="number" value="{{ $row->sort_order ?? 0 }}">
                                        <select name="status">
                                            @foreach (['DRAFT','PUBLISHED','ARCHIVED'] as $st)
                                                <option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="highlights" rows="2">{{ $highlightText }}</textarea>
                                        <button class="btn" type="submit">Update</button>
                                    </div>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No corporate plans yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>Corporate plan bookings</h2>
        <p class="muted">{{ $totals['plans'] }} plans · ₹{{ number_format($totals['totalRupees'], 0) }} collected target · commission ₹{{ number_format($totals['commissionRupees'], 0) }} · district share ₹{{ number_format($totals['districtShareRupees'], 0) }}</p>
        <form method="GET" class="filters" style="margin-bottom:12px">
            <div>
                <label>Status</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach (['REQUESTED' => 'Waiting for approval', 'APPROVED' => 'Pay 10%', 'APPROVED_10' => 'Pay 90%', 'PAID_100' => 'Paid in full', 'ASSIGNED' => 'Driver assigned', 'LIVE' => 'Live today', 'EXPIRED' => 'Expired', 'COMPLETED' => 'Completed', 'REJECTED' => 'Rejected'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Ref</th><th>Plan</th><th>Route</th><th>Money</th><th>Driver</th><th></th></tr></thead>
                <tbody>
                @forelse ($bookings as $row)
                    <tr>
                        <td>{{ $row['publicRef'] }}<br><span class="muted">{{ $row['statusLabel'] }}</span></td>
                        <td>{{ $row['planKey'] }} · {{ $row['tripKind'] }}<br><span class="muted">{{ $row['when'] }}</span><br><span class="muted">{{ $row['doneCount'] }}/{{ $row['dayCount'] }} days · today {{ $row['todayPhase'] ?: '—' }}</span></td>
                        <td>{{ $row['pickup'] }}<br><span class="muted">{{ $row['drop'] }}</span><br><span class="muted">{{ $row['contactName'] }} {{ $row['contactPhone'] }}</span></td>
                        <td>
                            ₹{{ number_format($row['totalRupees'], 0) }} total<br>
                            <span class="muted">10% ₹{{ number_format($row['pay10Rupees'], 0) }} · 90% ₹{{ number_format($row['pay90Rupees'], 0) }} · paid ₹{{ number_format($row['paidRupees'], 0) }}</span><br>
                            <span class="muted">Driver ₹{{ number_format($row['driverShareRupees'], 0) }} · commission ₹{{ number_format($row['commissionRupees'], 0) }}</span>
                            @if ($row['fleetOwnerName'])
                                <br><span class="muted">Fleet {{ $row['fleetOwnerName'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['driverName'] ? $row['driverName'].' · '.$row['driverPhone'].' · '.$row['plate'] : 'Unassigned' }}</td>
                        <td>
                            @if ($canMoney)
                                @if ($row['status'] === 'REQUESTED')
                                    <form method="POST" action="{{ route('ops.corporate-plans.approve', $row['id']) }}">@csrf<button class="btn" type="submit">Approve</button></form>
                                    <form method="POST" action="{{ route('ops.corporate-plans.reject', $row['id']) }}" style="margin-top:8px">@csrf<input name="reason" required placeholder="Reject reason" maxlength="240"><button class="btn ghost" type="submit">Reject</button></form>
                                @elseif ($row['status'] === 'APPROVED')
                                    <span class="muted">Waiting for the customer to pay 10%.</span>
                                @elseif ($row['status'] === 'APPROVED_10')
                                    <span class="muted">Waiting for the remaining 90%.</span>
                                @elseif ($row['status'] === 'PAID_100')
                                    <form method="POST" action="{{ route('ops.corporate-plans.assign', $row['id']) }}">
                                        @csrf
                                        <input type="search" placeholder="Search name or phone" oninput="filterCorporateDrivers(this)" style="margin-bottom:4px">
                                        <select name="driver_id" required>
                                            <option value="">Search driver name or phone</option>
                                            @foreach ($drivers as $driver)
                                                <option value="{{ $driver['id'] }}">{{ $driver['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn" type="submit">Assign driver</button>
                                    </form>
                                @endif
                                @if (in_array($row['status'], ['PAID_100', 'ASSIGNED', 'LIVE', 'EXPIRED', 'COMPLETED'], true))
                                    <form method="POST" action="{{ route('ops.corporate-plans.shares', $row['id']) }}" style="margin-top:8px">
                                        @csrf
                                        <input name="district_rupees" type="number" min="0" step="1" placeholder="District head ₹" value="{{ $row['districtHeadRupees'] }}">
                                        <input name="fleet_rupees" type="number" min="0" step="1" placeholder="Fleet owner ₹" value="{{ $row['fleetOwnerRupees'] }}">
                                        <button class="btn ghost" type="submit">Save shares</button>
                                    </form>
                                    <form method="POST" action="{{ route('ops.corporate-plans.timetable', $row['id']) }}" style="margin-top:8px">
                                        @csrf
                                        <input name="pickup_time" value="{{ $row['pickupTime'] }}" required>
                                        <input name="starts_on" type="date" value="{{ $row['startsOn'] }}" required>
                                        <input name="ends_on" type="date" value="{{ $row['endsOn'] }}" required>
                                        <button class="btn ghost" type="submit">Update time</button>
                                    </form>
                                    <form method="POST" action="{{ route('ops.corporate-plans.renew', $row['id']) }}" style="margin-top:8px">
                                        @csrf
                                        <input name="duration_days" type="number" min="7" value="30" required>
                                        <label><input type="checkbox" name="comped" value="1"> Comp this renewal</label>
                                        <button class="btn ghost" type="submit">Renew</button>
                                    </form>
                                @endif
                            @else
                                <span class="muted">View only for this territory.</span>
                            @endif
                            @if ($row['rejectReason'])
                                <div class="muted">{{ $row['rejectReason'] }}</div>
                            @endif
                            @if ($row['invoiceNo'])
                                <div class="muted">{{ $row['invoiceNo'] }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No corporate plans yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <script>
        function filterCorporateDrivers(input) {
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
