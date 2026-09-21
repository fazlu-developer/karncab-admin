@extends('layouts.app')

@section('title', 'Customer FAQ')
@section('heading', 'Support content')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="circle-help"></i> FAQ & support copy</h1>
            <p class="muted">These answers appear in the customer app Support screen. Tickets and safety desk still open from the same app section.</p>
        </div>
    </div>
    <section class="card">
        <h2>Add FAQ</h2>
        <form method="POST" action="{{ route('support.faqs.store') }}">
            @csrf
            <div class="filters">
                <div><label>Question</label><input name="question" required></div>
                <div><label>Audience</label>
                    <select name="audience">
                        <option value="customer">Customer app</option>
                        <option value="all">All apps</option>
                        <option value="driver">Driver app</option>
                    </select>
                </div>
            </div>
            <p><label>Answer</label><textarea name="answer" rows="4" required></textarea></p>
            <button class="btn" type="submit">Publish</button>
        </form>
    </section>
    <section class="card">
        <h2>Published</h2>
        @forelse ($faqs as $row)
            <p>
                <strong>{{ $row->question }}</strong>
                · {{ $row->audience }}
                · {{ $row->active ? 'active' : 'hidden' }}
                <form method="POST" action="{{ route('support.faqs.update', $row->id) }}" style="display:inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="active" value="{{ $row->active ? 0 : 1 }}">
                    <button class="btn ghost" type="submit">{{ $row->active ? 'Hide' : 'Show' }}</button>
                </form>
            </p>
            <p class="muted">{{ $row->answer }}</p>
        @empty
            <p class="muted">No FAQs yet. Add one above.</p>
        @endforelse
    </section>
@endsection
