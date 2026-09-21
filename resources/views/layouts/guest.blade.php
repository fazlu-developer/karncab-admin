<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', ($kcBrand['name'] ?? 'KarnaCab').' Admin')</title>
    <link rel="icon" href="{{ $kcBrand['icoUrl'] ?? asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ $kcBrand['faviconUrl'] ?? asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ $kcBrand['faviconUrl'] ?? asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="guest-body">
        <section class="guest-brand">
            <div>
                <img src="{{ $kcBrand['logoUrl'] ?? asset('branding/karnacab-logo-full.png') }}" alt="{{ $kcBrand['name'] ?? 'KarnaCab' }}" style="max-width:320px;width:100%;height:auto;margin-bottom:18px;background:#fff;border-radius:18px;padding:10px">
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
