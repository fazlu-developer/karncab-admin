@extends('layouts.app')

@section('title', $user->name)
@section('heading', 'User')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $user->name }}</h1>
            <p class="muted">{{ $user->email }} · {{ $user->phone ?: 'No phone' }}</p>
        </div>
        <div class="row-actions">
            @if ($user->driver)
                <a class="btn" href="{{ route('drivers.show', $user->driver) }}#driver-kyc"><i data-lucide="file-badge"></i> Open driver KYC</a>
            @endif
            <a class="btn ghost" href="{{ route('users.index') }}"><i data-lucide="list"></i> List</a>
            @can('users.edit')
                <a class="btn" href="{{ route('users.edit', $user) }}"><i data-lucide="pencil"></i> Edit</a>
            @endcan
        </div>
    </div>
    <section class="card">
        <p>
            <span class="pill muted"><i data-lucide="shield"></i> {{ $user->role }}</span>
            <span class="pill {{ $user->status === 'ACTIVE' ? 'ok' : ($user->status === 'PENDING' ? 'warn' : 'bad') }}">
                <i data-lucide="{{ $user->status === 'ACTIVE' ? 'check-circle' : 'alert-circle' }}"></i>
                {{ $user->status }}
            </span>
        </p>
        @if ($user->driver)
            <p>
                <i data-lucide="id-card"></i>
                Driver profile #{{ $user->driver->id }}
                · KYC {{ str_replace('_', ' ', $user->driver->kyc_status) }}
            </p>
            <p>
                <a class="btn" href="{{ route('drivers.show', $user->driver) }}#driver-kyc"><i data-lucide="file-badge"></i> Open driver KYC</a>
            </p>
        @endif
        @if (in_array($user->role, \App\Services\OperatorProvisioner::CONSOLE_ROLES, true))
            <p>
                Admin login: <strong>{{ $user->email }}</strong>
                @if ($operator)
                    · state {{ $operator->state_id ?: 'not assigned' }}
                    · district {{ $operator->district_id ?: '—' }}
                @else
                    · no console login yet. Edit this user and set a password.
                @endif
            </p>
        @endif
        @if (!empty($territory['kpis']))
            <h3>{{ $territory['kind'] }} figures · {{ $territory['place'] }}</h3>
            <div class="kpis">
                @foreach ([
                    'bookings' => 'Bookings',
                    'completed' => 'Completed',
                    'revenueRupees' => 'Revenue ₹',
                    'districtEarningRupees' => 'District earnings ₹',
                    'commissionRupees' => 'Ride commission ₹',
                    'platformShareRupees' => 'Platform share ₹',
                ] as $key => $label)
                    <div class="kpi">
                        <div class="label">{{ $label }}</div>
                        <b>{{ $territory['kpis'][$key] ?? 0 }}</b>
                    </div>
                @endforeach
            </div>
            <p><a href="{{ route('ops.module', array_filter([
                'module' => 'bookings',
                'status' => 'COMPLETED',
                'districtId' => $territory['kind'] === 'District' ? ($user->district_id ?: $operator?->district_id) : null,
                'stateId' => $territory['kind'] === 'State' ? ($user->state_id ?: $operator?->state_id) : null,
            ])) }}">Open this territory's bookings</a></p>
        @endif
        @if ($operator && !empty($operator->access_note))
            <p><strong>Access comment:</strong> {{ $operator->access_note }}</p>
        @endif
        @if ($kyc !== [])
            <h3>KYC</h3>
            <div class="kyc-list">
                @foreach ($kyc as $doc)
                    @php
                        $fileRoute = str_contains(strtoupper((string) $user->role), 'STATE')
                            ? 'organization.state-kyc.file'
                            : 'organization.district-kyc.file';
                        $fileUrl = route($fileRoute, $doc->id);
                        $ext = strtolower(pathinfo((string) ($doc->path ?? $doc->original_name ?? ''), PATHINFO_EXTENSION));
                        $image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                        $pdf = $ext === 'pdf';
                    @endphp
                    <article class="kyc-row">
                        <a class="kyc-preview" href="{{ $fileUrl }}" target="_blank" rel="noopener">
                            @if ($image)
                                <img src="{{ $fileUrl }}" alt="{{ $doc->doc_type }}" width="112" height="80" loading="lazy">
                            @elseif ($pdf)
                                <iframe src="{{ $fileUrl }}" title="{{ $doc->doc_type }}"></iframe>
                            @else
                                <span>File</span>
                            @endif
                        </a>
                        <div class="kyc-meta">
                            <strong>{{ $doc->doc_type }}</strong>
                            <div class="muted">{{ $doc->original_name ?: ($doc->path ?: 'Document') }}</div>
                            <span class="pill muted">{{ $doc->status }}</span>
                        </div>
                        <div class="kyc-actions">
                            <a class="btn ghost" href="{{ $fileUrl }}" target="_blank" rel="noopener">Open</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        <p class="muted">Created {{ \App\Support\IndiaTime::format($user->created_at) }}</p>
    </section>
@endsection
