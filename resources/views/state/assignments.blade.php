@extends('layouts.app')

@section('title', 'State assignment')
@section('heading', 'State assignment')

@section('content')
    <div class="hero">
        <div>
            <h1>Assign State Heads</h1>
            <p class="muted">Each State Head can operate only the selected state.</p>
        </div>
    </div>
    <section class="card">
        <table class="data">
            <thead><tr><th>Operator</th><th>Email</th><th>Current state</th><th></th></tr></thead>
            <tbody>
            @forelse ($operators as $operator)
                <tr>
                    <td>{{ $operator->name }}</td>
                    <td>{{ $operator->email }}</td>
                    <td>{{ $operator->state_id ?: '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('state.assignments.update', $operator) }}" class="filters">
                            @csrf @method('PUT')
                            <select name="state_id" required>
                                <option value="">Select state</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state->id }}" @selected((int) $operator->state_id === (int) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn" type="submit">Assign</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No State Head operators yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
