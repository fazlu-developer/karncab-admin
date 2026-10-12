@extends('layouts.app')

@section('title', 'FAQ')
@section('heading', 'Support content')

@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="circle-help"></i> FAQ & support copy</h1>
            <p class="muted">English and Hindi answers. When the app language is Hindi, question_hi / answer_hi are shown.</p>
        </div>
    </div>
    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif
    <section class="card">
        <h2>Add FAQ</h2>
        <form method="POST" action="{{ route('support.faqs.store') }}">
            @csrf
            <div class="filters">
                <div><label>Question (English)</label><input name="question" required></div>
                <div><label>Question (Hindi)</label><input name="question_hi"></div>
                <div><label>Audience</label>
                    <select name="audience">
                        <option value="customer">Customer app</option>
                        <option value="driver">Partner app</option>
                        <option value="all">All apps</option>
                    </select>
                </div>
            </div>
            <p><label>Answer (English)</label><textarea name="answer" rows="3" required></textarea></p>
            <p><label>Answer (Hindi)</label><textarea name="answer_hi" rows="3"></textarea></p>
            <button class="btn" type="submit">Publish</button>
        </form>
    </section>
    <section class="card">
        <h2>Published</h2>
        @forelse ($faqs as $row)
            <form method="POST" action="{{ route('support.faqs.update', $row->id) }}" class="card" style="margin-bottom:12px">
                @csrf
                @method('PATCH')
                <p><strong>{{ $row->question }}</strong> · {{ $row->audience }} · {{ $row->active ? 'active' : 'hidden' }}</p>
                <div class="grid-2">
                    <div class="field"><label>English question</label><input name="question" value="{{ $row->question }}"></div>
                    <div class="field"><label>Hindi question</label><input name="question_hi" value="{{ $row->question_hi ?? '' }}"></div>
                </div>
                <p><label>English answer</label><textarea name="answer" rows="2">{{ $row->answer }}</textarea></p>
                <p><label>Hindi answer</label><textarea name="answer_hi" rows="2">{{ $row->answer_hi ?? '' }}</textarea></p>
                <label><input type="checkbox" name="active" value="1" @checked((int) $row->active === 1)> Active</label>
                <p><button class="btn" type="submit">Save</button></p>
            </form>
        @empty
            <p class="muted">No FAQs yet. Add one above.</p>
        @endforelse
    </section>
@endsection
