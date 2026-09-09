@extends('layouts.app')

@section('title', 'Wallet')
@section('heading', 'Wallet')

@section('content')
    <p><a href="{{ route('wallets.index') }}"><i data-lucide="arrow-left"></i> All wallets</a></p>
    <div class="hero">
        <div>
            <h1>{{ $wallet['ownerType'] }} #{{ $wallet['walletId'] }}</h1>
            <p class="muted">User {{ $wallet['userId'] }} · {{ $wallet['ownerName'] }} · ₹{{ number_format($wallet['balanceRupees'], 2) }}</p>
        </div>
    </div>
    <section class="card">
        <h2>Ledger</h2>
        @include('wallets.partials.ledger-table', ['rows' => $wallet['ledger'] ?? []])
    </section>
@endsection
