@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="chart-column"></i> Reports &amp; analytics</h1>
            <p class="muted">{{ $note }} Rows stay inside your district, state, or fleet. Export is CSV, Excel, or PDF.</p>
        </div>
    </div>
    <form class="card" method="GET">
        <div class="filters">
            <div><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
            <div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
            <button class="btn" type="submit"><i data-lucide="filter"></i> Apply range</button>
        </div>
    </form>
    @foreach ($groups as $group => $items)
        <section class="card">
            <h2>{{ $group }}</h2>
            <div class="table-wrap">
                <table class="data">
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                <td>
                                    <a href="{{ route('reports.show', ['report' => $item['key'], 'from' => $from, 'to' => $to]) }}">Open</a>
                                    @can('reports.export')
                                        · <a href="{{ route('reports.export', ['report' => $item['key'], 'format' => 'csv', 'from' => $from, 'to' => $to]) }}">CSV</a>
                                        · <a href="{{ route('reports.export', ['report' => $item['key'], 'format' => 'xlsx', 'from' => $from, 'to' => $to]) }}">Excel</a>
                                        · <a href="{{ route('reports.export', ['report' => $item['key'], 'format' => 'pdf', 'from' => $from, 'to' => $to]) }}">PDF</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
