@php
    $children = collect($child['children'] ?? []);
    $padding = 1.25 + ($level * 0.75);
    $labelWeightClass = $level === 0 ? 'font-semibold' : 'font-medium';
    $leafWeightClass = $level === 0 ? 'font-medium' : 'font-light';
@endphp

<li class="border-b border-slate-200">
@if ($children->isNotEmpty())
        <details class="group/subnav">
            <summary class="flex min-h-[52px] cursor-pointer list-none items-center justify-between py-3 pr-3 text-[13px] text-slate-700 hover:bg-slate-50 hover:text-slate-900" style="padding-left: {{ $padding }}rem;">
                <span class="truncate pr-2 {{ $labelWeightClass }}">{{ $child['label'] ?? '' }}</span>
                <span class="inline-flex h-6 w-6 items-center justify-center text-[20px] font-light leading-none text-slate-400 group-open/subnav:hidden">+</span>
                <span class="hidden h-6 w-6 items-center justify-center text-[20px] font-light leading-none text-slate-400 group-open/subnav:inline-flex">−</span>
            </summary>
            <ul>
                @foreach ($children as $nestedChild)
                    @include('front.desktop.partials.main-nav-mobile-child', ['child' => $nestedChild, 'level' => $level + 1])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $child['url'] ?? '#' }}" class="flex min-h-[52px] items-center py-3 text-[13px] {{ $leafWeightClass }} text-slate-700 hover:bg-slate-100 hover:text-slate-900" style="padding-left: {{ $padding }}rem;">
            {{ $child['label'] ?? '' }}
        </a>
    @endif
</li>
