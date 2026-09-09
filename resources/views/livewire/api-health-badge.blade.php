<div class="card health">
    <strong><i data-lucide="activity"></i> Database</strong>
    <span class="pill {{ $status === 'ok' ? 'ok' : 'warn' }}">{{ $status }}</span>
    <p class="muted">{{ $message }}</p>
</div>
