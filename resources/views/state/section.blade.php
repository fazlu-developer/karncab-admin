@extends('layouts.app')

@section('title', str_replace('-', ' ', $section))
@section('heading', ucwords(str_replace('-', ' ', $section)))

@section('content')
    <div class="hero">
        <div>
            <h1>{{ ucwords(str_replace('-', ' ', $section)) }}</h1>
            <p class="muted">{{ $state['stateName'] }} only. Other states are not included in this list or export.</p>
        </div>
    </div>
    @if ($section === 'franchises')
        <section class="card">
            <h3 style="margin-top:0">Add franchise</h3>
            <form method="POST" action="{{ route('state.franchises.store') }}">
                @csrf
                <div class="filters">
                    <div class="field" style="flex:1"><label>Trade name</label><input name="trade_name" required></div>
                    <div class="field"><label>District</label>
                        <select name="district_id" required>
                            @foreach ($districts ?? [] as $district)
                                <option value="{{ $district['id'] }}">{{ $district['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Owner name</label><input name="name" required></div>
                    <div class="field"><label>Owner email</label><input name="email" type="email" required></div>
                    <div class="field"><label>Password</label><input name="password" type="password" required></div>
                    <button class="btn" type="submit">Apply</button>
                </div>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        @forelse ($rows as $row)
                            @foreach (array_keys($row) as $col)
                                <th>{{ $col }}</th>
                            @endforeach
                            @break
                        @empty
                            <th>Empty</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="muted">No records in this state.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
