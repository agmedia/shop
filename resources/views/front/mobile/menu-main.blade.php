@php
    try {
        $catalogFeatures = app(\App\Services\Catalog\CatalogFeatureService::class);
        $mainNavigation = app(\App\Services\Front\NavigationMenuService::class)->forLocale((string) app()->getLocale());
        $showManufacturers = $catalogFeatures->useManufacturers();
        $showBlog = $catalogFeatures->useBlog();
        $loyaltyEnabled = (bool) app(\App\Services\Settings\SystemSettingsService::class)->get(
            'user_loyalty_enabled',
            (bool) config('user_features.flags.user_loyalty_enabled', true)
        );
    } catch (\Throwable $e) {
        $mainNavigation = [];
        $showManufacturers = (bool) config('catalog_features.flags.catalog_use_manufacturers', true);
        $showBlog = (bool) config('catalog_features.flags.catalog_use_blog', true);
        $loyaltyEnabled = (bool) config('user_features.flags.user_loyalty_enabled', true);
    }

    $storeName = trim((string) (($storeSettings['branding']['store_name'] ?? null) ?: config('app.name', 'AG Shop')));
    $storeLogoUrl = trim((string) ($storeSettings['branding']['logo_url'] ?? ''));

    $primaryUrls = collect($mainNavigation ?? [])
        ->map(fn ($item) => trim((string) ($item['url'] ?? '')))
        ->filter()
        ->values();

    $secondaryLinks = [
        ['label' => __('ui.mobile.menu.home'), 'url' => route('home')],
        ['label' => __('ui.mobile.menu.desktop_storefront'), 'url' => route('home', ['frontend_variant' => 'desktop'])],
    ];

    if ($showManufacturers) {
        $secondaryLinks[] = ['label' => __('ui.mobile.menu.manufacturers'), 'url' => route('manufacturers.index')];
    }

    if ($showBlog) {
        $secondaryLinks[] = ['label' => __('ui.mobile.menu.blog'), 'url' => route('blog.index')];
    }

    if (\Illuminate\Support\Facades\Route::has('faq.index')) {
        $secondaryLinks[] = ['label' => __('ui.front.desktop.nav.faq'), 'url' => route('faq.index')];
    }

    $secondaryLinks[] = ['label' => __('ui.mobile.menu.cart'), 'url' => route('cart.index')];
    $secondaryLinks[] = ['label' => __('ui.front.desktop.favorites'), 'url' => route('wishlist.index')];
    $secondaryLinks[] = ['label' => __('ui.mobile.menu.contact'), 'url' => route('contact.create')];
    $secondaryLinks = collect($secondaryLinks)
        ->reject(fn ($link) => $primaryUrls->contains(trim((string) ($link['url'] ?? ''))))
        ->unique('url')
        ->values()
        ->all();
@endphp

