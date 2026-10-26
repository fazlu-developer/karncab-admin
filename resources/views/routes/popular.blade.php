@extends('layouts.app')

@section('title', 'Popular routes')
@section('heading', 'Popular routes')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="route"></i> Popular routes</h1>
            <p class="muted">Super admin and state head can add routes. One Way and Round Trip replace the built-in list when routes are published.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="card">{{ session('status') }}</p>
    @endif

    @unless ($ready)
        <p class="card">The popular routes table is not on this database yet. Run the API migration, then add routes here.</p>
    @endunless

    <section class="card">
        <h2>Add a route</h2>
        <form method="post" action="{{ route('routes.popular.store') }}" class="grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            @csrf
            <label>Title<input name="title" required placeholder="Saharsa → Patna"></label>
            <label>Service
                <select name="service">
                    <option value="all">One way and round trip</option>
                    <option value="one_way">One way</option>
                    <option value="round_trip">Round trip</option>
                </select>
            </label>
            <label>From<input name="from_title" required></label>
            <label>From address<input name="from_address"></label>
            <label>From latitude<input name="from_lat" required inputmode="decimal"></label>
            <label>From longitude<input name="from_lng" required inputmode="decimal"></label>
            <label>To<input name="to_title" required></label>
            <label>To address<input name="to_address"></label>
            <label>To latitude<input name="to_lat" required inputmode="decimal"></label>
            <label>To longitude<input name="to_lng" required inputmode="decimal"></label>
            <label>Distance km<input name="distance_km" type="number" min="0"></label>
            <label>From fare ₹<input name="from_fare" type="number" min="0"></label>
            <label>State<input name="state" placeholder="Bihar"></label>
            <div style="align-self:end"><button class="btn" type="submit">Save route</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Published routes</h2>
        <table>
            <thead><tr><th>Title</th><th>Service</th><th>From</th><th>To</th><th>Km</th><th></th></tr></thead>
            <tbody>
                @forelse ($routes as $route)
                    <tr>
                        <td>{{ $route->title }}</td>
                        <td>{{ $route->service }}</td>
                        <td>{{ $route->from_title }}</td>
                        <td>{{ $route->to_title }}</td>
                        <td>{{ $route->distance_km }}</td>
                        <td>
                            <form method="post" action="{{ route('routes.popular.destroy', $route->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No routes yet. The apps keep the built-in Saharsa routes until you add one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
