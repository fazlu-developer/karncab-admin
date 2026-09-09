@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    <div class="hero">
        <div>
            <h1>State notifications</h1>
            <p class="muted">Broadcasts only reach accounts inside {{ $state['stateName'] }}.</p>
        </div>
    </div>
    <section class="card">
        <form method="POST" action="{{ route('state.notify') }}">
            @csrf
            <div class="field"><label>Title</label><input name="title" required></div>
            <div class="field"><label>Body</label><textarea name="body" rows="4" required></textarea></div>
            <div class="field">
                <label>Audience</label>
                <select name="audience">
                    <option value="driver">Drivers</option>
                    <option value="customer">Customers</option>
                    <option value="ops">District / franchise / fleet</option>
                </select>
            </div>
            <button class="btn" type="submit"><i data-lucide="send"></i> Send in this state</button>
        </form>
    </section>
    <section class="card">
        <table class="data">
            <thead><tr><th>Title</th><th>Body</th><th>Kind</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['title'] }}</td>
                    <td>{{ $row['body'] }}</td>
                    <td>{{ $row['kind'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No recent notifications.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
