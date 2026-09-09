@extends('layouts.app')

@section('title', $label)
@section('heading', $label)

@section('content')
    <p><a href="{{ route('customer.dashboard') }}"><i data-lucide="arrow-left"></i> Customer home</a></p>
    <div class="hero">
        <div>
            <h1>{{ $label }}</h1>
            <p class="muted">{{ $catalog['note'] }}</p>
        </div>
    </div>

    @if ($section === 'profile')
        <section class="card">
            <form method="POST" action="{{ route('customer.profile') }}">
                @csrf @method('PATCH')
                <div class="filters">
                    <div><label>Name</label><input name="name" value="{{ $profile['name'] }}"></div>
                    <div><label>Phone</label><input name="phone" value="{{ $profile['phone'] }}"></div>
                    <div><label>Address</label><input name="last_address" value="{{ $profile['address'] }}"></div>
                </div>
                <button class="btn" type="submit">Save</button>
            </form>
        </section>
    @elseif ($section === 'emergency')
        <section class="card">
            <form method="POST" action="{{ route('customer.emergency') }}">
                @csrf @method('PATCH')
                <div class="filters">
                    <div><label>Name</label><input name="emergency_name" value="{{ $emergency['name'] }}"></div>
                    <div><label>Phone</label><input name="emergency_phone" value="{{ $emergency['phone'] }}"></div>
                </div>
                <button class="btn" type="submit">Save</button>
            </form>
        </section>
    @elseif ($section === 'family')
        <section class="card">
            <p class="muted">Book for another person from a saved family member. You remain the paying booker.</p>
            <form method="POST" action="{{ route('customer.family.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Name</label><input name="name" required></div>
                    <div><label>Mobile</label><input name="phone" required></div>
                    <div><label>Relation</label><input name="relation"></div>
                </div>
                <button class="btn" type="submit">Add</button>
            </form>
            @foreach ($family as $member)
                <p>{{ $member['name'] }} · {{ $member['phone'] }}</p>
            @endforeach
        </section>
    @elseif ($section === 'places')
        <section class="card">
            <form method="POST" action="{{ route('customer.places.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Title</label><input name="title" required></div>
                    <div><label>Address</label><input name="address" required></div>
                </div>
                <button class="btn" type="submit">Save location</button>
            </form>
            @foreach ($places as $place)
                <p>{{ $place['title'] }} — {{ $place['address'] }}</p>
            @endforeach
        </section>
    @elseif ($section === 'wallet')
        <section class="card">
            <p>{{ ($data['wallet'] ?? null) ? '₹'.number_format($data['wallet']['balanceRupees'], 2) : 'No wallet yet.' }}</p>
        </section>
    @elseif ($section === 'complaints')
        <section class="card">
            <form method="POST" action="{{ route('customer.complaints.store') }}" enctype="multipart/form-data">
                @csrf
                <div><label>Subject</label><input name="subject" required minlength="4"></div>
                <div><label>Description</label><input name="description"></div>
                <div><label>Category</label><input name="category" placeholder="booking"></div>
                <div><label>Booking ID</label><input name="booking_id" type="number"></div>
                <div><label>Priority</label><input name="priority" placeholder="medium"></div>
                <div><label>Attachment</label><input type="file" name="file"></div>
                <button class="btn" type="submit">Open complaint</button>
            </form>
            @foreach (($data['rows'] ?? []) as $row)
                <p>{{ $row['ticketId'] ?? $row['publicRef'] }} · {{ $row['subject'] }} · {{ $row['status'] ?? '' }}</p>
            @endforeach
        </section>
    @elseif ($section === 'ratings')
        <section class="card">
            <form method="POST" action="{{ route('customer.ratings.store') }}">
                @csrf
                <div class="filters">
                    <div><label>Booking ID</label><input name="booking_id" type="number" required></div>
                    <div><label>Stars</label><input name="stars" type="number" min="1" max="5" required></div>
                </div>
                <button class="btn" type="submit">Rate</button>
            </form>
            @foreach (($data['rows'] ?? []) as $row)
                <p>{{ $row['publicRef'] }} · {{ $row['stars'] }}★</p>
            @endforeach
        </section>
    @else
        <section class="card">
            @php $rows = $data['rows'] ?? []; @endphp
            @forelse ($rows as $row)
                <p>{{ $row['publicRef'] ?? $row['title'] ?? $row['code'] ?? $row['name'] ?? json_encode($row) }}
                    @if(isset($row['status'])) · {{ $row['status'] }}@endif
                    @if(isset($row['cta'])) — {{ $row['cta'] }}@endif
                </p>
            @empty
                <p class="muted">Nothing here yet.</p>
            @endforelse
        </section>
    @endif
@endsection
