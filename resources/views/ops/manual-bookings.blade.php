@extends('layouts.app')
@section('title', 'Manual Booking')
@section('heading', 'Manual Booking')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="layers"></i> Manual booking</h1>
            <p class="muted">Create a SEARCHING ride on behalf of a customer. Nearby online drivers can accept it the same way as an app booking.</p>
        </div>
    </div>
    @can('bookings.manage')
        <section class="card">
            <h2>New booking</h2>
            <form method="POST" action="{{ route('ops.manual-bookings.store') }}">
                @csrf
                <div class="filters">
                    <div>
                        <label>Customer</label>
                        <select name="customer_id" required>
                            <option value="">Select customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Pickup</label><input name="pickup_text" required></div>
                    <div><label>Drop</label><input name="drop_text" required></div>
                    <div>
                        <label>Product</label>
                        <select name="product">
                            <option value="LOCAL_CAB">LOCAL_CAB</option>
                            <option value="ONE_WAY">ONE_WAY</option>
                            <option value="RENTAL">RENTAL</option>
                            <option value="AIRPORT">AIRPORT</option>
                        </select>
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="category">
                            <option value="AUTO">AUTO</option>
                            <option value="BIKE">BIKE</option>
                            <option value="MINI">MINI</option>
                            <option value="SEDAN" selected>SEDAN</option>
                            <option value="SUV">SUV</option>
                            <option value="TRAVELLER">TRAVELLER</option>
                        </select>
                    </div>
                    <div>
                        <label>District</label>
                        <select name="district_id">
                            <option value="">—</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Quote (₹)</label><input name="quote_rupees" type="number" min="0" step="1"></div>
                    <div><label>Passenger name</label><input name="passenger_name"></div>
                    <div><label>Passenger phone</label><input name="passenger_phone"></div>
                    <button class="btn" type="submit">Create booking</button>
                </div>
            </form>
        </section>
    @endcan
    <section class="card">
        <h2>Recent bookings</h2>
        <table class="data">
            <thead><tr><th>ID</th><th>Ref</th><th>Status</th><th>Product</th><th>Pickup</th><th>Drop</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('ops.bookings.show', $row->id) }}">{{ $row->id }}</a></td>
                    <td>{{ $row->public_ref }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->product }} / {{ $row->category ?? '' }}</td>
                    <td>{{ $row->pickup_text }}</td>
                    <td>{{ $row->drop_text }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No bookings yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
