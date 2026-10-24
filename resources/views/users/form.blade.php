@extends('layouts.app')

@section('title', $user->exists ? 'Edit user' : 'New user')
@section('heading', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $user->exists ? 'Edit user' : 'Create user' }}</h1>
            <p class="muted">Pick a role first. Only the fields and menu access that role needs will show.</p>
        </div>
        <a class="btn ghost" href="{{ route('users.index') }}">Back to list</a>
    </div>
    <section class="card" style="max-width:920px">
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="form-grid">
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
                        <p>Super Admin accounts keep that role.</p>
                    @else
                        <select name="role" id="user-role">
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
                    @error('password') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div id="role-geo">
                @include('partials.geo-fields', [
                    'stateValue' => (string) old('state_id', $user->state_id),
                    'districtValue' => (string) old('district_id', $user->district_id),
                ])
                <p class="muted" id="territory-hint">State Head needs one state. District Head and Fleet Owner need one district.</p>
            </div>
            <div id="role-privileged">
                <h3>Finance and system modules</h3>
                <p class="muted">Super Admin always has these. Tick a module to show it for this Admin or Manager.</p>
                <div class="perm-grid">
                    @foreach (\App\Platform\PrivilegedModules::LABELS as $ability => $label)
                        <label>
                            <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $granted), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div id="role-permissions">
                <h3>Menu access</h3>
                <p class="muted" id="permission-hint">Tick the sidebar modules this login should see. State Head always has Dashboard and All users. Website enquiries, Old users and Withdraw requests stay with Super Admin / Admin.</p>
                <div class="perm-grid">
                    @foreach ($navPermissions ?? [] as $ability => $label)
                        <label>
                            <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $granted), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div id="role-kyc">
                <h3>KYC files</h3>
                <p class="muted">State Head and District Head KYC uploaded here appears on their KYC page.</p>
                <div class="form-grid">
                    <div class="field"><label>PAN</label><input type="file" name="kyc_pan" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                    <div class="field"><label>Aadhaar</label><input type="file" name="kyc_aadhaar" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                    <div class="field"><label>Appointment letter</label><input type="file" name="kyc_appointment" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                    <div class="field"><label>Address proof</label><input type="file" name="kyc_address" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
                </div>
            </div>
            <button class="btn" type="submit"><i data-lucide="save"></i> Save</button>
        </form>
    </section>
    <script>
        (function () {
            var role = document.getElementById('user-role');
            var state = document.querySelector('[name="state_id"]');
            var district = document.querySelector('[name="district_id"]');
            var hint = document.getElementById('territory-hint');
            var geo = document.getElementById('role-geo');
            var privileged = document.getElementById('role-privileged');
            var perms = document.getElementById('role-permissions');
            var kyc = document.getElementById('role-kyc');
            if (!role) return;
            function show(el, on) {
                if (el) el.style.display = on ? '' : 'none';
            }
            function mark(select, required, text) {
                if (!select) return;
                select.required = required;
                var field = select.closest('.field');
                var label = field ? field.querySelector('label') : null;
                if (label) label.textContent = required ? text + ' *' : text;
            }
            function sync() {
                var value = role.value;
                var stateHead = value === 'STATE_HEAD';
                var districtHead = value === 'DISTRICT_HEAD';
                var fleet = value === 'FLEET_OWNER';
                var operator = value === 'ADMIN' || value === 'MANAGER';
                var head = stateHead || districtHead;
                show(geo, stateHead || districtHead || fleet || operator);
                show(privileged, operator);
                show(perms, operator || head);
                show(kyc, head);
                mark(state, stateHead || districtHead || fleet, 'State');
                mark(district, districtHead || fleet, 'District');
                if (hint) {
                    hint.textContent = districtHead
                        ? 'District is required. This login can see only that district’s fleet owners and bookings.'
                        : (stateHead
                            ? 'State is required. This login can see every fleet owner and district in that state.'
                            : (fleet
                                ? 'State and district are required for a fleet owner.'
                                : 'Assign territory only when this role works in one state or district.'));
                }
            }
            role.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
