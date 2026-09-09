@extends('layouts.app')

@section('title', 'My Karna Cab')
@section('heading', 'Customer')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $profile['name'] }}</h1>
            <p class="muted">{{ $catalog['note'] }}</p>
        </div>
    </div>
    <div class="kpis">
        @foreach ([
            [count($upcoming), 'Upcoming', 'calendar-clock'],
            [count($scheduled), 'Scheduled', 'calendar'],
            [$activeRide ? 1 : 0, 'Active', 'navigation'],
            [count($history), 'History', 'history'],
        ] as [$n, $label, $icon])
            <div class="kpi">
                <div class="label"><i data-lucide="{{ $icon }}"></i> {{ $label }}</div>
                <b>{{ $n }}</b>
            </div>
        @endforeach
    </div>

    <section class="card">
        <h2>Active ride</h2>
        @if ($activeRide)
            <p><strong>{{ $activeRide['publicRef'] }}</strong> · {{ $activeRide['status'] }} · {{ $activeRide['pickup'] }} → {{ $activeRide['drop'] }}</p>
            @if (!empty($verify['driver']))
                <p>Driver {{ $verify['driver']['name'] }} · {{ $verify['vehicle']['number'] ?? '' }} · {{ $verify['vehicle']['type'] ?? '' }} · {{ $verify['driver']['rating'] }}★ · {{ $verify['driver']['verificationStatus'] }}</p>
                <p>Start OTP {{ $verify['otp']['start'] ?? '—' }} @if(!empty($verify['otp']['end'])) · End OTP {{ $verify['otp']['end'] }}@endif</p>
            @endif
            <form method="POST" action="{{ route('customer.sos') }}" style="display:inline">
                @csrf
                <input type="hidden" name="kind" value="emergency">
                <input type="hidden" name="booking_id" value="{{ $activeRide['id'] }}">
                <button class="btn" type="submit">SOS</button>
            </form>
            <form method="POST" action="{{ route('customer.share') }}" style="display:inline">
                @csrf
                <input type="hidden" name="booking_id" value="{{ $activeRide['id'] }}">
                <button class="btn ghost" type="submit">Share trip</button>
            </form>
        @else
            <p class="muted">No ride in progress.</p>
        @endif
    </section>

    <section class="card">
        <h2>Profile</h2>
        <form method="POST" action="{{ route('customer.profile') }}">
            @csrf
            @method('PATCH')
            <div class="filters">
                <div><label>Name</label><input name="name" value="{{ $profile['name'] }}"></div>
                <div><label>Phone</label><input name="phone" value="{{ $profile['phone'] }}"></div>
                <div><label>Address</label><input name="last_address" value="{{ $profile['address'] }}"></div>
            </div>
            <button class="btn" type="submit">Save profile</button>
        </form>
    </section>

    <section class="card">
        <h2>Emergency contact</h2>
        <form method="POST" action="{{ route('customer.emergency') }}">
            @csrf
            @method('PATCH')
            <div class="filters">
                <div><label>Name</label><input name="emergency_name" value="{{ $emergency['name'] }}"></div>
                <div><label>Phone</label><input name="emergency_phone" value="{{ $emergency['phone'] }}"></div>
            </div>
            <button class="btn ghost" type="submit">Save contact</button>
        </form>
        <form method="POST" action="{{ route('customer.contacts.store') }}">
            @csrf
            <div class="filters">
                <div><label>Add another name</label><input name="name" required></div>
                <div><label>Mobile</label><input name="phone" required></div>
                <div><label>Relation</label><input name="relation"></div>
            </div>
            <button class="btn ghost" type="submit">Add emergency contact</button>
        </form>
        @foreach (($contacts ?? []) as $contact)
            <p>{{ $contact['name'] }} · {{ $contact['phone'] }}
                @if(!empty($contact['id']))
                    <form method="POST" action="{{ route('customer.contacts.destroy', $contact['id']) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button class="btn ghost" type="submit">Remove</button>
                    </form>
                @endif
            </p>
        @endforeach
    </section>

    <section class="card">
        <h2>Family booking / book for another person</h2>
        <p class="muted">Add a passenger. The booker still pays; the driver only sees the passenger after assignment.</p>
        <form method="POST" action="{{ route('customer.family.store') }}">
            @csrf
            <div class="filters">
                <div><label>Name</label><input name="name" required></div>
                <div><label>Mobile</label><input name="phone" required></div>
                <div><label>Relation</label><input name="relation"></div>
            </div>
            <button class="btn ghost" type="submit">Add family member</button>
        </form>
        @foreach ($family as $member)
            <p>{{ $member['name'] }} · {{ $member['phone'] }}
                <form method="POST" action="{{ route('customer.family.destroy', $member['id']) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button class="btn ghost" type="submit">Remove</button>
                </form>
            </p>
        @endforeach
    </section>

    <section class="card">
        <h2>Saved locations</h2>
        <form method="POST" action="{{ route('customer.places.store') }}">
            @csrf
            <div class="filters">
                <div><label>Title</label><input name="title" required></div>
                <div><label>Address</label><input name="address" required></div>
            </div>
            <button class="btn ghost" type="submit">Save location</button>
        </form>
        @foreach ($places as $place)
            <p>{{ $place['title'] }} — {{ $place['address'] }}</p>
        @endforeach
    </section>

    @foreach ([
        ['Upcoming bookings', $upcoming],
        ['Scheduled rides', $scheduled],
        ['Booking history', $history],
        ['Corporate bookings', $corporate],
        ['Booking for another person', $guest],
    ] as [$title, $rows])
        <section class="card">
            <h2>{{ $title }}</h2>
            @forelse ($rows as $row)
                <p>{{ $row['publicRef'] }} · {{ $row['status'] }} · {{ $row['pickup'] }} → {{ $row['drop'] }}@if($row['bookedForOther']) · for {{ $row['passengerName'] ?? 'passenger' }}@endif</p>
            @empty
                <p class="muted">None.</p>
            @endforelse
        </section>
    @endforeach

    <section class="card">
        <h2>Parcel history</h2>
        @forelse ($parcels as $row)
            <p>{{ $row['publicRef'] }} · {{ $row['status'] }}</p>
        @empty
            <p class="muted">No parcels.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Travel bookings</h2>
        @forelse ($travel as $row)
            <p>{{ $row['publicRef'] }} · {{ $row['status'] }}</p>
        @empty
            <p class="muted">No travel bookings.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Bulk bookings</h2>
        @forelse ($bulk as $row)
            <p>{{ $row['publicRef'] }} · {{ $row['status'] }}</p>
        @empty
            <p class="muted">No bulk bookings.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>Wallet</h2>
        <p>{{ $wallet ? '₹'.number_format($wallet['balanceRupees'], 2) : 'No wallet yet.' }}</p>
    </section>
    <section class="card">
        <h2>Coupons & offers</h2>
        @forelse ($offers as $offer)
            <p>{{ $offer['title'] ?? $offer['code'] }} — {{ $offer['cta'] ?? $offer['code'] }}</p>
        @empty
            <p class="muted">No offers right now.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Invoices</h2>
        @forelse ($invoices as $row)
            <p>{{ $row['publicRef'] }} · {{ $row['status'] }}</p>
        @empty
            <p class="muted">No invoices.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Notifications</h2>
        @forelse ($notifications as $row)
            <p>{{ $row['title'] }} — {{ $row['body'] }}</p>
        @empty
            <p class="muted">Inbox is empty.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Ratings</h2>
        <form method="POST" action="{{ route('customer.ratings.store') }}">
            @csrf
            <div class="filters">
                <div><label>Booking ID</label><input name="booking_id" type="number" required></div>
                <div><label>Stars</label><input name="stars" type="number" min="1" max="5" required></div>
                <div><label>Comment</label><input name="comment"></div>
            </div>
            <button class="btn ghost" type="submit">Rate completed ride</button>
        </form>
        @foreach ($ratings as $row)
            <p>{{ $row['publicRef'] }} · {{ $row['stars'] }}★</p>
        @endforeach
    </section>
    <section class="card">
        <h2>Complaints</h2>
        <form method="POST" action="{{ route('customer.complaints.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="filters">
                <div><label>Subject</label><input name="subject" required minlength="4"></div>
                <div><label>Description</label><input name="description"></div>
                <div><label>Category</label><input name="category" placeholder="booking"></div>
                <div><label>Booking ID</label><input name="booking_id" type="number"></div>
                <div><label>Priority</label><input name="priority" placeholder="medium"></div>
                <div><label>Attachment</label><input type="file" name="file"></div>
            </div>
            <button class="btn ghost" type="submit">Open complaint</button>
        </form>
        @foreach ($complaints as $row)
            <p>{{ $row['ticketId'] ?? $row['publicRef'] }} · {{ $row['subject'] }} · {{ $row['status'] }} · {{ $row['priority'] ?? '' }}</p>
        @endforeach
    </section>
@endsection
