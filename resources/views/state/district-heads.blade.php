@extends('layouts.app')

@section('title', 'District Heads')
@section('heading', 'District Heads')

@section('content')
    <div class="hero">
        <div>
            <h1>District Heads</h1>
            <p class="muted">Applications start as APPLIED and do not occupy the district. One ACTIVE District Head or exclusive franchise per district is enforced in the database.</p>
        </div>
    </div>
    <section class="card">
        <h3 style="margin-top:0">Add District Head</h3>
        <form method="POST" action="{{ route('state.district-heads.store') }}">
            @csrf
            <div class="field"><label>Name</label><input name="name" required></div>
            <div class="field"><label>Email</label><input name="email" type="email" required></div>
            <div class="field"><label>Phone</label><input name="phone"></div>
            <div class="field">
                <label>District</label>
                <select name="district_id" required>
                    @foreach ($districts ?? [] as $district)
                        <option value="{{ $district['id'] }}">{{ $district['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label>Password</label><input name="password" type="password" required></div>
            <button class="btn" type="submit"><i data-lucide="plus"></i> Create</button>
        </form>
    </section>
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>District</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['email'] }}</td>
                        <td>{{ $row['phone'] }}</td>
                        <td>{{ $row['districtId'] }}</td>
                        <td><span class="pill muted">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No District Heads in this state.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
