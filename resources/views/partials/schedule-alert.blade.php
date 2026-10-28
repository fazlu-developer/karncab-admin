@php
    $scheduleReminders = [];
    $scheduleActor = auth()->user();
    if ($scheduleActor && in_array($scheduleActor->role, ['SUPER_ADMIN', 'ADMIN', 'STATE_HEAD', 'DISTRICT_HEAD'], true)) {
        try {
            $scheduleReminders = app(\App\Services\PlatformOpsService::class)->scheduleReminders($scheduleActor);
        } catch (\Throwable) {
            $scheduleReminders = [];
        }
    }
@endphp
@if ($scheduleReminders !== [])
    <div class="schedule-alert">
        <strong>Assign a driver now</strong>
        <p>{{ count($scheduleReminders) }} travel, corporate, bulk, or ambulance booking{{ count($scheduleReminders) === 1 ? '' : 's' }} still need a driver.</p>
        @foreach ($scheduleReminders as $reminder)
            @php
                $desk = match (strtoupper((string) ($reminder['product'] ?? ''))) {
                    'AMBULANCE' => route('ops.ambulance'),
                    'TRAVEL', 'TOUR' => route('ops.tour-bookings'),
                    'CORPORATE' => route('ops.corporate-plans'),
                    'BULK' => route('ops.bulk-bookings'),
                    default => route('ops.assign-drivers'),
                };
            @endphp
            <a href="{{ $desk }}">
                Booking ID {{ $reminder['publicRef'] }} · {{ str_replace('_', ' ', $reminder['product']) }}
                · {{ $reminder['scheduledAt'] ?: 'Waiting for assignment' }}
                · {{ $reminder['pickupText'] }}
            </a>
        @endforeach
    </div>
@endif
