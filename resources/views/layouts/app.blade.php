<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', ($kcBrand['name'] ?? 'KarnaRide').' Admin')</title>
    <link rel="icon" href="{{ $kcBrand['icoUrl'] ?? asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ $kcBrand['faviconUrl'] ?? asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ $kcBrand['faviconUrl'] ?? asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    @livewireStyles
</head>
<body>
<div class="console">
    <aside class="side">
        @php
            $user = auth()->user();
            $navGroups = \App\Platform\ConsoleNav::grouped($user);
        @endphp
        <a class="logo" href="{{ route($user->homeRoute()) }}">
            <img src="{{ $kcBrand['logoUrl'] ?? asset('branding/karnacab-logo-full.png') }}" alt="{{ $kcBrand['name'] ?? 'KarnaRide' }}" class="logo-img">
        </a>
        @foreach ($navGroups as $group => $items)
            @continue($items === [])
            <div class="group">{{ $group }}</div>
            @foreach ($items as $item)
                <a class="nav {{ \App\Platform\ConsoleNav::active($user, $item) ? 'active' : '' }}" href="{{ \App\Platform\ConsoleNav::href($user, $item) }}">
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
            @include('partials.schedule-alert')
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash" style="background:#fde8e8;color:#7f1d1d">{{ session('error') }}</div>
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
<script>window.KarnaRideGeo = @json($kcGeo ?? ['states' => [], 'districts' => []]);</script>
<script src="{{ asset('js/geo-select.js') }}"></script>
<script src="{{ asset('js/kc-select.js') }}?v={{ filemtime(public_path('js/kc-select.js')) }}"></script>
<script>
document.addEventListener('input', function (event) {
    var id = event.target.getAttribute('data-table-filter');
    if (!id) return;
    var table = document.getElementById(id);
    if (!table) return;
    var term = (event.target.value || '').toLowerCase();
    table.querySelectorAll('tbody tr').forEach(function (row) {
        row.hidden = term !== '' && row.innerText.toLowerCase().indexOf(term) === -1;
    });
});
(function () {
    function boxFor(input) {
        var box = input.parentNode.querySelector('.kc-image-preview');
        if (!box) {
            box = document.createElement('div');
            box.className = 'kc-image-preview';
            box.style.marginTop = '8px';
            input.parentNode.appendChild(box);
        }
        return box;
    }
    function show(input, src) {
        var box = boxFor(input);
        if (!src) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML = '<img src="' + src.replace(/"/g, '&quot;') + '" alt="Preview" style="max-width:100%;max-height:160px;border-radius:10px;object-fit:cover">';
    }
    document.querySelectorAll('input[data-image-preview], input[type=file][accept*="image"], input[name=image_url], input[name=banner]').forEach(function (input) {
        if (input.type === 'file') {
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) {
                    show(input, '');
                    return;
                }
                show(input, URL.createObjectURL(file));
            });
            return;
        }
        if ((input.value || '').match(/^https?:\/\//i)) {
            show(input, input.value);
        }
        input.addEventListener('input', function () {
            var value = (input.value || '').trim();
            show(input, /^https?:\/\//i.test(value) ? value : '');
        });
    });
})();
</script>
@livewireScripts
@stack('scripts')
</body>
</html>
