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
        <p>{{ count($scheduleReminders) }} scheduled booking{{ count($scheduleReminders) === 1 ? '' : 's' }} in your area still have no driver.</p>
        @foreach ($scheduleReminders as $reminder)
            <a href="{{ route('ops.assign-drivers') }}">
                Booking ID {{ $reminder['publicRef'] }} · {{ str_replace('_', ' ', $reminder['product']) }}
                · {{ $reminder['scheduledAt'] ?: 'Time not set' }}
                · {{ $reminder['pickupText'] }}
            </a>
        @endforeach
    </div>
@endif
