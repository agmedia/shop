@php
    $children = collect($child['children'] ?? []);
    $padding = 1.2 + ($level * 0.9);
@endphp

@if ($children->isNotEmpty())
    <details>
        <summary class="mobile-nav-row" style="padding-left: {{ $padding }}rem;">
            <span class="font-500">{{ $child['label'] ?? '' }}</span>
            <span class="opacity-60 menu-toggle-plus menu-toggle-sign">+</span>
            <span class="opacity-60 menu-toggle-minus menu-toggle-sign">-</span>
        </summary>
        <div>
            <a href="{{ $child['url'] ?? '#' }}" class="close-menu mobile-nav-row text-decoration-none" style="padding-left: {{ $padding + 0.9 }}rem;">
                <span class="font-500">Otvori {{ $child['label'] ?? '' }}</span>
            </a>
            @foreach ($children as $nestedChild)
                @include('front.mobile.partials.menu-main-child', ['child' => $nestedChild, 'level' => $level + 1])
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $child['url'] ?? '#' }}" class="close-menu mobile-nav-row text-decoration-none" style="padding-left: {{ $padding }}rem;">
        <span class="font-500">{{ $child['label'] ?? '' }}</span>
    </a>
@endif
