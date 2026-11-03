@extends('layouts.app')

@section('title', $row->public_ref)
@section('heading', $title)

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="file-badge"></i> {{ $title }}</h1>
            <p class="muted">{{ $row->public_ref }} · {{ $row->full_name }} · {{ $designation }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('joining.pdf', ['joining' => $row->id, 'download' => 1]) }}">Download PDF</a>
            <a class="btn" href="{{ route('joining.docx', $row->id) }}">Download Word</a>
            <a class="btn ghost" href="{{ route('joining.index', ['edit' => $row->id]) }}">Edit</a>
            <a class="btn ghost" href="{{ route('joining.index') }}">All letters</a>
        </div>
    </div>

    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif

    <article class="card" style="max-width:820px;background:#fff">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:3px solid #1b7a32;padding-bottom:12px">
            <div>
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="KarnaRide" style="max-width:240px;max-height:78px">
                @else
                    <strong style="color:#1b7a32;font-size:22px">KarnaRide</strong>
                @endif
            </div>
            <div style="text-align:right">
                <div style="color:#1b7a32;font-weight:700">{{ $title }}</div>
                <div class="muted">Ref {{ $row->public_ref }}</div>
                <div class="muted">Issued {{ $issued }}</div>
            </div>
        </div>
        <p style="margin-top:18px"><strong>Dear {{ $row->full_name }},</strong></p>
        @foreach ($paragraphs as $parts)
            <p style="line-height:1.55">@php
                $html = '';
                $first = true;
                foreach ($parts as [$text, $bold]) {
                    $piece = (string) $text;
                    if (! $first && ! preg_match('/^\s|^[.,;:]/', $piece)) {
                        $piece = ' '.$piece;
                    }
                    $first = false;
                    $html .= $bold ? '<strong>'.e($piece).'</strong>' : e($piece);
                }
                echo $html;
            @endphp</p>
        @endforeach
        <p>With warm regards,</p>
        <p style="color:#1b7a32"><strong>For KarnaRide</strong></p>
        @if ($showSignature)
            <img src="{{ route('joining.letterhead.file', 'signature') }}" alt="Signature" style="max-width:180px;max-height:70px;background:#fff">
        @endif
        <p style="margin-bottom:0"><strong>{{ $head->signatory_name }}</strong><br><span class="muted">{{ $head->signatory_title }}, KarnaRide</span></p>
        <p class="muted" style="margin-top:18px;border-top:1px solid #e5e7eb;padding-top:10px">
            KarnaRide · Ride · Travel · Parcel<br>
            {{ $head->office_address }} · Powered by {{ $head->powered_by }}<br>
            Digital seal {{ $hash }}
        </p>
    </article>
@endsection
