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
            <p><a href="{{ url($campaign['bannerUrl']) }}">Banner</a></p>
        @endif
    </section>

    @can('advertising.edit')
        <section class="card">
            <h2>{{ $canReview ? 'Edit & targeting' : 'Update campaign' }}</h2>
            <form method="POST" action="{{ route('ads.update', $campaign['id']) }}">
                @csrf
                @method('PATCH')
                <div class="filters">
                    <div><label>Campaign name</label><input name="title" value="{{ $campaign['campaign'] }}"></div>
                    <div><label>Business name</label><input name="business_name" value="{{ $campaign['business'] }}"></div>
                    <div>
                        <label>Category</label>
                        <select name="category">
                            @foreach ($catalog['categories'] as $item)
                                <option value="{{ $item['key'] }}" @selected($campaign['category'] === $item['key'])>{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Campaign type</label>
                        <select name="campaign_type">
                            @foreach ($catalog['campaignTypes'] as $item)
                                <option value="{{ $item['key'] }}" @selected($campaign['campaignType'] === $item['key'])>{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Target state</label>
                        <select name="state_id">
                            <option value="">All states</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}" @selected(($campaign['targetState']['id'] ?? null) == $state->id)>{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Target district</label>
                        <select name="district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected(($campaign['targetDistrict']['id'] ?? null) == $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Target city</label><input name="target_city" value="{{ $campaign['targetCity'] }}"></div>
                    <div><label>Start date</label><input name="starts_on" type="date" value="{{ $campaign['startDate'] }}"></div>
                    <div><label>End date</label><input name="ends_on" type="date" value="{{ $campaign['endDate'] }}"></div>
                    <div><label>Budget (₹)</label><input name="budget_rupees" type="number" min="1" value="{{ (int) $campaign['budgetRupees'] }}"></div>
                    <div><label>CTA URL</label><input name="cta_url" value="{{ $campaign['ctaUrl'] }}"></div>
                </div>
                <div><label>Business information</label><textarea name="business_info" rows="2">{{ $campaign['businessInfo'] }}</textarea></div>
                <button class="btn" type="submit">Save</button>
            </form>
            <form method="POST" action="{{ route('ads.banner', $campaign['id']) }}" enctype="multipart/form-data" style="margin-top:12px">
                @csrf
                <label>Banner</label>
                <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn ghost" type="submit">Upload banner</button>
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