<style>
    .menu-toggle-minus { display: none; }
    details[open] > summary .menu-toggle-plus { display: none; }
    details[open] > summary .menu-toggle-minus { display: inline; }
    details > summary { list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    #menu-main {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        width: 100dvw !important;
        max-width: 100vw !important;
        max-width: 100dvw !important;
        right: 0 !important;
        left: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        background: #ffffff !important;
        z-index: 9700 !important;
        overflow: visible !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }
    .menu-hider {
        z-index: 9690 !important;
    }
    body.menu-main-open #cookie-consent-floating-button,
    body.menu-main-open .header,
    body.menu-main-open #footer-bar,
    body.menu-main-open .page-title {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
    #menu-main .mobile-menu-shell {
        position: fixed;
        inset: 0;
        width: 100vw;
        width: 100dvw;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        overflow-x: hidden;
        overflow-y: auto;
    }
    #menu-main .mobile-menu-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: calc(env(safe-area-inset-top, 0px) + 1rem) 1rem 1rem;
        border-bottom: 1px solid #d5dde7;
        background: #ffffff;
    }
    #menu-main .mobile-menu-brand {
        min-width: 0;
        color: #0f172a;
        text-decoration: none;
        font-size: clamp(1.55rem, 4vw, 2rem);
        font-weight: 800;
        line-height: 1.05;
        text-transform: uppercase;
    }
    #menu-main .mobile-menu-brand.has-logo {
        display: inline-flex;
        align-items: center;
        text-transform: none;
    }
    #menu-main .mobile-menu-brand img {
        display: block;
        width: auto;
        height: auto;
        max-width: min(58vw, 13rem);
        max-height: 3rem;
        object-fit: contain;
    }
    #menu-main .mobile-menu-close {
        flex: 0 0 4.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4.25rem;
        height: 4.25rem;
        border: 1px solid #d5dde7;
        background: #ffffff;
        color: #334155;
    }
    #menu-main .mobile-menu-close svg {
        width: 1.8rem;
        height: 1.8rem;
    }
    #menu-main .mobile-menu-body {
        flex: 1 1 auto;
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem);
    }
    #menu-main .mobile-menu-section,
    #menu-main .mobile-nav-children {
        margin: 0;
        padding: 0;
    }
    #menu-main .mobile-nav-details {
        margin: 0;
    }
    .mobile-nav-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        min-height: 4.45rem;
        padding: 1rem 1.35rem;
        margin: 0;
        background: #ffffff;
        color: #334155;
        border: 0;
        box-shadow: inset 0 -1px 0 #d5dde7;
        text-align: left;
        text-decoration: none;
    }
    .mobile-nav-row:visited {
        color: #334155;
    }
    .mobile-nav-row > span:first-child {
        min-width: 0;
        flex: 1 1 auto;
    }
    .mobile-nav-row--primary {
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }
    .mobile-nav-row--child {
        font-size: 0.98rem;
        font-weight: 500;
        text-transform: none;
    }
    .mobile-nav-row--utility {
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }
    .mobile-nav-row--view-all {
        font-size: 0.92rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
    }
    .menu-toggle-sign {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 3.1rem;
        width: 3.1rem;
        height: 3.1rem;
        border: 1px solid #d5dde7;
        font-size: 1.8rem;
        font-weight: 300;
        line-height: 1;
        color: #475569;
        background: #ffffff;
    }
    #menu-main .mobile-nav-details > summary {
        cursor: pointer;
    }
    #menu-main .mobile-menu-footer {
        padding: 1rem 1.35rem calc(env(safe-area-inset-bottom, 0px) + 1rem);
        box-shadow: inset 0 1px 0 #d5dde7;
        background: #ffffff;
    }
    #menu-main .mobile-menu-languages {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    #menu-main .mobile-menu-language {
        color: #475569;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    #menu-main .mobile-menu-language.is-active {
        color: #0f172a;
        text-decoration: underline;
        text-underline-offset: 0.25rem;
    }
    @media (max-width: 768px) {
        .mobile-nav-row {
            padding: 0.95rem 1.2rem;
        }
        .menu-toggle-sign {
            flex-basis: 2.8rem;
            width: 2.8rem;
            height: 2.8rem;
            font-size: 1.65rem;
        }
    }
</style>

