<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KarnaCab Admin')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="guest-body">
        <section class="guest-brand">
            <div>
                <div class="muted" style="color:#f0b429;letter-spacing:.14em;text-transform:uppercase;font-size:12px">KarnaCab</div>
                <h1>Operations console</h1>
                <p>Manage riders, drivers, bookings and the Bihar network from one secure dashboard.</p>
            </div>
            <p class="muted" style="color:#c5ddd3">Need access? Ask a Super Admin to activate your operator account.</p>
        </section>
        <section class="guest-form">
            <div class="auth-card">
                @if (session('status'))
                    <div class="flash">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <script>window.lucide && lucide.createIcons();</script>
</body>
</html>
