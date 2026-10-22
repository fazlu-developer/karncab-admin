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
        @include('organization.partials.kyc-files', [
            'docs' => $docs,
            'fileRoute' => 'organization.district-kyc.file',
            'replaceRoute' => 'organization.district-kyc.replace',
            'destroyRoute' => 'organization.district-kyc.destroy',
            'reviewRoute' => 'organization.district-kyc.review',
            'placeKey' => 'district_name',
            'empty' => 'No District Head KYC files yet.',
        ])
    </section>
@endsection
