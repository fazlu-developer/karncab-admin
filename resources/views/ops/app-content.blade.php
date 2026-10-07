@extends('layouts.app')
@section('title', 'App content')
@section('heading', 'Customer app pages')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="file-text"></i> Account menu pages</h1>
            <p class="muted">These five pages appear in the customer app Account section. Edit the title, short description, full text and optional image. Saved text stays until you change it again.</p>
        </div>
    </div>
    @foreach ($pages as $page)
        @php
            $body = $page->body ?? '';
            $decoded = is_string($body) ? json_decode($body, true) : $body;
            $html = is_array($decoded) ? ($decoded['html'] ?? $decoded['text'] ?? '') : (string) $body;
            $image = $page->image_url ?? '';
            $imageSrc = $image === '' ? '' : (str_starts_with($image, 'http') ? $image : asset(ltrim($image, '/')));
        @endphp
        <section class="card">
            <h2>{{ $page->title }}</h2>
            <form method="POST" action="{{ route('ops.app-content.update', $page->slug) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="grid-2">
                    <div class="field"><label>Title</label><input name="title" value="{{ old('title', $page->title) }}" required></div>
                    <div class="field"><label>Short description</label><input name="lede" value="{{ old('lede', $page->lede) }}" maxlength="500"></div>
                </div>
                <div class="field"><label>Page text (editor)</label><textarea name="body" rows="8">{{ old('body', $html) }}</textarea></div>
                <div class="field">
                    <label>Image (optional)</label>
                    @if ($imageSrc)
                        <p><img src="{{ $imageSrc }}" alt="" style="max-width:220px;border-radius:12px"></p>
                        <label><input type="checkbox" name="remove_image" value="1"> Remove image</label>
                    @endif
                    <input type="file" name="image" accept="image/*">
                </div>
                <button class="btn" type="submit">Save {{ $page->title }}</button>
            </form>
        </section>
    @endforeach
@endsection