<div class="mobile-menu-shell">
    <div class="mobile-menu-header">
        <a href="{{ route('home') }}" class="mobile-menu-brand {{ $storeLogoUrl !== '' ? 'has-logo' : '' }}">
            @if ($storeLogoUrl !== '')
                <img src="{{ $storeLogoUrl }}" alt="{{ $storeName }}" data-store-brand-logo>
            @else
                {{ $storeName }}
            @endif
        </a>
        <button type="button" class="mobile-menu-close close-menu" aria-label="Zatvori izbornik">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12"></path>
                <path d="M18 6 6 18"></path>
            </svg>
        </button>
    </div>

    <div class="mobile-menu-body">
        <div class="mobile-menu-section">
            @if (!empty($mainNavigation))
                @foreach ($mainNavigation as $navItem)
                    @php
                        $children = collect($navItem['children'] ?? []);
                        $target = !empty($navItem['open_in_new_tab']) ? '_blank' : null;
                        $rel = !empty($navItem['open_in_new_tab']) ? 'noopener noreferrer' : null;
                    @endphp

                    @if ($children->isNotEmpty())
                        <details class="mobile-nav-details">
                            <summary class="mobile-nav-row mobile-nav-row--primary">
                                <span>{{ $navItem['label'] ?? 'Menu' }}</span>
                                <span class="menu-toggle-plus menu-toggle-sign">+</span>
                                <span class="menu-toggle-minus menu-toggle-sign">-</span>
                            </summary>
                            <div class="mobile-nav-children">
                                <a href="{{ $navItem['url'] ?? '#' }}" class="close-menu mobile-nav-row mobile-nav-row--view-all" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif>
                                    <span>{{ __('ui.mobile.menu.shop') }}: {{ $navItem['label'] ?? 'Menu' }}</span>
                                </a>
                                @foreach ($children as $child)
                                    @include('front.mobile.partials.menu-main-child', ['child' => $child, 'level' => 0])
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a href="{{ $navItem['url'] ?? '#' }}" class="close-menu mobile-nav-row mobile-nav-row--primary" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif>
                            <span>{{ $navItem['label'] ?? 'Menu' }}</span>
                        </a>
                    @endif
                @endforeach
            @else
                <a href="{{ route('shop.index') }}" class="close-menu mobile-nav-row mobile-nav-row--primary">
                    <span>{{ __('ui.mobile.menu.shop') }}</span>
                </a>
                <a href="{{ route('categories.index') }}" class="close-menu mobile-nav-row mobile-nav-row--primary">
                    <span>{{ __('ui.mobile.menu.categories') }}</span>
                </a>
            @endif

            @foreach ($secondaryLinks as $link)
                <a href="{{ $link['url'] }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach

            @auth
                <a href="{{ route('account.dashboard') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ __('ui.mobile.menu.my_account') }}</span>
                </a>
                <a href="{{ route('account.orders') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ __('ui.mobile.menu.my_orders') }}</span>
                </a>
                <a href="{{ route('account.profile') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ __('ui.mobile.menu.profile_settings') }}</span>
                </a>

                @if ($loyaltyEnabled)
                    <a href="{{ route('account.loyalty') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                        <span>{{ __('ui.account.nav.loyalty') }}</span>
                    </a>
                @endif

                @if (auth()->user()->isA('superadmin') || auth()->user()->can('admin.access'))
                    <a href="{{ route('admin.dashboard') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                        <span>{{ __('ui.mobile.menu.admin_dashboard') }}</span>
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="close-menu mobile-nav-row mobile-nav-row--utility">
                        <span>{{ __('ui.account.nav.logout') }}</span>
                    </button>
                </form>
            @else
                <a href="{{ route('front.auth.login') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ __('ui.mobile.menu.login') }}</span>
                </a>
                <a href="{{ route('front.auth.register') }}" class="close-menu mobile-nav-row mobile-nav-row--utility">
                    <span>{{ __('ui.mobile.menu.register') }}</span>
                </a>
            @endauth
        </div>
    </div>

    <div class="mobile-menu-footer">
        @if (count($frontLanguages ?? []) > 1)
            <div class="mobile-menu-languages" aria-label="Languages">
                @foreach ($frontLanguages as $language)
                    @php($languageCode = (string) ($language['code'] ?? ''))
                    @if ($languageCode === (string) ($frontLocale ?? app()->getLocale()))
                        <span class="mobile-menu-language is-active">{{ strtoupper($languageCode) }}</span>
                    @else
                        <a href="{{ route('front.locale.switch', ['code' => $languageCode]) }}" class="mobile-menu-language" hreflang="{{ $languageCode }}">
                            {{ strtoupper($languageCode) }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
        <a href="#" data-menu="menu-colors" class="btn btn-full rounded-0 font-13 font-700 bg-highlight">
            {{ __('ui.mobile.menu.theme_colors') }}
        </a>
    </div>
</div>

<script>
    (function () {
        const syncMobileMainMenuLayout = function () {
            const menu = document.getElementById('menu-main');
            const shell = menu ? menu.querySelector('.mobile-menu-shell') : null;

            if (!menu || !shell) {
                return;
            }

            const viewportWidth = Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0);
            const viewportHeight = Math.max(window.innerHeight || 0, document.documentElement.clientHeight || 0);

            if (viewportWidth > 0) {
                menu.style.width = viewportWidth + 'px';
                menu.style.maxWidth = viewportWidth + 'px';
                shell.style.width = viewportWidth + 'px';
                shell.style.maxWidth = viewportWidth + 'px';
            }

            if (viewportHeight > 0) {
                menu.style.height = viewportHeight + 'px';
                shell.style.minHeight = viewportHeight + 'px';
            }

            menu.style.left = '0px';
            menu.style.right = '0px';
            menu.style.top = '0px';
            menu.style.bottom = '0px';
        };

        const syncMobileMainMenuState = function () {
            const menu = document.getElementById('menu-main');
            const isOpen = !!(menu && menu.classList.contains('menu-active'));

            document.body.classList.toggle('menu-main-open', isOpen);
        };

        const initMobileMainMenuLayout = function () {
            const menu = document.getElementById('menu-main');

            syncMobileMainMenuLayout();
            syncMobileMainMenuState();
            window.setTimeout(syncMobileMainMenuLayout, 120);
            window.setTimeout(syncMobileMainMenuState, 120);

            window.addEventListener('resize', syncMobileMainMenuLayout, { passive: true });

            document.querySelectorAll('[data-menu="menu-main"]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    window.requestAnimationFrame(syncMobileMainMenuLayout);
                    window.requestAnimationFrame(syncMobileMainMenuState);
                    window.setTimeout(syncMobileMainMenuLayout, 80);
                    window.setTimeout(syncMobileMainMenuState, 80);
                });
            });

            document.querySelectorAll('#menu-main .close-menu, .menu-hider').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    window.setTimeout(syncMobileMainMenuState, 40);
                });
            });

            if (menu && !menu.__mobileMenuObserverAttached) {
                const observer = new MutationObserver(function () {
                    syncMobileMainMenuLayout();
                    syncMobileMainMenuState();
                });

                observer.observe(menu, {
                    attributes: true,
                    attributeFilter: ['class', 'style'],
                });

                menu.__mobileMenuObserverAttached = true;
            }
        };

        if (document.readyState === 'complete') {
            initMobileMainMenuLayout();
        } else {
            window.addEventListener('load', initMobileMainMenuLayout, { once: true });
        }
    })();
</script>
