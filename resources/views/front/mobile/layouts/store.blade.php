<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('front.partials.seo-meta')
    @include('front.partials.schema-markup')
    @include('front.partials.analytics')
    @include('front.partials.klaviyo')

    <link rel="preload" href="{{ asset('front-theme/styles/bootstrap.css') }}?v={{ filemtime(public_path('front-theme/styles/bootstrap.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('front-theme/styles/bootstrap.css') }}?v={{ filemtime(public_path('front-theme/styles/bootstrap.css')) }}"></noscript>
    <link rel="preload" href="{{ asset('front-theme/styles/style.css') }}?v={{ filemtime(public_path('front-theme/styles/style.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('front-theme/styles/style.css') }}?v={{ filemtime(public_path('front-theme/styles/style.css')) }}"></noscript>
    <link rel="preload" href="{{ asset('front-theme/styles/rising-sun-font.css') }}?v={{ filemtime(public_path('front-theme/styles/rising-sun-font.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('front-theme/styles/rising-sun-font.css') }}?v={{ filemtime(public_path('front-theme/styles/rising-sun-font.css')) }}"></noscript>
    @if (!empty($storeSettings['branding']['favicons']['ico_url'] ?? null))
        <link rel="icon" href="{{ $storeSettings['branding']['favicons']['ico_url'] }}" sizes="any">
    @endif
    @if (!empty($storeSettings['branding']['favicons']['32_url'] ?? null))
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $storeSettings['branding']['favicons']['32_url'] }}">
    @endif
    @if (!empty($storeSettings['branding']['favicons']['16_url'] ?? null))
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $storeSettings['branding']['favicons']['16_url'] }}">
    @endif
    @if (!empty($storeSettings['branding']['favicons']['192_url'] ?? null))
        <link rel="icon" type="image/png" sizes="192x192" href="{{ $storeSettings['branding']['favicons']['192_url'] }}">
    @endif
    @if (!empty($storeSettings['branding']['favicons']['512_url'] ?? null))
        <link rel="icon" type="image/png" sizes="512x512" href="{{ $storeSettings['branding']['favicons']['512_url'] }}">
    @endif
    @if (empty($storeSettings['branding']['favicons']['ico_url'] ?? null) && !empty($storeSettings['branding']['favicon_url'] ?? null))
        <link rel="icon" href="{{ $storeSettings['branding']['favicon_url'] }}">
    @endif
    @include('front.partials.cookie-consent-head')
    <style>
        .header .store-header-logo-link {
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .header .store-header-logo {
            display: block;
            width: auto;
            height: auto;
            max-width: 150px;
            max-height: 34px;
            object-fit: contain;
        }
    </style>
</head>
<body class="theme-light font-risingsun" data-highlight="highlight-red">
@include('front.partials.accessibility')
@php
    $mobileBrandName = trim((string) (($storeSettings['branding']['store_name'] ?? null) ?: config('app.name', 'AG Shop')));
    $mobileBrandLogoUrl = trim((string) ($storeSettings['branding']['logo_url'] ?? ''));
@endphp
<div id="page">
    <div class="header header-fixed header-logo-center header-auto-show">
        <a href="{{ route('home') }}" class="header-title {{ $mobileBrandLogoUrl !== '' && ! View::hasSection('header_title') ? 'store-header-logo-link' : '' }}">
            @hasSection('header_title')
                @yield('header_title')
            @elseif ($mobileBrandLogoUrl !== '')
                <img src="{{ $mobileBrandLogoUrl }}" alt="{{ $mobileBrandName }}" class="store-header-logo" data-store-brand-logo>
            @else
                {{ $mobileBrandName }}
            @endif
        </a>
        <a href="#" data-back-button class="header-icon header-icon-1" aria-label="Back">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="#" data-menu="menu-main" class="header-icon header-icon-4" aria-label="Menu">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-dark" aria-label="Light mode">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
        </a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-light" aria-label="Dark mode">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 1 0 9.8 9.8Z"/></svg>
        </a>
    </div>

    <div id="footer-bar" class="footer-bar-6">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active-nav' : '' }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3 3 10h2v10h5v-6h4v6h5V10h2z"/></svg><span>Home</span></a>
        <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active-nav' : '' }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z"/></svg><span>Categories</span></a>
        <a href="{{ route('shop.index') }}" class="{{ request()->routeIs('shop.*') ? 'active-nav circle-nav' : 'circle-nav' }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.7 5.5L21 8.4l-4.5 4.4 1.1 6.2L12 16.1 6.4 19l1.1-6.2L3 8.4l6.3-.9z"/></svg><span>Shop</span></a>
        <a href="{{ route('cart.index') }}" class="{{ request()->routeIs('cart.*') ? 'active-nav' : '' }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 9h10l-1 10H8L7 9Z"/><path d="M9 9V7a3 3 0 0 1 6 0v2"/></svg><span>Cart</span></a>
        <a href="{{ route('wishlist.index') }}" class="{{ request()->routeIs('wishlist.*') ? 'active-nav' : '' }}">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 8.6c0 5.9-8.8 10.9-8.8 10.9S3.2 14.5 3.2 8.6a4.8 4.8 0 0 1 8.8-2.7 4.8 4.8 0 0 1 8.8 2.7Z"/></svg>
            <span>{{ __('ui.front.desktop.favorites') }}</span>
            <u data-wishlist-count>{{ (int) ($wishlistSummary['item_count'] ?? 0) }}</u>
        </a>
        <a href="#" data-menu="menu-main"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span>Menu</span></a>
    </div>

    <div class="page-title page-title-fixed">
        <h1>@yield('page_title', 'Store')</h1>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme" data-menu="menu-share" aria-label="Share"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 8a3 3 0 1 0-2.9-3.8L8.8 6.9a3 3 0 1 0 0 10.2l4.3 2.7A3 3 0 1 0 14 18a3 3 0 0 0-.1-.8l-4.3-2.7a3 3 0 0 0 0-5l4.3-2.7c.3.2.7.2 1.1.2Z"/></svg></a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-light" data-toggle-theme aria-label="Dark mode"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 1 0 9.8 9.8Z"/></svg></a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-dark" data-toggle-theme aria-label="Light mode"><svg class="h-4 w-4 text-yellow-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a1 1 0 0 1 1 1v1.1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm8-6a1 1 0 0 1 1 1 1 1 0 0 1-1 1h-1.1a1 1 0 1 1 0-2H20ZM5.1 12a1 1 0 1 1 0 2H4a1 1 0 1 1 0-2h1.1Zm11.2 5.7a1 1 0 0 1 1.4 0l.8.8a1 1 0 1 1-1.4 1.4l-.8-.8a1 1 0 0 1 0-1.4ZM6.5 6.5a1 1 0 0 1 1.4 0l.8.8A1 1 0 0 1 7.3 8.7l-.8-.8a1 1 0 0 1 0-1.4Zm11 2.2a1 1 0 0 1 0-1.4l.8-.8a1 1 0 1 1 1.4 1.4l-.8.8a1 1 0 0 1-1.4 0ZM6.5 17.5a1 1 0 0 1 1.4 0 1 1 0 0 1 0 1.4l-.8.8a1 1 0 1 1-1.4-1.4l.8-.8Z"/></svg></a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme" data-menu="menu-main" aria-label="Menu"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></a>
    </div>
    <div class="page-title-clear"></div>

    <main id="main-content" tabindex="-1" class="page-content footer-bar-clear">
        @include('front.mobile.partials.flash')
        @yield('content')
        <div class="mb-5"></div>
    </main>

    @php
        $helpColumn = collect($storeSettings['footer']['link_columns'] ?? [])->values()->get(1, []);
        $helpTitle = trim((string) ($helpColumn['title'] ?? '')) ?: __('ui.front.desktop.footer.help');
        $helpLinks = collect($helpColumn['links'] ?? [])
            ->filter(fn ($link) => is_array($link) && trim((string) ($link['label'] ?? '')) !== '' && trim((string) ($link['url'] ?? '')) !== '');
    @endphp
    <footer class="card card-style footer-bar-clear" aria-label="{{ $helpTitle }}">
        <div class="content">
            <h3>{{ $helpTitle }}</h3>
            <ul class="list-unstyled mb-0">
                @foreach ($helpLinks as $link)
                    <li><a href="{{ $link['url'] }}" class="color-theme">{{ $link['label'] }}</a></li>
                @endforeach
                @include('front.partials.withdrawal-link', ['withdrawalLinkClass' => 'color-theme'])
            </ul>
        </div>
    </footer>
    @include('front.partials.analytics-ecommerce')

    <div id="menu-main" class="menu menu-box-left rounded-0" data-menu-width="cover">
        @include('front.mobile.menu-main')
    </div>
    <div id="menu-colors" class="menu menu-box-bottom rounded-m" data-menu-load="/front-theme/menu-colors.html" data-menu-height="480"></div>
    <div id="menu-share" class="menu menu-box-bottom rounded-m" data-menu-load="/front-theme/menu-share.html" data-menu-height="370"></div>
    @stack('mobile-menus')
</div>

<script>
    (function () {
        var onLoadQueue = [
            "{{ asset('front-theme/scripts/bootstrap.min.js') }}?v={{ filemtime(public_path('front-theme/scripts/bootstrap.min.js')) }}",
            "{{ asset('front-theme/scripts/custom.js') }}?v={{ filemtime(public_path('front-theme/scripts/custom.js')) }}",
            "{{ asset('front-theme/scripts/wishlist-toggle.js') }}?v={{ filemtime(public_path('front-theme/scripts/wishlist-toggle.js')) }}"
        ];
        function loadScript(src) {
            var s = document.createElement('script');
            s.src = src;
            s.defer = true;
            document.body.appendChild(s);
        }
        window.addEventListener('load', function () {
            onLoadQueue.forEach(loadScript);
        }, { once: true });

        var loaded = false;
        function loadFontAwesome() {
            if (loaded) return;
            loaded = true;
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = "{{ asset('front-theme/fonts/css/fontawesome-all.min.css') }}?v={{ filemtime(public_path('front-theme/fonts/css/fontawesome-all.min.css')) }}";
            document.head.appendChild(link);
        }
        document.addEventListener('click', function (event) {
            if (event.target && event.target.closest('[data-menu=\"menu-main\"], [data-menu=\"menu-share\"]')) {
                loadFontAwesome();
            }
        }, { passive: true });
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(loadFontAwesome, { timeout: 2500 });
        } else {
            window.setTimeout(loadFontAwesome, 2500);
        }
    })();
</script>
@include('front.partials.scroll-to-top', ['mobileOffset' => true])
@include('front.partials.cookie-consent')
@stack('scripts')
</body>
</html>
