@php
    $children = collect($child['children'] ?? []);
    $padding = 1.2 + ($level * 0.9);
    $childColor = \App\Models\Catalog\Category\Category::normalizeNavigationColor($child['color'] ?? null);
@endphp

@if ($children->isNotEmpty())
    <details class="mobile-nav-details">
        <summary class="mobile-nav-row mobile-nav-row--child" style="padding-left: {{ $padding }}rem;">
            <span @if($childColor !== null) style="color: {{ $childColor }};" @endif>{{ $child['label'] ?? '' }}</span>
            <span class="menu-toggle-plus menu-toggle-sign">+</span>
            <span class="menu-toggle-minus menu-toggle-sign">-</span>
        </summary>
        <div class="mobile-nav-children">
            <a href="{{ $child['url'] ?? '#' }}" class="close-menu mobile-nav-row mobile-nav-row--view-all text-decoration-none" style="padding-left: {{ $padding + 0.9 }}rem;">
                <span @if($childColor !== null) style="color: {{ $childColor }};" @endif>Otvori {{ $child['label'] ?? '' }}</span>
            </a>
            @foreach ($children as $nestedChild)
                @include('front.mobile.partials.menu-main-child', ['child' => $nestedChild, 'level' => $level + 1])
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $child['url'] ?? '#' }}" class="close-menu mobile-nav-row mobile-nav-row--child text-decoration-none" style="padding-left: {{ $padding }}rem;">
        <span @if($childColor !== null) style="color: {{ $childColor }};" @endif>{{ $child['label'] ?? '' }}</span>
    </a>
@endif
