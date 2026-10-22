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
        @include('organization.partials.kyc-files', [
            'docs' => $docs,
            'fileRoute' => 'organization.state-kyc.file',
            'replaceRoute' => 'organization.state-kyc.replace',
            'destroyRoute' => 'organization.state-kyc.destroy',
            'reviewRoute' => 'organization.state-kyc.review',
            'placeKey' => 'state_name',
            'empty' => ($selectedUserId ?? 0) > 0 ? 'No documents uploaded for this State Head yet.' : 'No State Head KYC files yet.',
        ])
    </section>
@endsection
