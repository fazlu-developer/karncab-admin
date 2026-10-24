@extends('layouts.app')
@section('title', 'Territory contacts')
@section('heading', 'Territory contacts')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="phone"></i> Territory contacts</h1>
            <p class="muted">Public support details for each State Head and District Head. Customer and driver apps show the District Head first, then the State Head. Phone, email and address stay as you type them. App labels switch to Hindi with the app language.</p>
        </div>
    </div>

    <section class="card">
        <h2>Add a head</h2>
        <form method="POST" action="{{ route('territory-contacts.store') }}" enctype="multipart/form-data">
            @csrf
            @include('territory-contacts.form', ['row' => null, 'stateHeads' => $stateHeads, 'districtHeads' => $districtHeads])
            <button class="btn" type="submit">Save contact</button>
        </form>
    </section>

    <section class="card">
        <h2>Saved contacts</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Area</th>
                        <th>Support</th>
                        <th>Connect</th>
                        <th>Address</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @if ($row['photoUrl'])<img src="{{ $row['photoUrl'] }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px">@endif
                            <strong>{{ $row['name'] }}</strong>
                            <div class="muted">{{ $row['roleLabel'] }}@if ($row['phone']) · {{ $row['phone'] }}@endif</div>
                        </td>
                        <td>{{ $row['districtName'] ?: $row['stateName'] }}@if ($row['districtName'] && $row['stateName'])<div class="muted">{{ $row['stateName'] }}</div>@endif</td>
                        <td>{{ $row['supportPhone'] ?: '—' }}<div class="muted">{{ $row['email'] }}</div></td>
                        <td>{{ $row['connectPhone'] ?: '—' }}</td>
                        <td>{{ $row['address'] ?: '—' }}@if ($row['placeName'])<div class="muted">{{ $row['placeName'] }}</div>@endif</td>
                        <td>
                            <details>
                                <summary>Edit</summary>
                                <form method="POST" action="{{ route('territory-contacts.update', $row['id']) }}" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    @include('territory-contacts.form', ['row' => $row, 'stateHeads' => $stateHeads, 'districtHeads' => $districtHeads])
                                    <button class="btn" type="submit">Update</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('territory-contacts.destroy', $row['id']) }}" onsubmit="return confirm('Remove this public contact?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn ghost" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No territory contacts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
