@extends('layouts.app')

@section('title', $report['label'])
@section('heading', $report['label'])

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="chart-column"></i> {{ $report['label'] }}</h1>
            <p class="muted">{{ $report['from'] }} to {{ $report['to'] }}. Role {{ $report['scope']['role'] }} — other territories are excluded.</p>
        </div>
        <div>
            <a class="btn ghost" href="{{ route('reports.index', request()->query()) }}">All reports</a>
            @can('reports.export')
                <a class="btn ghost" href="{{ route('reports.export', ['report' => $report['key'], 'format' => 'csv'] + request()->query()) }}">CSV</a>
                <a class="btn ghost" href="{{ route('reports.export', ['report' => $report['key'], 'format' => 'xlsx'] + request()->query()) }}">Excel</a>
                <a class="btn ghost" href="{{ route('reports.export', ['report' => $report['key'], 'format' => 'pdf'] + request()->query()) }}">PDF</a>
            @endcan
        </div>
    </div>
    <form class="card" method="GET">
        <div class="filters">
            <div><label>From</label><input type="date" name="from" value="{{ $report['from'] }}"></div>
            <div><label>To</label><input type="date" name="to" value="{{ $report['to'] }}"></div>
            <button class="btn" type="submit"><i data-lucide="filter"></i> Filter</button>
        </div>
    </form>
    <section class="card">
        <p class="muted">{{ $report['totals']['rows'] ?? 0 }} rows in scope.</p>
        @if (($report['rows'] ?? []) === [])
            <p class="muted">No rows for this range and role.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            @foreach (array_keys($report['rows'][0]) as $col)
                                <th>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>
                                @foreach ($row as $value)
                                    <td>{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
