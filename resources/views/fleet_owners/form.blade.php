@extends('layouts.app')
@section('title', 'Add Fleet Owner')
@section('heading', 'Add Fleet Owner')
@section('content')
    <section class="card" style="max-width:720px">
        <form method="POST" action="{{ route('fleet-owners.store') }}">
            @csrf
            <div class="field"><label>Fleet Owner Name</label><input name="name" required></div>
            <div class="field"><label>Business Name</label><input name="trade_name" required></div>
            <div class="field"><label>Mobile</label><input name="phone"></div>
            <div class="field"><label>Email</label><input name="email" type="email" required></div>
            <div class="field"><label>Password</label><input name="password" type="password" required></div>
            <div class="field"><label>Address</label><input name="address"></div>
            @include('partials.geo-fields', [
                'required' => true,
                'stateValue' => (string) old('state_id'),
                'districtValue' => (string) old('district_id'),
            ])
            <div class="field">
                <label>Franchise (optional)</label>
                <select name="franchise_id">
                    <option value="">None</option>
                    @foreach ($franchises as $franchise)
                        <option value="{{ $franchise->id }}">{{ $franchise->trade_name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Save</button>
        </form>
    </section>
@endsection
