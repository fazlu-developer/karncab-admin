@extends('layouts.app')

@section('title', 'Fleet')
@section('heading', 'Fleet')

@section('content')
    <section class="card">
        <h1>No fleet assigned</h1>
        <p class="muted">This Fleet Owner account is not linked to a fleet_owners record. Ask an admin to set fleet_owner_id.</p>
    </section>
@endsection
