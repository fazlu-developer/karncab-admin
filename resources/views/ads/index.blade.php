@extends('layouts.app')

@section('title', 'Advertising')
@section('heading', 'Advertising')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="megaphone"></i> KarnaRide ads</h1>
            <p class="muted">{{ $catalog['placements']['note'] }} Impressions {{ number_format($totals['impressions']) }} · Clicks {{ number_format($totals['clicks']) }} · Revenue ₹{{ number_format($totals['revenuePaise'] / 100, 2) }}.</p>
        </div>
    </div>

    @can('advertising.edit')
        <section class="card">
            <h2>Submit campaign</h2>
            <form method="POST" action="{{ route('ads.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Campaign name</label><input name="title" required></div>
                    <div><label>Business name</label><input name="business_name" required></div>
                    <div>
                        <label>Category</label>
                        <select name="category" required>
                            @foreach ($catalog['categories'] as $item)
                                <option value="{{ $item['key'] }}">{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Campaign type</label>
                        <select name="campaign_type" required>
                            @foreach ($catalog['campaignTypes'] as $item)
                                <option value="{{ $item['key'] }}">{{ $item['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>App placement</label>
                        <select name="placement">
                            @foreach ($catalog['placements']['allowed'] as $place)
                                <option value="{{ $place }}" @selected($place === 'home')>{{ ucfirst($place) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('partials.geo-fields', [
                        'stateLabel' => 'Target state',
                        'districtLabel' => 'Target district',
                        'emptyState' => 'All states',
                        'emptyDistrict' => 'All districts',
                    ])
                    <div><label>Target city</label><input name="target_city"></div>
                    <div><label>Start date</label><input name="starts_on" type="date" required></div>
                    <div><label>End date</label><input name="ends_on" type="date" required></div>
                    <div><label>Budget (₹)</label><input name="budget_rupees" type="number" min="1" required></div>
                    <div><label>CTA URL</label><input name="cta_url" placeholder="https://"></div>
                    <div><label>Banner image URL</label><input name="image_url" type="url" placeholder="https://karnaride.in/uploads/ads/banner.jpg"></div>
                    <div>
                        <label>Upload banner</label>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
                <div><label>Business information</label><textarea name="business_info" rows="2"></textarea></div>
                <p class="muted">JPEG / PNG / WebP, 1600×740 recommended, max 2 MB. Use a public HTTPS image URL or upload a file. New campaigns stay pending until an admin approves them.</p>
                <button class="btn" type="submit"><i data-lucide="send"></i> Submit for review</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Campaigns</h2>
        <form class="filters" method="GET">
            <div><label>Search</label><input name="q" value="{{ $query['q'] ?? '' }}" placeholder="Campaign or business"></div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach ($catalog['statuses'] as $status)
                        <option value="{{ $status }}" @selected(($query['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    @foreach ($catalog['categories'] as $item)
                        <option value="{{ $item['key'] }}" @selected(($query['category'] ?? '') === $item['key'])>{{ $item['title'] }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn ghost" type="submit">Filter</button>
        </form>
        @if ($campaigns === [])
            <p class="muted">No campaigns in scope.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Business</th>
                            <th>Status</th>
                            <th>Target</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>Revenue</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaigns as $row)
                            <tr>
                                <td>{{ $row['campaign'] }}</td>
                                <td>{{ $row['business'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['targetState']['name'] ?? '—' }} / {{ $row['targetDistrict']['name'] ?? '—' }} / {{ $row['targetCity'] ?? '—' }}</td>
                                <td>{{ $row['impressions'] }}</td>
                                <td>{{ $row['clicks'] }}</td>
                                <td>₹{{ number_format($row['revenueRupees'], 2) }}</td>
                                <td><a class="icon-btn" href="{{ route('ads.show', $row['id']) }}"><i data-lucide="eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
