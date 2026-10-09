{{--
    The page links under any paginated list, set as the default view in
    AppServiceProvider. Laravel's own views are built for Tailwind, which this
    project does not use.

    Previous and next carry text, not only arrows. A page that cannot be reached
    from here (the current one, or past either end) is a <span>, not a link.
--}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('Pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="pagination__link pagination__link--disabled" aria-disabled="true">{{ __('Previous') }}</span>
        @else
            <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
        @endif

        <ul class="pagination__pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pagination__gap">{{ $element }}</span></li>
                @else
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page === $paginator->currentPage())
                                <span class="pagination__link pagination__link--current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pagination__link" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
        @else
            <span class="pagination__link pagination__link--disabled" aria-disabled="true">{{ __('Next') }}</span>
        @endif
    </nav>
@endif
