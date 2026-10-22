<div class="kyc-list">
    @forelse ($docs as $row)
        @php
            $fileUrl = route($fileRoute, $row->id);
            $ext = strtolower(pathinfo((string) ($row->path ?? $row->original_name ?? ''), PATHINFO_EXTENSION));
            $image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            $pdf = $ext === 'pdf';
            $place = $row->{$placeKey} ?? null;
        @endphp
        <article class="kyc-row">
            <a class="kyc-preview" href="{{ $fileUrl }}" target="_blank" rel="noopener">
                @if ($image)
                    <img src="{{ $fileUrl }}" alt="{{ $row->doc_type }}" width="112" height="80" loading="lazy">
                @elseif ($pdf)
                    <iframe src="{{ $fileUrl }}" title="{{ $row->doc_type }}"></iframe>
                @else
                    <span>File</span>
                @endif
            </a>
            <div class="kyc-meta">
                <strong>{{ $row->user_name ?: 'User #'.$row->user_id }}</strong>
                <div class="muted">{{ $row->user_email }}</div>
                <div>{{ $place ?: '—' }} · {{ $row->doc_type }}</div>
                @if (! empty($row->original_name))
                    <div class="muted">{{ $row->original_name }}</div>
                @endif
                <div class="kyc-meta-line">
                    <span class="pill muted">{{ $row->status }}</span>
                    <span class="muted">{{ $row->created_at }}</span>
                </div>
            </div>
            <div class="kyc-actions">
                <a class="btn ghost" href="{{ $fileUrl }}" target="_blank" rel="noopener">Open</a>
                <form method="POST" action="{{ route($replaceRoute, $row->id) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                    <button class="btn ghost" type="submit">Replace</button>
                </form>
                <form method="POST" action="{{ route($destroyRoute, $row->id) }}" onsubmit="return confirm('Delete this KYC file?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn danger" type="submit">Delete</button>
                </form>
                @can('kyc.approve')
                    <form method="POST" action="{{ route($reviewRoute, $row->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="verified">
                        <button class="btn" type="submit">Approve</button>
                    </form>
                    <form method="POST" action="{{ route($reviewRoute, $row->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="rejected">
                        <button class="btn ghost" type="submit">Reject</button>
                    </form>
                @endcan
            </div>
        </article>
    @empty
        <p class="muted">{{ $empty }}</p>
    @endforelse
</div>
