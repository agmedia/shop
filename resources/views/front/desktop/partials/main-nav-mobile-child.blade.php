@php
    $children = collect($child['children'] ?? []);
    $padding = 1.25 + ($level * 0.75);
    $labelWeightClass = $level === 0 ? 'font-semibold' : 'font-medium';
    $leafWeightClass = $level === 0 ? 'font-medium' : 'font-light';
    $target = !empty($child['open_in_new_tab']) ? '_blank' : null;
    $rel = !empty($child['open_in_new_tab']) ? 'noopener noreferrer' : null;
    $childColor = \App\Models\Catalog\Category\Category::normalizeNavigationColor($child['color'] ?? null);
@endphp

<li>
@if ($children->isNotEmpty())
        <details class="group/subnav desktop-mobile-menu-group" data-mobile-menu-accordion>
            <summary class="desktop-mobile-menu-row relative flex min-h-[52px] cursor-pointer list-none items-center py-3 pr-3 text-[13px] text-slate-700 hover:bg-slate-50 hover:text-slate-900" style="padding-left: {{ $padding }}rem;">
                <a
                    href="{{ $child['url'] ?? '#' }}"
                    class="min-w-0 flex-1 truncate pr-11 {{ $labelWeightClass }}"
                    data-mobile-nav-link
                    @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif
                >
                    <span @if($childColor) style="color: {{ $childColor }};" @endif>{{ $child['label'] ?? '' }}</span>
                </a>
                <span class="absolute right-3 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center border border-slate-300 bg-white text-slate-500 group-open/subnav:hidden" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 10h12"></path>
                        <path d="M10 4v12"></path>
                    </svg>
                </span>
                <span class="absolute right-3 top-1/2 hidden h-7 w-7 -translate-y-1/2 items-center justify-center border border-slate-300 bg-white text-slate-500 group-open/subnav:inline-flex" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 10h12"></path>
                    </svg>
                </span>
            </summary>
            <ul class="desktop-mobile-menu-children">
                @foreach ($children as $nestedChild)
                    @include('front.desktop.partials.main-nav-mobile-child', ['child' => $nestedChild, 'level' => $level + 1])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $child['url'] ?? '#' }}" class="desktop-mobile-menu-row flex min-h-[52px] items-center py-3 text-[13px] {{ $leafWeightClass }} text-slate-700 hover:bg-slate-100 hover:text-slate-900" style="padding-left: {{ $padding }}rem;">
            <span @if($childColor) style="color: {{ $childColor }};" @endif>{{ $child['label'] ?? '' }}</span>
        </a>
    @endif
</li>
