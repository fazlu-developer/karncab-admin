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

    @php
        $homeLive = array_values(array_filter(
            $campaigns,
            fn (array $row) => $row['status'] === 'published' && ($row['placement'] ?? 'home') === 'home' && ! empty($row['bannerUrl']),
        ));
        $homeLive = array_slice($homeLive, 0, 3);
        $homeLive = array_reverse($homeLive);
    @endphp
    @if ($homeLive !== [])
        <section class="card">
            <h2>On the customer home now</h2>
            <p class="muted">These are the latest 3 published banners. The app rotates them. Pause one to take it off the home.</p>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                @foreach ($homeLive as $index => $row)
                    <div>
                        <img src="{{ str_starts_with($row['bannerUrl'], 'http') ? $row['bannerUrl'] : url($row['bannerUrl']) }}" alt="" style="width:220px;height:110px;object-fit:cover;border-radius:12px">
                        <p class="muted" style="margin:6px 0">{{ $index + 1 }}. {{ $row['campaign'] }}</p>
                        @if ($canReview)
                            <form method="POST" action="{{ route('ads.pause', $row['id']) }}">
                                @csrf
                                <button class="btn ghost" type="submit">Pause</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @can('advertising.edit')
        <section class="card">
            <h2>Publish home advertisements</h2>
            <form method="POST" action="{{ route('ads.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Banner 1 name</label><input name="name_1" value="{{ old('name_1') }}" placeholder="Banner name"></div>
                    <div><label>Banner 1 link</label><input name="link_1" value="{{ old('link_1') }}" placeholder="https://"></div>
                    <div>
                        <label>Banner 1 picture</label>
                        <input type="file" name="banner_1" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview required>
                    </div>
                    <div><label>Banner 2 name</label><input name="name_2" value="{{ old('name_2') }}" placeholder="Optional"></div>
                    <div><label>Banner 2 link</label><input name="link_2" value="{{ old('link_2') }}" placeholder="https://"></div>
                    <div>
                        <label>Banner 2 picture</label>
                        <input type="file" name="banner_2" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview>
                    </div>
                    <div><label>Banner 3 name</label><input name="name_3" value="{{ old('name_3') }}" placeholder="Optional"></div>
                    <div><label>Banner 3 link</label><input name="link_3" value="{{ old('link_3') }}" placeholder="https://"></div>
                    <div>
                        <label>Banner 3 picture</label>
                        <input type="file" name="banner_3" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview>
                    </div>
                </div>
                <p class="muted">Each banner has a name, a link, and a picture. If the link starts with http, tapping the picture in the customer or driver app opens it. JPEG / PNG / WebP / GIF, max 4 MB. The home keeps the latest 3.</p>
                <button class="btn" type="submit"><i data-lucide="send"></i> Publish on home</button>
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
