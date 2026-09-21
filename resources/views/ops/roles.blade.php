@extends('layouts.app')
@section('title', 'Roles')
@section('heading', 'Roles')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="key-round"></i> Roles and permissions</h1>
            <p class="muted">Each operator role has a built-in matrix. Managers can receive extra abilities without changing their role. Super Admin keeps full access.</p>
        </div>
        <a class="btn ghost" href="{{ route('managers.index') }}">Manager accounts</a>
    </div>
    <section class="card">
        <h2>Role matrix</h2>
        <table class="data">
            <thead><tr><th>Role</th><th>Permissions</th></tr></thead>
            <tbody>
            @foreach ($matrix as $row)
                <tr>
                    <td><strong>{{ $row['role'] }}</strong></td>
                    <td class="muted" style="font-size:12px">{{ implode(' · ', $row['permissions']) ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    @forelse ($managers as $manager)
        <section class="card">
            <h2>{{ $manager->name }} <span class="muted">{{ $manager->email }}</span></h2>
            <form method="POST" action="{{ route('ops.roles.extras', $manager) }}">
                @csrf
                @method('PUT')
                <div class="grid-2">
                    @foreach ($catalog as $ability)
                        <label style="display:block;margin:6px 0">
                            <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, $manager->assignedAbilities(), true))>
                            {{ $ability }}
                        </label>
                    @endforeach
                </div>
                <button class="btn" type="submit">Save extra permissions</button>
            </form>
        </section>
    @empty
        <section class="card"><p class="muted">No Manager users yet. Create one under Managers, then grant extra abilities here.</p></section>
    @endforelse
@endsection
