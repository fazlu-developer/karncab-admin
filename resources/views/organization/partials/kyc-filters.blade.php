<form class="filters" method="GET" style="margin-bottom:14px">
    @if (!empty($heads))
        <div>
            <label>Account</label>
            <select name="user_id">
                <option value="">All</option>
                @foreach ($heads as $head)
                    <option value="{{ $head->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $head->id)>{{ $head->name }} · {{ $head->email }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, email, or place">
    <select name="doc_type">
        <option value="">All documents</option>
        @foreach (['PAN' => 'PAN', 'AADHAAR' => 'Aadhaar', 'APPOINTMENT' => 'Appointment letter', 'ADDRESS' => 'Address proof', 'GST' => 'GST', 'PHOTO' => 'Photo'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['doc_type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="status">
        <option value="">All status</option>
        @foreach (['submitted', 'verified', 'rejected'] as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <button class="btn ghost" type="submit">Search</button>
    <a class="btn ghost" href="{{ url()->current() }}">Reset</a>
</form>
