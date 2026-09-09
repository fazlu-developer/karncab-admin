@extends('layouts.app')

@section('title', 'Advertising')
@section('heading', 'Advertising')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="megaphone"></i> Karna Cab ads</h1>
            <p class="muted">{{ $catalog['placements']['note'] }} Impressions {{ number_format($totals['impressions']) }} · Clicks {{ number_format($totals['clicks']) }} · Revenue ₹{{ number_format($totals['revenuePaise'] / 100, 2) }}.</p>
        </div>
    </div>

    @can('advertising.edit')
        <section class="card">
            <h2>Submit campaign</h2>
            <form method="POST" action="{{ route('ads.store') }}">
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
                        <label>Target state</label>
                        <select name="state_id">
                            <option value="">All states</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Target district</label>
                        <select name="district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Target city</label><input name="target_city"></div>
                    <div><label>Start date</label><input name="starts_on" type="date" required></div>
                    <div><label>End date</label><input name="ends_on" type="date" required></div>
                    <div><label>Budget (₹)</label><input name="budget_rupees" type="number" min="1" required></div>
                    <div><label>CTA URL</label><input name="cta_url"></div>
                </div>
                <div><label>Business information</label><textarea name="business_info" rows="2"></textarea></div>
                <p class="muted">New campaigns stay pending until an admin approves them. Ads never publish from this form.</p>
                <button class="btn" type="submit"><i data-lucide="send"></i> Submit for review</button>
            </form>
        </section>
    @endcan

    <section class="card">
        <h2>Campaigns</h2>
        <form class="filters" method="GET">
            <div><label>Search</label><input name="q" value="{{ $query['q'] ?? '' }}" placeholder="Campaign or business"></div>
            <div><label>Status</label><input name="status" value="{{ $query['status'] ?? '' }}"></div>
            <div><label>Category</label><input name="category" value="{{ $query['category'] ?? '' }}"></div>
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
