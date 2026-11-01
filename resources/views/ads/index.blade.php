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
            <p class="muted">These are the latest 3 published banners. The app rotates them. Pause hides one. Delete removes it and its picture.</p>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                @foreach ($homeLive as $index => $row)
                    <div>
                        <img src="{{ str_starts_with($row['bannerUrl'], 'http') ? $row['bannerUrl'] : url($row['bannerUrl']) }}" alt="" style="width:220px;height:110px;object-fit:cover;border-radius:12px">
                        <p class="muted" style="margin:6px 0">{{ $index + 1 }}. {{ $row['campaign'] }}</p>
                        @if ($canReview)
                            <form method="POST" action="{{ route('ads.pause', $row['id']) }}" style="display:inline">
                                @csrf
                                <button class="btn ghost" type="submit">Pause</button>
                            </form>
                        @endif
                        @can('advertising.edit')
                            <form method="POST" action="{{ route('ads.destroy', $row['id']) }}" style="display:inline" onsubmit="return confirm('Delete this banner? It leaves the customer home.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Delete</button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @can('advertising.edit')
        <section class="card">
            <h2>Add one home banner</h2>
            <form method="POST" action="{{ route('ads.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="filters">
                    <div><label>Name</label><input name="name" value="{{ old('name') }}" placeholder="Banner name"></div>
                    <div><label>Link</label><input name="link" value="{{ old('link') }}" placeholder="https://"></div>
                    <div>
                        <label>Picture</label>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview required>
                    </div>
                    <div>
                        <label>Budget ₹</label>
                        <input name="budget_rupees" type="number" min="0" step="1" value="{{ old('budget_rupees') }}" placeholder="Required only for a second banner">
                    </div>
                    <div>
                        <label>Publish</label>
                        <label class="remember"><input type="checkbox" name="publish" value="1" @checked(old('publish', '1'))> Show on the website and in the apps</label>
                    </div>
                </div>
                @error('budget_rupees') <p class="error">{{ $message }}</p> @enderror
                @error('banner') <p class="error">{{ $message }}</p> @enderror
                <p class="muted">Add one banner at a time. The first published banner needs no budget. Another published banner needs a rupee budget. Clicks are counted in the table. JPEG / PNG / WebP / GIF, max 4 MB.</p>
                <button class="btn" type="submit"><i data-lucide="send"></i> Save banner</button>
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
                            <th>Budget</th>
                            <th>Publish</th>
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
                                <td>{{ data_get($row, 'targetState.name') ?: '—' }} / {{ data_get($row, 'targetDistrict.name') ?: '—' }} / {{ $row['targetCity'] ?: '—' }}</td>
                                <td>{{ $row['impressions'] }}</td>
                                <td>{{ number_format($row['clicks']) }}</td>
                                <td>₹{{ number_format($row['budgetRupees'], 0) }}</td>
                                <td>
                                    @if ($row['status'] === 'published')
                                        <form method="POST" action="{{ route('ads.pause', $row['id']) }}">
                                            @csrf
                                            <button class="btn ghost" type="submit">Published</button>
                                        </form>
                                    @elseif ($row['status'] === 'paused')
                                        <form method="POST" action="{{ route('ads.resume', $row['id']) }}">
                                            @csrf
                                            <button class="btn ghost" type="submit">Off</button>
                                        </form>
                                    @else
                                        {{ $row['status'] }}
                                    @endif
                                </td>
                                <td>
                                    <a class="icon-btn" href="{{ route('ads.show', $row['id']) }}"><i data-lucide="eye"></i></a>
                                    @can('advertising.edit')
                                        <form method="POST" action="{{ route('ads.destroy', $row['id']) }}" style="display:inline" onsubmit="return confirm('Delete this banner?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn danger" type="submit">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
