@extends('layouts.app')
@section('title', 'Website CMS')
@section('heading', 'Website pages')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="globe"></i> Website CMS</h1>
            <p class="muted">Every public website page reads this table. Edit Privacy, Terms, About and product pages here. Customer-app legal slugs stay in sync.</p>
        </div>
    </div>
    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif

    <section class="card">
        <h2>New page</h2>
        <form method="POST" action="{{ route('ops.website-pages.store') }}" class="filters" style="align-items:end">
            @csrf
            <div><label>Slug</label><input name="slug" placeholder="safety-tips" required></div>
            <div><label>Title</label><input name="title" required></div>
            <div>
                <label>Template</label>
                <select name="template">
                    <option value="legal">Legal / article</option>
                    <option value="service">Service</option>
                    <option value="contact">Contact</option>
                    <option value="support">Support</option>
                    <option value="download">Download</option>
                </select>
            </div>
            <div>
                <label>Nav group</label>
                <select name="nav_group">
                    <option value="company">Company</option>
                    <option value="legal">Legal</option>
                    <option value="rides">Rides</option>
                    <option value="services">Services</option>
                    <option value="primary">Primary</option>
                </select>
            </div>
            <div style="min-width:280px"><label>Intro</label><input name="lede"></div>
            <div style="flex:1;min-width:100%"><label>Body</label><textarea name="body" rows="4"></textarea></div>
            <div><button class="btn" type="submit">Create page</button></div>
        </form>
        <p class="muted">URL will be <code>https://your-site/{slug}</code>.</p>
    </section>

    @foreach ($pages as $page)
        @php
            $body = $page->body ?? '';
            $decoded = is_string($body) ? json_decode($body, true) : $body;
            $html = is_array($decoded) ? ($decoded['html'] ?? $decoded['text'] ?? '') : (string) $body;
            if ($html === '' && is_array($decoded) && !empty($decoded['sections'])) {
                $bits = [];
                foreach ($decoded['sections'] as $section) {
                    $bits[] = $section['heading'] ?? '';
                    $bits[] = $section['text'] ?? '';
                    foreach ($section['paragraphs'] ?? [] as $p) { $bits[] = $p; }
                }
                $html = trim(implode("\n\n", array_filter($bits)));
            }
        @endphp
        <section class="card">
            <h2>{{ $page->title }} <span class="muted">/{{ $page->slug }}</span></h2>
            <form method="POST" action="{{ route('ops.website-pages.update', $page->slug) }}">
                @csrf
                @method('PUT')
                <div class="grid-2">
                    <div class="field"><label>Title</label><input name="title" value="{{ old('title', $page->title) }}" required></div>
                    <div class="field"><label>Eyebrow</label><input name="eyebrow" value="{{ old('eyebrow', $page->eyebrow) }}"></div>
                    <div class="field"><label>Intro / lede</label><input name="lede" value="{{ old('lede', $page->lede) }}"></div>
                    <div class="field"><label>Nav label</label><input name="nav_label" value="{{ old('nav_label', $page->nav_label) }}"></div>
                    <div class="field">
                        <label>Template</label>
                        <select name="template">
                            @foreach (['legal' => 'Legal / article', 'app_legal' => 'App legal', 'service' => 'Service', 'contact' => 'Contact', 'support' => 'Support', 'download' => 'Download', 'rides' => 'Rides', 'travel' => 'Travel', 'register' => 'Register', 'home' => 'Home'] as $value => $label)
                                <option value="{{ $value }}" @selected(($page->template ?: 'legal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Nav group</label>
                        <select name="nav_group">
                            @foreach (['primary', 'rides', 'services', 'company', 'legal'] as $group)
                                <option value="{{ $group }}" @selected(($page->nav_group ?: 'company') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field"><label>Page text</label><textarea name="body" rows="8">{{ old('body', $html) }}</textarea></div>
                <label><input type="checkbox" name="published" value="1" @checked((int) $page->published === 1)> Published</label>
                <p><button class="btn" type="submit">Save {{ $page->slug }}</button></p>
            </form>
        </section>
    @endforeach
@endsection
