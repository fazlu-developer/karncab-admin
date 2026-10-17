@extends('layouts.app')

@section('title', $def['label'] ?? 'Module')
@section('heading', $def['label'] ?? 'Module')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="{{ $def['icon'] ?? 'folder' }}"></i> {{ $def['label'] }}</h1>
            <p class="muted">Laravel reads the platform database. Secrets are never shown in this table. Territory filters cannot be widened via the URL.</p>
        </div>
        @can('reports.export')
            <a class="btn ghost" href="{{ route('ops.export', ['module' => $def['key'], ...request()->query()]) }}"><i data-lucide="download"></i> Export CSV</a>
        @endcan
    </div>
    @if (!empty($error))
        <p class="error">{{ $error }}</p>
    @endif
    @if (!empty($payload['custom']))
        <section class="card">
            <p>This workspace belongs to a module created under Roles. Anyone whose role includes <code>{{ $def['ability'] ?? ($def['key'].'.view') }}</code> can open it.</p>
        </section>
    @endif

    @if (($def['key'] ?? '') === 'bookings')
        <form class="card" method="GET">
            <div class="filters">
                <div><label>Booking ID / ref</label><input name="publicRef" value="{{ $query['publicRef'] ?? '' }}"></div>
                <div><label>Customer</label><input name="customer" value="{{ $query['customer'] ?? '' }}"></div>
                <div><label>Driver</label><input name="driver" value="{{ $query['driver'] ?? '' }}"></div>
                <div><label>Vehicle</label><input name="vehicle" value="{{ $query['vehicle'] ?? '' }}"></div>
                <div>
                    <label>Booking type</label>
                    <select name="product">
                        <option value="">All types</option>
                        @foreach (['LOCAL_CAB','ONE_WAY','ROUND_TRIP','RENTAL','AIRPORT','OUTSTATION','PARCEL'] as $product)
                            <option value="{{ $product }}" @selected(($query['product'] ?? '') === $product)>{{ $product }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Status</label><input name="status" value="{{ $query['status'] ?? '' }}"></div>
                <div><label>Payment status</label><input name="paymentStatus" value="{{ $query['paymentStatus'] ?? '' }}"></div>
                @include('partials.geo-fields', [
                    'stateName' => 'stateId',
                    'districtName' => 'districtId',
                    'stateValue' => (string) ($query['stateId'] ?? ''),
                    'districtValue' => (string) ($query['districtId'] ?? ''),
                    'emptyState' => 'All states',
                    'emptyDistrict' => 'All districts',
                ])
                <div><label>Fleet ID</label><input name="fleetId" value="{{ $query['fleetId'] ?? '' }}"></div>
                <div><label>Franchise ID</label><input name="franchiseId" value="{{ $query['franchiseId'] ?? '' }}"></div>
                <div><label>From</label><input type="date" name="from" value="{{ $query['from'] ?? '' }}"></div>
                <div><label>To</label><input type="date" name="to" value="{{ $query['to'] ?? '' }}"></div>
            </div>
            <button class="btn" type="submit"><i data-lucide="filter"></i> Filter</button>
        </form>
    @endif

    @if (($def['key'] ?? '') === 'state-heads' && auth()->user()->can('users.create'))
        <section class="card" style="max-width:720px">
            <h2><i data-lucide="user-plus"></i> Add State Head</h2>
            <p class="muted">One ACTIVE State Head per state. They sign in to the admin with this email and password. KYC files show on their KYC page.</p>
            <form method="POST" action="{{ route('organization.state-heads.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="field"><label>Name</label><input name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email') }}" required>@error('email') <div class="error">{{ $message }}</div> @enderror</div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ old('phone') }}">@error('phone') <div class="error">{{ $message }}</div> @enderror</div>
                <div class="field"><label>Password</label><input name="password" type="password" required>@error('password') <div class="error">{{ $message }}</div> @enderror</div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'ACTIVE') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                @php $geo = \App\Platform\GeoCatalog::payload(auth()->user()); @endphp
                <div class="field">
                    <label>State</label>
                    <select name="state_id" required>
                        <option value="">Select state</option>
                        @foreach ($geo['states'] as $state)
                            <option value="{{ $state['id'] }}" @selected((string) old('state_id') === (string) $state['id'])>{{ $state['name'] }}</option>
                        @endforeach
                    </select>
                    @error('state_id') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field"><label>PAN</label><input type="file" name="kyc_pan" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                <div class="field"><label>Aadhaar</label><input type="file" name="kyc_aadhaar" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                <div class="field"><label>Appointment letter</label><input type="file" name="kyc_appointment" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                <div class="field"><label>Address proof</label><input type="file" name="kyc_address" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                <button class="btn" type="submit"><i data-lucide="plus"></i> Add State Head</button>
            </form>
        </section>
    @endif

    @if (($def['key'] ?? '') === 'notifications' && auth()->user()->can('platform.admin'))
        <section class="card">
            <h2><i data-lucide="megaphone"></i> Broadcast</h2>
            <form method="POST" action="{{ route('ops.notify') }}">
                @csrf
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Body</label><textarea name="body" rows="3" required></textarea></div>
                <div class="field">
                    <label>Audience</label>
                    <select name="audience">
                        <option value="customer">Customers</option>
                        <option value="driver">Drivers</option>
                        <option value="ops">Operations</option>
                    </select>
                </div>
                <button class="btn" type="submit"><i data-lucide="send"></i> Send</button>
            </form>
        </section>
    @endif

    <section class="card">
        @if ($rows === [])
            <p class="muted">No records in this module yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            @foreach (array_keys($rows[0]) as $col)
                                <th>{{ \Illuminate\Support\Str::headline(str_replace(['Inr', 'inr'], ['INR', 'INR'], $col)) }}</th>
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $status = strtoupper((string) ($row['Status'] ?? $row['status'] ?? ''));
                                $newRequest = in_array($status, ['SEARCHING', 'REQUESTED', 'DRIVER_SEARCHING', 'PENDING'], true);
                            @endphp
                            <tr @if ($newRequest) style="background:#FFF4CC;font-weight:700" @endif>
                                @foreach ($row as $key => $value)
                                    <td title="{{ is_scalar($value) ? $value : '' }}" style="white-space:normal">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                @endforeach
                                <td>
                                    @if (($def['key'] ?? '') === 'bookings' && !empty($row['Id'] ?? $row['id']))
                                        <a class="icon-btn" href="{{ route('ops.bookings.show', $row['Id'] ?? $row['id']) }}" title="Lifecycle"><i data-lucide="eye"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
