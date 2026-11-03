@php
    $hasNavigation = !empty($mainNavigation ?? []);
    try {
        $showBlog = app(\App\Services\Catalog\CatalogFeatureService::class)->useBlog();
    } catch (\Throwable) {
        $showBlog = (bool) config('catalog_features.flags.catalog_use_blog', true);
    }
@endphp

@if ($hasNavigation)
    <div class="desktop-mobile-menu-list overflow-y-auto px-0 text-sm uppercase tracking-[0.03em] text-slate-900">
        @foreach ($mainNavigation as $item)
            @php
                $children = collect($item['children'] ?? []);
                $hasChildren = $children->isNotEmpty();
                $target = !empty($item['open_in_new_tab']) ? '_blank' : null;
                $rel = !empty($item['open_in_new_tab']) ? 'noopener noreferrer' : null;
                $itemColor = \App\Models\Catalog\Category\Category::normalizeNavigationColor($item['color'] ?? null);
            @endphp

            @if ($hasChildren)
                <details class="group/nav desktop-mobile-menu-group" data-mobile-menu-accordion>
                    <summary class="desktop-mobile-menu-row relative flex min-h-[56px] cursor-pointer list-none items-center px-4 py-3 hover:bg-slate-50">
                        <a
                            href="{{ $item['url'] ?? '#' }}"
                            class="min-w-0 flex-1 truncate pr-12 text-[14px] font-semibold"
                            data-mobile-nav-link
                            @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif
                        >
                            <span @if($itemColor) style="color: {{ $itemColor }};" @endif>{{ $item['label'] }}</span>
                        </a>
                        <span class="absolute right-3 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center border border-slate-300 bg-white text-slate-600 group-open/nav:hidden" aria-hidden="true">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M4 10h12"></path>
                                <path d="M10 4v12"></path>
                            </svg>
                        </span>
                        <span class="absolute right-3 top-1/2 hidden h-8 w-8 -translate-y-1/2 items-center justify-center border border-slate-300 bg-white text-slate-600 group-open/nav:inline-flex" aria-hidden="true">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M4 10h12"></path>
                            </svg>
                        </span>
                    </summary>
                    <ul class="desktop-mobile-menu-children text-[13px]">
                        @foreach ($children as $child)
                            @include('front.desktop.partials.main-nav-mobile-child', ['child' => $child, 'level' => 0])
                        @endforeach
                    </ul>
                </details>
            @else
                <a href="{{ $item['url'] ?? '#' }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif><span @if($itemColor) style="color: {{ $itemColor }};" @endif>{{ $item['label'] }}</span></a>
            @endif
        @endforeach
    </div>
@else
    <nav class="desktop-mobile-menu-list overflow-y-auto px-0 text-sm uppercase tracking-[0.03em] text-slate-900">
        <a href="{{ route('shop.index') }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.new') }}</a>
        <a href="{{ route('categories.index') }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">Kategorije</a>
        @if ($showBlog)
            <a href="{{ route('blog.index') }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.blog') }}</a>
        @endif
        <a href="{{ route('faq.index') }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.faq') }}</a>
        <a href="{{ route('contact.create') }}" class="desktop-mobile-menu-row flex min-h-[56px] items-center px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.contact') }}</a>
    </nav>
@endif
