@extends('layouts.app')

@section('title', $campaign['campaign'])
@section('heading', 'Campaign')

@section('content')
    <p><a href="{{ route('ads.index') }}"><i data-lucide="arrow-left"></i> All campaigns</a></p>
    <div class="hero">
        <div>
            <h1>{{ $campaign['campaign'] }}</h1>
            <p class="muted">{{ $campaign['business'] }} · {{ $campaign['categoryLabel'] }} · {{ $campaign['campaignTypeLabel'] }} · {{ $campaign['status'] }}</p>
        </div>
    </div>

    <section class="card">
        <h2>Delivery</h2>
        <p>Impressions {{ $campaign['impressions'] }} · Clicks {{ $campaign['clicks'] }} · CTR {{ $campaign['ctr'] }}% · Revenue ₹{{ number_format($campaign['revenueRupees'], 2) }} · Budget ₹{{ number_format($campaign['budgetRupees'], 2) }}</p>
        <p class="muted">Target {{ $campaign['targetState']['name'] ?? 'all states' }} / {{ $campaign['targetDistrict']['name'] ?? 'all districts' }} / {{ $campaign['targetCity'] ?: 'all cities' }} · {{ $campaign['startDate'] }} → {{ $campaign['endDate'] }}</p>
        @if ($campaign['rejectedReason'])
            <p class="muted">Rejected: {{ $campaign['rejectedReason'] }}</p>
        @endif
        @if ($campaign['bannerUrl'])
            <p>
                <img src="{{ $campaign['bannerUrl'] }}" alt="Banner" style="max-width:100%;max-height:220px;border-radius:12px;object-fit:cover">
            </p>
            <p class="muted">Image URL: {{ $campaign['imageUrl'] ?? $campaign['bannerUrl'] }}</p>
        @else
            <p class="muted">No banner yet. Upload a file or paste a public HTTPS image URL below.</p>
        @endif
    </section>

    @can('advertising.edit')
        <section class="card">
            <h2>Banner</h2>
            <form method="POST" action="{{ route('ads.update', $campaign['id']) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <div class="filters">
                    <div><label>Title (optional)</label><input name="title" value="{{ $campaign['campaign'] }}"></div>
                    <div>
                        <label>Replace banner</label>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview>
                    </div>
                </div>
                <button class="btn" type="submit">Publish</button>
            </form>
        </section>
    @endcan

    @if ($canReview)
        <section class="card">
            <h2>Admin actions</h2>
            @if (in_array($campaign['status'], ['pending', 'approved'], true))
                <form method="POST" action="{{ route('ads.review', $campaign['id']) }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="status" value="approved">
                    <button class="btn" type="submit">Approve</button>
                </form>
                <form method="POST" action="{{ route('ads.review', $campaign['id']) }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    <input name="reason" placeholder="Reject reason">
                    <button class="btn ghost" type="submit">Reject</button>
                </form>
            @endif
            @if (in_array($campaign['status'], ['published', 'approved'], true))
                <form method="POST" action="{{ route('ads.pause', $campaign['id']) }}" style="display:inline">
                    @csrf
                    <button class="btn ghost" type="submit">Pause</button>
                </form>
            @endif
            @if ($campaign['status'] === 'paused')
                <form method="POST" action="{{ route('ads.resume', $campaign['id']) }}" style="display:inline">
                    @csrf
                    <button class="btn" type="submit">Resume</button>
                </form>
            @endif
        </section>
    @endif
@endsection
