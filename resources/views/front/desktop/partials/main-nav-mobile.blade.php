@php
    $hasNavigation = !empty($mainNavigation ?? []);
@endphp

@if ($hasNavigation)
    <div class="overflow-y-auto border-t border-slate-200 px-0 text-sm uppercase tracking-[0.03em] text-slate-900">
        @foreach ($mainNavigation as $item)
            @php
                $children = collect($item['children'] ?? []);
                $hasChildren = $children->isNotEmpty();
                $target = !empty($item['open_in_new_tab']) ? '_blank' : null;
                $rel = !empty($item['open_in_new_tab']) ? 'noopener noreferrer' : null;
            @endphp

            @if ($hasChildren)
                <details class="group/nav border-b border-slate-200">
                    <summary class="flex min-h-[56px] cursor-pointer list-none items-center justify-between px-4 py-3 hover:bg-slate-50">
                        <span class="min-w-0 truncate pr-3 text-[14px] font-semibold">{{ $item['label'] }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center text-[21px] font-light leading-none text-slate-500 group-open/nav:hidden">+</span>
                        <span class="hidden h-6 w-6 items-center justify-center text-[21px] font-light leading-none text-slate-500 group-open/nav:inline-flex">−</span>
                    </summary>
                    <ul class="px-0 pb-0 text-[13px]">
                        @foreach ($children as $child)
                            @include('front.desktop.partials.main-nav-mobile-child', ['child' => $child, 'level' => 0])
                        @endforeach
                    </ul>
                </details>
            @else
                <a href="{{ $item['url'] ?? '#' }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif>{{ $item['label'] }}</a>
            @endif
        @endforeach
    </div>
@else
    <nav class="overflow-y-auto border-t border-slate-200 px-0 text-sm uppercase tracking-[0.03em] text-slate-900">
        <a href="{{ route('shop.index') }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.new') }}</a>
        <a href="{{ route('categories.index') }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">Kategorije</a>
        @if ($catalogFeatures->useBlog())
            <a href="{{ route('blog.index') }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.blog') }}</a>
        @endif
        <a href="{{ route('faq.index') }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.faq') }}</a>
        <a href="{{ route('contact.create') }}" class="flex min-h-[56px] items-center border-b border-slate-200 px-4 py-3 text-[14px] font-semibold hover:bg-slate-50">{{ __('ui.front.desktop.nav.contact') }}</a>
    </nav>
@endif
