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
    @livewireStyles
</head>
<body>
<div class="console">
    <aside class="side">
        @php
            $user = auth()->user();
            $navGroups = $user->isAdvertiser()
                ? \App\Platform\AdvertiserNav::grouped()
                : ($user->isStateHead()
                    ? \App\Platform\StateHeadNav::grouped()
                    : ($user->isFleetOwner()
                        ? \App\Platform\FleetNav::grouped()
                        : ($user->isCustomer()
                            ? \App\Platform\CustomerNav::grouped()
                            : \App\Platform\OpsNav::grouped($user))));
        @endphp
        <a class="logo" href="{{ route($user->homeRoute()) }}">
            <img src="{{ $kcBrand['logoUrl'] ?? asset('branding/karnacab-logo-full.png') }}" alt="{{ $kcBrand['name'] ?? 'KarnaCab' }}" class="logo-img">
        </a>
        @foreach ($navGroups as $group => $items)
            <div class="group">{{ $group }}</div>
            @foreach ($items as $item)
                @php
                    $href = $user->isAdvertiser()
                        ? \App\Platform\AdvertiserNav::href($item)
                        : ($user->isStateHead()
                            ? \App\Platform\StateHeadNav::href($item)
                            : ($user->isFleetOwner()
                                ? \App\Platform\FleetNav::href($item)
                                : ($user->isCustomer()
                                    ? \App\Platform\CustomerNav::href($item)
                                    : \App\Platform\OpsNav::href($item))));
                    $active = $user->isAdvertiser()
                        ? \App\Platform\AdvertiserNav::active($item)
                        : ($user->isStateHead()
                            ? \App\Platform\StateHeadNav::active($item)
                            : ($user->isFleetOwner()
                                ? \App\Platform\FleetNav::active($item)
                                : ($user->isCustomer()
                                    ? \App\Platform\CustomerNav::active($item)
                                    : \App\Platform\OpsNav::active($item))));
                @endphp
                <a class="nav {{ $active ? 'active' : '' }}" href="{{ $href }}">
                    <i data-lucide="{{ $item['icon'] }}"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endforeach
    </aside>
    <div class="main">
        <header class="topbar">
            <strong>@yield('heading', 'Operations')</strong>
            <div class="who">
                <i data-lucide="shield-check"></i>
                {{ auth()->user()->name }} · {{ auth()->user()->role }}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn ghost" type="submit"><i data-lucide="log-out"></i> Log out</button>
                </form>
            </div>
        </header>
        <div class="page">
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash" style="background:#fde8e8;color:#7f1d1d">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
<script src="https://unpkg.com/lucide@0.469.0"></script>
<script>window.lucide && lucide.createIcons();</script>
<script>window.KarnaCabGeo = @json($kcGeo ?? ['states' => [], 'districts' => []]);</script>
<script src="{{ asset('js/geo-select.js') }}"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
