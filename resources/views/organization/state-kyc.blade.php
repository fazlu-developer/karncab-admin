@extends('layouts.app')

@section('title', 'State Head KYC')
@section('heading', 'State Head KYC')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="landmark"></i> State Head KYC</h1>
            <p class="muted">Documents uploaded while creating a State Head appear here. Choose that account to open them.</p>
        </div>
    </div>

    <section class="card">
        <h2>Upload for a State Head</h2>
        <form method="POST" action="{{ route('organization.state-kyc.store') }}" enctype="multipart/form-data" class="filters" style="align-items:end">
            @csrf
            <div>
                <label>State Head</label>
                <select name="user_id" required>
                    <option value="">Select account</option>
                    @foreach ($heads as $head)
                        <option value="{{ $head->id }}" @selected((int) ($selectedUserId ?? 0) === (int) $head->id)>{{ $head->name }} · {{ $head->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Document type</label>
                <select name="doc_type" required>
                    <option value="PAN">PAN</option>
                    <option value="AADHAAR">Aadhaar</option>
                    <option value="APPOINTMENT">Appointment letter</option>
                    <option value="ADDRESS">Address proof</option>
                    <option value="GST">GST</option>
                    <option value="PHOTO">Photo</option>
                </select>
            </div>
            <div>
                <label>File</label>
                <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
            </div>
            <div>
                <button class="btn" type="submit">Upload KYC</button>
            </div>
        </form>
        @if (($heads ?? []) === [])
            <p class="muted" style="margin-top:10px">Create a State Head user first, then upload KYC here.</p>
        @endif
    </section>

    <section class="card">
        <h2>Submitted files</h2>
        <form method="GET" action="{{ route('organization.state-kyc') }}" class="filters" style="align-items:end;margin-bottom:14px">
            <div>
                <label>Show documents for</label>
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">All State Heads</option>
                    @foreach ($heads as $head)
                        <option value="{{ $head->id }}" @selected((int) ($selectedUserId ?? 0) === (int) $head->id)>{{ $head->name }} · {{ $head->email }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>State Head</th><th>State</th><th>Type</th><th>Status</th><th>When</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($docs as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->user_name ?: 'User #'.$row->user_id }}</strong>
                                <div class="muted">{{ $row->user_email }}</div>
                            </td>
                            <td>{{ $row->state_name ?: '—' }}</td>
                            <td>
                                {{ $row->doc_type }}
                                @if (! empty($row->original_name))
                                    <div class="muted">{{ $row->original_name }}</div>
                                @endif
                            </td>
                            <td><span class="pill muted">{{ $row->status }}</span></td>
                            <td>{{ $row->created_at }}</td>
                            <td class="row-actions" style="min-width:260px">
                                @php
                                    $fileUrl = route('organization.state-kyc.file', $row->id);
                                    $ext = strtolower(pathinfo((string) ($row->path ?? ''), PATHINFO_EXTENSION));
                                @endphp
                                @if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true))
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $fileUrl }}" alt="{{ $row->doc_type }}" style="width:160px;height:110px;object-fit:cover;border-radius:8px;display:block;margin-bottom:8px">
                                    </a>
                                @elseif ($ext === 'pdf')
                                    <iframe src="{{ $fileUrl }}" title="{{ $row->doc_type }}" style="width:220px;height:140px;border:0;border-radius:8px;display:block;margin-bottom:8px"></iframe>
                                @endif
                                <a class="btn ghost" href="{{ $fileUrl }}" target="_blank" rel="noopener">Open</a>
                                <form method="POST" action="{{ route('organization.state-kyc.replace', $row->id) }}" enctype="multipart/form-data" style="margin-top:8px">
                                    @csrf
                                    <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                                    <button class="btn ghost" type="submit">Replace</button>
                                </form>
                                <form method="POST" action="{{ route('organization.state-kyc.destroy', $row->id) }}" onsubmit="return confirm('Delete this KYC file?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">Delete</button>
                                </form>
                                @can('kyc.approve')
                                    <form method="POST" action="{{ route('organization.state-kyc.review', $row->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="verified">
                                        <button class="btn" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('organization.state-kyc.review', $row->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn ghost" type="submit">Reject</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ ($selectedUserId ?? 0) > 0 ? 'No documents uploaded for this State Head yet.' : 'No State Head KYC files yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
