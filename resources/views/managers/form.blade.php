@extends('layouts.app')
@section('title', $manager->exists ? 'Edit manager' : 'New manager')
@section('heading', $manager->exists ? 'Edit manager' : 'New manager')
@section('content')
    <section class="card" style="max-width:920px">
        <form method="POST" action="{{ $manager->exists ? route('managers.update', $manager) : route('managers.store') }}">
            @csrf
            @if($manager->exists) @method('PUT') @endif
            <div class="field"><label>Name</label><input name="name" value="{{ old('name', $manager->name) }}" required></div>
            <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $manager->email) }}" required></div>
            <div class="field"><label>Password {{ $manager->exists ? '(optional)' : '' }}</label><input name="password" type="password" {{ $manager->exists ? '' : 'required' }}></div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $manager->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.geo-fields', [
                'stateValue' => (string) old('state_id', $manager->state_id),
                'districtValue' => (string) old('district_id', $manager->district_id),
                'stateLabel' => 'Optional state scope',
                'districtLabel' => 'Optional district scope',
                'emptyState' => 'All states',
                'emptyDistrict' => 'All districts',
            ])
            <h3>Permissions</h3>
            <p class="muted">A manager with fleet_owner.view cannot create fleet owners unless fleet_owner.create is also granted.</p>
            <div class="grid-2">
                @foreach ($catalog as $ability)
                    <label style="display:block;margin:6px 0">
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $granted), true))>
                        {{ $ability }}
                    </label>
                @endforeach
            </div>
            <button class="btn" type="submit">Save</button>
        </form>
    </section>
@endsection
