@if ($paginator->hasPages())
    <nav>
        @if ($paginator->onFirstPage())
            <span>Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">Prev</a>
        @endif
        @php
            $start = max(1, $paginator->currentPage() - 2);
            $end = min($paginator->lastPage(), $paginator->currentPage() + 2);
        @endphp
        @for ($page = $start; $page <= $end; $page++)
            @if ($page == $paginator->currentPage())
                <span class="active"><span>{{ $page }}</span></span>
            @else
                <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
            @endif
        @endfor
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span>Next</span>
        @endif
    </nav>
@endif
