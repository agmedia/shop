@if ($paginator->hasPages())
    <div class="w-full">
        <p class="mb-4 text-center text-sm text-slate-500">
            {{ __('pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-center">
            <div class="inline-flex items-center">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-slate-100 px-3 text-sm text-slate-400">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:z-10">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-white px-3 text-sm text-slate-500">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-900 bg-slate-900 px-3 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:z-10" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:z-10">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex h-10 min-w-10 items-center justify-center border border-slate-300 bg-slate-100 px-3 text-sm text-slate-400">
                    {!! __('pagination.next') !!}
                </span>
            @endif
            </div>
        </nav>
    </div>
@endif
