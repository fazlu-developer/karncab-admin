@extends('layouts.app')

@section('title', 'Joining letters')
@section('heading', 'Joining letters')

@section('content')
    @php
        $open = 'letterhead';
        if ($edit || $errors->hasAny(['full_name', 'phone', 'email', 'designation_id', 'letter_type', 'joining_on', 'posting', 'offered_rupees', 'address', 'reporting_to'])) {
            $open = 'letter';
        } elseif (request()->filled('q') || request()->filled('letter_type') || request()->filled('designation_id') || request()->filled('page')) {
            $open = 'issued';
        } elseif ($errors->has('name')) {
            $open = 'designations';
        }
    @endphp
    <div class="hero">
        <div>
            <h1><i data-lucide="file-badge"></i> Joining letters</h1>
            <p class="muted">Letterhead, designations, and issued letters are on separate tabs. Preview a letter before you download Word or PDF.</p>
        </div>
    </div>

    <div class="kc-tabs" id="joining-tabs" data-kc-tabs data-kc-open="{{ $open }}">
        <div class="kc-tabbar" role="tablist">
            <button type="button" class="kc-tab {{ $open === 'letterhead' ? 'on' : '' }}" data-kc-tab="letterhead">Letterhead</button>
            <button type="button" class="kc-tab {{ $open === 'designations' ? 'on' : '' }}" data-kc-tab="designations">Designations</button>
            <button type="button" class="kc-tab {{ $open === 'letter' ? 'on' : '' }}" data-kc-tab="letter">{{ $edit ? 'Edit letter' : 'New letter' }}</button>
            <button type="button" class="kc-tab {{ $open === 'issued' ? 'on' : '' }}" data-kc-tab="issued">Issued letters</button>
        </div>

        <div class="kc-panel" data-kc-panel="letterhead" @if ($open !== 'letterhead') hidden @endif>
            <section class="card">
                <h2>Company letterhead</h2>
                <p class="muted">These details print on every offer and appointment letter. Upload a preview image, then save.</p>
                <form method="POST" action="{{ route('joining.letterhead') }}" enctype="multipart/form-data" class="grid-2" style="margin-top:14px">
                    @csrf
                    <input type="hidden" name="legal_name" value="KarnaRide">
                    <div class="field"><label>Name on the letter</label><input name="brand_name" value="{{ old('brand_name', $letterhead->brand_name ?: 'KarnaRide') }}" required></div>
                    <div class="field"><label>CIN</label><input name="cin" value="{{ old('cin', $letterhead->cin) }}"></div>
                    <div class="field"><label>GSTIN</label><input name="gstin" value="{{ old('gstin', $letterhead->gstin) }}" placeholder="Enter GSTIN from the certificate"></div>
                    <div class="field"><label>Powered by</label><input name="powered_by" value="{{ old('powered_by', $letterhead->powered_by) }}"></div>
                    <div class="field" style="grid-column:1 / -1"><label>Registered office</label><input name="office_address" value="{{ old('office_address', $letterhead->office_address) }}" required></div>
                    <div class="field"><label>Signatory name</label><input name="signatory_name" value="{{ old('signatory_name', $letterhead->signatory_name) }}" required></div>
                    <div class="field"><label>Signatory title</label><input name="signatory_title" value="{{ old('signatory_title', $letterhead->signatory_title) }}" required></div>
                    <div class="join-files" style="grid-column:1 / -1">
                        <div class="join-file">
                            <label>Letterhead image (JPG or PNG)</label>
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Letterhead">
                            @else
                                <span class="muted">No letterhead image yet.</span>
                            @endif
                            <input type="file" name="header_image" accept=".jpg,.jpeg,.png">
                            @if ($letterhead->header_image_path)
                                <label class="muted"><input type="checkbox" name="clear_header" value="1"> Use the KarnaRide logo instead</label>
                            @endif
                            <div class="muted">Shown at the top of the letter. Leave the file empty to keep the current image.</div>
                        </div>
                        <div class="join-file">
                            <label>Signature image (JPG or PNG)</label>
                            @if ($letterhead->signature_path)
                                <img src="{{ route('joining.letterhead.file', 'signature') }}" alt="Signature">
                            @else
                                <span class="muted">No signature yet.</span>
                            @endif
                            <input type="file" name="signature" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="join-file">
                            <label>Certificate of Incorporation</label>
                            @include('joining.partials.file-preview', ['path' => $letterhead->coi_path, 'kind' => 'coi', 'label' => 'COI'])
                            @if (! $letterhead->coi_path)<span class="muted">No COI file yet.</span>@endif
                            <input type="file" name="coi" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="join-file">
                            <label>GST certificate</label>
                            @include('joining.partials.file-preview', ['path' => $letterhead->gst_path, 'kind' => 'gst', 'label' => 'GST'])
                            @if (! $letterhead->gst_path)<span class="muted">No GST file yet.</span>@endif
                            <input type="file" name="gst" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                    <div><button class="btn" type="submit">Save letterhead</button></div>
                </form>
            </section>
        </div>

        <div class="kc-panel" data-kc-panel="designations" @if ($open !== 'designations') hidden @endif>
            <section class="card">
                <h2>Designations</h2>
                <form method="POST" action="{{ route('joining.designations.store') }}" class="filters" style="margin:12px 0">
                    @csrf
                    <input type="text" name="name" placeholder="Add a title" required>
                    <button class="btn" type="submit">Add</button>
                </form>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Title</th><th>Show in letters</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($designations as $item)
                            <tr>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('joining.designations.update', $item->id) }}" class="row-edit">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $item->name }}" required>
                                        <label class="muted" style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="active" value="1" @checked($item->active)> Active</label>
                                        <button class="btn ghost" type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">No designations yet. Add the job titles used on letters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="kc-panel" data-kc-panel="letter" @if ($open !== 'letter') hidden @endif>
            <section class="card">
                <h2>{{ $edit ? 'Edit letter '.$edit->public_ref : 'New offer or appointment' }}</h2>
                <form method="POST" action="{{ $edit ? route('joining.update', $edit->id) : route('joining.store') }}" class="grid-2" style="margin-top:14px">
                    @csrf
                    @if ($edit) @method('PUT') @endif
                    <div class="field"><label>Full name</label><input name="full_name" value="{{ old('full_name', $edit->full_name ?? '') }}" required></div>
                    <div class="field"><label>Mobile</label><input name="phone" value="{{ old('phone', $edit->phone ?? '') }}" required></div>
                    <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $edit->email ?? '') }}"></div>
                    <div class="field">
                        <label>Designation</label>
                        <select name="designation_id" required>
                            <option value="">Select designation</option>
                            @foreach ($activeDesignations as $item)
                                <option value="{{ $item->id }}" @selected((string) old('designation_id', $edit->designation_id ?? '') === (string) $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Letter</label>
                        <select name="letter_type" required>
                            <option value="offer" @selected(old('letter_type', $edit->letter_type ?? 'offer') === 'offer')>Offer letter</option>
                            <option value="appointment" @selected(old('letter_type', $edit->letter_type ?? '') === 'appointment')>Appointment letter</option>
                        </select>
                    </div>
                    <div class="field"><label>Joining date</label><input type="date" name="joining_on" value="{{ old('joining_on', isset($edit) ? substr((string) $edit->joining_on, 0, 10) : '') }}" required></div>
                    <div class="field"><label>Posting (state, district, or office)</label><input name="posting" value="{{ old('posting', $edit->posting ?? '') }}" placeholder="Saharsa district" required></div>
                    <div class="field"><label>Amount per month (Rs)</label><input name="offered_rupees" value="{{ old('offered_rupees', $edit->offered_rupees ?? '') }}" inputmode="numeric"></div>
                    <div class="field" style="grid-column:1 / -1"><label>Address on the letter</label><input name="address" value="{{ old('address', $edit->address ?? '') }}"></div>
                    <div class="field" style="grid-column:1 / -1"><label>Reports to</label><input name="reporting_to" value="{{ old('reporting_to', $edit->reporting_to ?? 'Director, KarnaRide') }}"></div>
                    <div class="row-actions">
                        <button class="btn" type="submit">{{ $edit ? 'Update letter' : 'Issue letter' }}</button>
                        @if ($edit)
                            <a class="btn ghost" href="{{ route('joining.index') }}">Cancel</a>
                        @endif
                    </div>
                </form>
            </section>
        </div>

        <div class="kc-panel" data-kc-panel="issued" @if ($open !== 'issued') hidden @endif>
            <section class="card">
                <h2>Issued letters</h2>
                <form class="filters" method="GET" action="{{ route('joining.index') }}" style="margin-top:12px">
                    <input name="q" value="{{ $filters['q'] }}" placeholder="Search name, mobile, email, ref, posting">
                    <select name="letter_type">
                        <option value="">All letters</option>
                        <option value="offer" @selected($filters['letter_type'] === 'offer')>Offer letter</option>
                        <option value="appointment" @selected($filters['letter_type'] === 'appointment')>Appointment letter</option>
                    </select>
                    <select name="designation_id">
                        <option value="">All designations</option>
                        @foreach ($designations as $item)
                            <option value="{{ $item->id }}" @selected((int) $filters['designation_id'] === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <select name="per_page">
                        <option value="10" @selected((int) $filters['per_page'] === 10)>10 / page</option>
                        <option value="25" @selected((int) $filters['per_page'] === 25)>25 / page</option>
                        <option value="50" @selected((int) $filters['per_page'] === 50)>50 / page</option>
                    </select>
                    <button class="btn ghost" type="submit">Search</button>
                    <a class="btn ghost" href="{{ route('joining.index') }}">Reset</a>
                </form>
                <div class="table-wrap" style="margin-top:12px">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Person</th>
                                <th>Designation</th>
                                <th>Letter</th>
                                <th>Joining</th>
                                <th>Seal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($joinings as $row)
                            <tr>
                                <td>{{ $row->public_ref }}</td>
                                <td>
                                    <strong>{{ $row->full_name }}</strong>
                                    <div class="muted">{{ $row->phone }}{{ $row->posting ? ' · '.$row->posting : '' }}</div>
                                </td>
                                <td>{{ $row->designation_name ?: '—' }}</td>
                                <td>{{ $row->letter_type === 'appointment' ? 'Appointment' : 'Offer' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->joining_on)->format('d M Y') }}</td>
                                <td class="muted">{{ $row->document_hash ? substr($row->document_hash, 0, 12).'…' : 'On download' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn" href="{{ route('joining.preview', $row->id) }}">Preview</a>
                                        <a class="btn ghost" href="{{ route('joining.pdf', ['joining' => $row->id, 'download' => 1]) }}">PDF</a>
                                        <a class="btn ghost" href="{{ route('joining.docx', $row->id) }}">Word</a>
                                        <a class="btn ghost" href="{{ route('joining.index', ['edit' => $row->id]) }}">Edit</a>
                                        <form method="POST" action="{{ route('joining.destroy', $row->id) }}" onsubmit="return confirm('Remove this letter?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn ghost" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No letters match this filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:14px">{{ $joinings->links('pagination.admin') }}</div>
            </section>
        </div>
    </div>
@endsection
