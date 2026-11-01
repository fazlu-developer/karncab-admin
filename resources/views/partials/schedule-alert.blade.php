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
        <strong>Scheduled bookings</strong>
        <p>{{ count($scheduleReminders) }} customer schedule{{ count($scheduleReminders) === 1 ? '' : 's' }} across every service.</p>
        @foreach ($scheduleReminders as $reminder)
            @php
                $bookingUrl = !empty($reminder['id'])
                    ? route('ops.bookings.show', $reminder['id'])
                    : route('ops.assign-drivers');
            @endphp
            <a href="{{ $bookingUrl }}">
                Booking ID {{ $reminder['id'] ?? $reminder['publicRef'] }}
                · {{ $reminder['publicRef'] }}
                · {{ str_replace('_', ' ', (string) ($reminder['product'] ?? '')) }}
                · {{ $reminder['scheduledAt'] ?: 'Waiting for assignment' }}
                · {{ $reminder['pickupText'] ?? '' }}
                · {{ empty($reminder['driverId']) ? 'Needs a driver' : 'Driver assigned' }}
            </a>
        @endforeach
    </div>
@endif
