@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'New user')
@section('heading', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $user->exists ? 'Edit user' : 'Create user' }}</h1>
            <p class="muted">Roles: Admin, Manager, State Head, District Head, Fleet Owner, Driver, Customer. Super Admin is not assigned from this form.</p>
        </div>
        <a class="btn ghost" href="{{ route('users.index') }}">Back to list</a>
    </div>
    <section class="card" style="max-width:820px">
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="field">
                <label>Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Phone</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}">
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Role</label>
                @if ($user->exists && $user->role === 'SUPER_ADMIN')
                    <input type="hidden" name="role" value="SUPER_ADMIN">
                    <p>Super Admin accounts keep that role. It is not offered for other users.</p>
                @else
                    <select name="role">
                        @foreach ($roles as $role)
                            @continue($role === 'SUPER_ADMIN')
                            <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ str_replace('_', ' ', $role) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach (['ACTIVE','PENDING','SUSPENDED'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ $user->exists ? 'New password (optional)' : 'Password' }}</label>
                <input name="password" type="password" {{ $user->exists ? '' : 'required' }}>
                <p class="muted">For State Head, District Head, Manager and Admin this is the admin login password.</p>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            @include('partials.geo-fields', [
                'stateValue' => (string) old('state_id', $user->state_id),
                'districtValue' => (string) old('district_id', $user->district_id),
            ])
            <p class="muted">State Head needs one state. District Head needs one district. The selected place is assigned when you save.</p>
            <h3>Finance and system modules</h3>
            <p class="muted">Super Admin always has these. Tick a module to show it for this Manager or Admin. State Head and District Head never see them.</p>
            <div class="grid-2">
                @foreach (\App\Platform\PrivilegedModules::LABELS as $ability => $label)
                    <label style="display:block;margin:6px 0">
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $granted), true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <h3>Manager and Admin permissions</h3>
            <p class="muted">Tick the modules this account should see. A State Head always has Dashboard and All users, never States or Districts, and every other module only after you tick it. A Manager sees Dashboard plus the modules you tick. If a Manager has no ticks yet, their existing manager menu stays.</p>
            <div class="grid-2">
                @foreach ($permissionCatalog as $ability)
                    @continue(isset(\App\Platform\PrivilegedModules::LABELS[$ability]))
                    <label style="display:block;margin:6px 0">
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $granted), true))>
                        {{ $ability }}
                    </label>
                @endforeach
            </div>
            <p class="muted">State Head and District Head KYC uploaded here appears on their KYC page.</p>
            <div class="field"><label>PAN</label><input type="file" name="kyc_pan" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <div class="field"><label>Aadhaar</label><input type="file" name="kyc_aadhaar" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <div class="field"><label>Appointment letter</label><input type="file" name="kyc_appointment" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <div class="field"><label>Address proof</label><input type="file" name="kyc_address" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <button class="btn" type="submit"><i data-lucide="save"></i> Save</button>
        </form>
    </section>
@endsection
