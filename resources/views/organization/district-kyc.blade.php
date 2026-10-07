@extends('layouts.app')

@section('title', 'District Head KYC')
@section('heading', 'District Head KYC')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="file-badge"></i> District Head KYC</h1>
            <p class="muted">Admin and State Head upload PAN, Aadhaar, appointment letter and address proof against the District Head account — not against the operator login.</p>
        </div>
    </div>

    <section class="card">
        <h2>Upload for a District Head</h2>
        <form method="POST" action="{{ route('organization.district-kyc.store') }}" enctype="multipart/form-data" class="filters" style="align-items:end">
            @csrf
            @if ($canUploadForOthers ?? true)
                <div>
                    <label>District Head</label>
                    <select name="user_id" required>
                        <option value="">Select account</option>
                        @foreach ($heads as $head)
                            <option value="{{ $head->id }}">{{ $head->name }} · {{ $head->email }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
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
        @if (($canUploadForOthers ?? true) && ($heads ?? []) === [])
            <p class="muted" style="margin-top:10px">Create a District Head first (State workspace or Users), then upload KYC here.</p>
        @endif
    </section>

    <section class="card">
        <h2>Submitted files</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>District Head</th><th>District</th><th>Type</th><th>Status</th><th>When</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($docs as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->user_name ?: 'User #'.$row->user_id }}</strong>
                                <div class="muted">{{ $row->user_email }}</div>
                            </td>
                            <td>{{ $row->district_name ?: '—' }}</td>
                            <td>{{ $row->doc_type }}</td>
                            <td><span class="pill muted">{{ $row->status }}</span></td>
                            <td>{{ $row->created_at }}</td>
                            <td class="row-actions">
                                <a class="btn ghost" href="{{ route('organization.district-kyc.file', $row->id) }}" target="_blank">Open</a>
                                @can('kyc.approve')
                                    <form method="POST" action="{{ route('organization.district-kyc.review', $row->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="verified">
                                        <button class="btn" type="submit">Verify</button>
                                    </form>
                                    <form method="POST" action="{{ route('organization.district-kyc.review', $row->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn danger" type="submit">Reject</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No District Head KYC files yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
