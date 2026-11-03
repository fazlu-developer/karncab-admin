@if ($path)
    @if (str_ends_with(strtolower($path), '.pdf'))
        <div style="margin:0 0 8px"><a href="{{ route('joining.letterhead.file', $kind) }}">View {{ $label }}</a></div>
    @else
        <img src="{{ route('joining.letterhead.file', $kind) }}" alt="{{ $label }}" style="display:block;max-width:180px;max-height:72px;margin:0 0 8px;background:#fff;border:1px solid #e5e7eb;padding:6px">
    @endif
@endif
