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
            <h2>Publish home banner</h2>
            <form method="POST" action="{{ route('ads.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Title (optional)</label><input name="title" value="{{ old('title') }}"></div>
                    <div>
                        <label>Banner image</label>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview required>
                    </div>
                </div>
                <p class="muted">JPEG / PNG / WebP / GIF, max 4 MB. Publishing puts this banner on the customer and driver home screens immediately.</p>
                <button class="btn" type="submit"><i data-lucide="send"></i> Publish</button>
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
                            <th>Banner</th>
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
                                <td>
                                    @if (!empty($row['bannerUrl']))
                                        <img src="{{ str_starts_with($row['bannerUrl'], 'http') ? $row['bannerUrl'] : url($row['bannerUrl']) }}" alt="" style="width:88px;height:44px;object-fit:cover;border-radius:8px">
                                    @else
                                        —
                                    @endif
                                </td>
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
