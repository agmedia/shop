<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('front.partials.seo-meta')
    @include('front.partials.schema-markup')
    @include('front.partials.analytics')
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link rel="stylesheet" href="{{ asset('front-theme/styles/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('front-theme/styles/style.css') }}">
    <link rel="stylesheet" href="{{ asset('front-theme/fonts/css/fontawesome-all.min.css') }}">
    <link rel="manifest" href="{{ route('front.manifest') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('front-theme/app/icons/icon-192x192.png') }}">
</head>
<body class="theme-light" data-highlight="highlight-red">
@php
    $mobileHeroBlocks = app(\App\Services\Content\ContentBlockResolver::class)->forPlacement(
        'home.hero',
        app()->getLocale(),
        null,
        null,
        'mobile',
        true
    );

    $isFrontPreview = false;
    $frontPreviewBlock = null;
    $frontPreviewPlacement = null;

    $viewer = auth()->user();
    $canPreviewBlock = $viewer && ($viewer->isA('superadmin') || $viewer->can('content.blocks'));
    $previewBlockId = $canPreviewBlock ? (int) request()->query('preview_block', 0) : 0;
    $requestedPreviewPlacement = $canPreviewBlock ? (string) request()->query('preview_placement', '') : '';

    if ($previewBlockId > 0) {
        $frontPreviewBlock = \App\Models\Content\ContentBlock::query()
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [app()->getLocale(), config('app.locale')]),
                'slots' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])
            ->find($previewBlockId);

        if ($frontPreviewBlock) {
            $frontPreviewPlacement = $requestedPreviewPlacement !== ''
                ? $requestedPreviewPlacement
                : (string) ($frontPreviewBlock->slots->first()?->placement ?? 'home.hero');

            $frontPreviewTranslation = $frontPreviewBlock->translations->firstWhere('locale', app()->getLocale())
                ?? $frontPreviewBlock->translations->firstWhere('locale', config('app.locale'));
            $frontPreviewSlot = $frontPreviewBlock->slots->firstWhere('placement', $frontPreviewPlacement)
                ?? new \App\Models\Content\ContentBlockSlot(['placement' => $frontPreviewPlacement]);
            $frontPreviewItem = collect([[
                'slot' => $frontPreviewSlot,
                'block' => $frontPreviewBlock,
                'translation' => $frontPreviewTranslation,
            ]]);

            if ($frontPreviewPlacement === 'home.hero') {
                $mobileHeroBlocks = $frontPreviewItem;
                $isFrontPreview = true;
            }
        }
    }
@endphp
<div id="preloader">
    <div class="spinner-border color-highlight" role="status"></div>
</div>

<div id="page">
    <div class="header header-fixed header-logo-center header-auto-show">
        <a href="/" class="header-title">Store</a>
        <a href="/" class="header-icon header-icon-1" aria-label="Home">
            <i class="fas fa-chevron-left"></i>
        </a>
        <a href="#" data-menu="menu-main" class="header-icon header-icon-4" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-dark" aria-label="Light mode">
            <i class="fas fa-sun"></i>
        </a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-light" aria-label="Dark mode">
            <i class="fas fa-moon"></i>
        </a>
    </div>

    <div id="footer-bar" class="footer-bar-5">
        <a href="{{ route('home') }}" class="active-nav"><i class="fa fa-home"></i><span>Home</span></a>
        <a href="{{ route('categories.index') }}"><i class="fa fa-th-large"></i><span>Categories</span></a>
        <a href="{{ route('shop.index') }}" class="circle-nav"><i class="fa fa-star"></i><span>Featured</span></a>
        <a href="{{ route('cart.index') }}"><i class="fa fa-shopping-bag"></i><span>Cart</span></a>
        <a href="#" data-menu="menu-main"><i class="fa fa-bars"></i><span>Menu</span></a>
    </div>

    <div class="page-title page-title-fixed">
        <h1>{{ config('app.name', 'AG Shop') }} Store</h1>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme" data-menu="menu-share" aria-label="Share">
            <i class="fa fa-share-alt"></i>
        </a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-light" data-toggle-theme aria-label="Dark mode">
            <i class="fa fa-moon"></i>
        </a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-dark" data-toggle-theme aria-label="Light mode">
            <i class="fa fa-lightbulb color-yellow-dark"></i>
        </a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme" data-menu="menu-main" aria-label="Menu">
            <i class="fa fa-bars"></i>
        </a>
    </div>
    <div class="page-title-clear"></div>

    <div class="page-content footer-bar-clear">
        @if ($isFrontPreview)
            <div class="card card-style bg-yellow-light mb-3">
                <div class="content py-2">
                    <p class="mb-0 font-600 color-black">
                        Front preview:
                        <span class="font-700">{{ $frontPreviewBlock?->name }}</span>
                        <span class="opacity-60">({{ $frontPreviewPlacement }})</span>
                    </p>
                </div>
            </div>
        @endif

        @if ($mobileHeroBlocks->isNotEmpty())
            @include('components.content-placement', ['items' => $mobileHeroBlocks])
        @else
            <div class="splide single-slider slider-no-arrows slider-no-dots" id="single-slider-1">
                <div class="splide__track">
                    <div class="splide__list">
                        <div class="splide__slide">
                            <div class="card card-style mb-3 bg-19" data-card-height="300">
                                <div class="card-top">
                                    <a href="#" data-menu="menu-heart" class="icon icon-s bg-white color-red-dark rounded-xl mt-3 me-3 float-end"><i class="fa fa-heart"></i></a>
                                    <a href="#" data-menu="menu-cart" class="icon icon-s bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                                </div>
                                <div class="card-bottom mb-3 ms-3 me-3">
                                    <h1 class="color-white font-800 mb-n2">Transit Pro Pack</h1>
                                    <p class="color-white font-14 mb-2 opacity-60">Lightweight daypack with modular straps and weather shield.</p>
                                </div>
                                <div class="card-overlay bg-black opacity-60"></div>
                            </div>
                        </div>
                        <div class="splide__slide">
                            <div class="card card-style mb-3 bg-18" data-card-height="300">
                                <div class="card-top">
                                    <a href="#" data-menu="menu-heart" class="icon icon-s bg-white color-red-dark rounded-xl mt-3 me-3 float-end"><i class="fa fa-heart"></i></a>
                                    <a href="#" data-menu="menu-cart" class="icon icon-s bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                                </div>
                                <div class="card-bottom mb-3 ms-3 me-3">
                                    <h1 class="color-white font-800 mb-n2">Wireless Audio Kit</h1>
                                    <p class="color-white font-14 mb-2 opacity-60">Noise isolation with USB-C fast charging and compact case.</p>
                                </div>
                                <div class="card-overlay bg-black opacity-60"></div>
                            </div>
                        </div>
                        <div class="splide__slide">
                            <div class="card card-style mb-3 bg-17" data-card-height="300">
                                <div class="card-top">
                                    <a href="#" data-menu="menu-heart" class="icon icon-s bg-white color-red-dark rounded-xl mt-3 me-3 float-end"><i class="fa fa-heart"></i></a>
                                    <a href="#" data-menu="menu-cart" class="icon icon-s bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                                </div>
                                <div class="card-bottom mb-3 ms-3 me-3">
                                    <h1 class="color-white font-800 mb-n2">Weekend Duffel 32L</h1>
                                    <p class="color-white font-14 mb-2 opacity-60">Durable nylon shell, reinforced base and clean carry profile.</p>
                                </div>
                                <div class="card-overlay bg-black opacity-60"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="splide topic-slider slider-no-arrows slider-no-dots pb-2" id="topic-slider-1">
            <div class="splide__track">
                <div class="splide__list">
                    <div class="splide__slide"><h1 class="font-16 d-block"><a href="#" class="color-theme">Bags</a></h1></div>
                    <div class="splide__slide"><h1 class="font-16 d-block"><a href="#" class="color-theme opacity-50">Jackets</a></h1></div>
                    <div class="splide__slide"><h1 class="font-16 d-block"><a href="#" class="color-theme opacity-50">Shoes</a></h1></div>
                    <div class="splide__slide"><h1 class="font-16 d-block"><a href="#" class="color-theme opacity-50">Accessories</a></h1></div>
                    <div class="splide__slide"><h1 class="font-16 d-block"><a href="#" class="color-theme opacity-50">Travel</a></h1></div>
                </div>
            </div>
        </div>

        <div class="splide double-slider slider-no-dots" id="double-slider-1">
            <div class="splide__track">
                <div class="splide__list">
                    <div class="splide__slide">
                        <div class="card mx-3 mb-0 card-style bg-20" data-card-height="230">
                            <div class="card-top">
                                <a href="#" data-menu="menu-cart" class="icon icon-xxs bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                            </div>
                            <div class="card-bottom"><h3 class="color-white font-800 mb-3 pb-1 ps-3">EUR 89</h3></div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                        <p class="mx-3 mb-0 mt-2 color-highlight font-600">In stock</p>
                        <h4 class="mx-3 mb-4">Transit Pro <br> Backpack</h4>
                    </div>
                    <div class="splide__slide">
                        <div class="card mx-3 mb-0 card-style bg-21" data-card-height="230">
                            <div class="card-top">
                                <a href="#" data-menu="menu-cart" class="icon icon-xxs bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                            </div>
                            <div class="card-bottom">
                                <p class="color-white mb-n1 pb-1 ps-3 font-12 opacity-40 font-400"><del>EUR 149</del></p>
                                <h3 class="color-white font-800 mb-3 pb-1 ps-3">EUR 109</h3>
                            </div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                        <p class="mx-3 mb-0 mt-2 color-highlight font-600">Limited drop</p>
                        <h4 class="mx-3 mb-4">Weekend <br> Duffel</h4>
                    </div>
                    <div class="splide__slide">
                        <div class="card mx-3 mb-0 card-style bg-29" data-card-height="230">
                            <div class="card-top">
                                <a href="#" data-menu="menu-cart" class="icon icon-xxs bg-white color-black rounded-xl mt-3 me-2 float-end"><i class="fa fa-shopping-bag"></i></a>
                            </div>
                            <div class="card-bottom"><h3 class="color-white font-800 mb-3 pb-1 ps-3">EUR 59</h3></div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                        <p class="mx-3 mb-0 mt-2 color-highlight font-600">Top rated</p>
                        <h4 class="mx-3 mb-4">Modular <br> Sling</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider divider-margins"></div>

        <div class="card card-style" id="featured">
            <div class="content mb-0">
                <p class="mb-n1 font-600 color-highlight">Handpicked by AGShop</p>
                <h2 class="mb-4">Featured Products</h2>

                <div class="row mb-0">
                    <div class="col-6 mb-4 pe-0">
                        <a href="#"><img src="{{ asset('front-theme/images/pictures/17s.jpg') }}" class="rounded-sm shadow-xl img-fluid" alt="Product"></a>
                    </div>
                    <div class="col-6">
                        <a href="#" class="d-block">
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i><br>
                        </a>
                        <a href="#">
                            <h5 class="mb-0">Travel Shell Backpack</h5>
                            <span class="color-green-dark font-10">In Stock</span>
                        </a>
                        <h1 class="mt-1 mb-n2 font-800">EUR 89</h1>
                        <span class="opacity-50 font-11"><del>EUR 119</del> (- 25%)</span>
                    </div>

                    <div class="w-100 divider divider-margins"></div>

                    <div class="col-6 mb-4 pe-0">
                        <a href="#"><img src="{{ asset('front-theme/images/pictures/18s.jpg') }}" class="rounded-sm shadow-xl img-fluid" alt="Product"></a>
                    </div>
                    <div class="col-6">
                        <a href="#" class="d-block">
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i><br>
                        </a>
                        <a href="#">
                            <h5 class="mb-0">Weatherproof Utility Jacket</h5>
                            <span class="color-green-dark font-10">In Stock</span>
                        </a>
                        <h1 class="mt-1 mb-n2 font-800">EUR 129</h1>
                        <span class="opacity-50 font-11"><del>EUR 169</del> (- 24%)</span>
                    </div>
                </div>

                <a href="#" class="btn btn-full btn-sm rounded-s mb-3 font-600 gradient-highlight">View More Featured Products</a>
            </div>
        </div>

        <div class="content mb-3" id="categories">
            <h3>Popular Categories</h3>
        </div>
        <div class="splide double-slider slider-no-dots" id="double-slider-3">
            <div class="splide__track">
                <div class="splide__list">
                    <div class="splide__slide">
                        <div class="card mx-3 card-style bg-20" data-card-height="120">
                            <div class="card-center"><h3 class="color-white font-800 pb-0 mb-0 ps-3">Backpacks</h3></div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                    </div>
                    <div class="splide__slide">
                        <div class="card mx-3 card-style bg-21" data-card-height="120">
                            <div class="card-center"><h3 class="color-white font-800 pb-0 mb-0 ps-3">Outerwear</h3></div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                    </div>
                    <div class="splide__slide">
                        <div class="card mx-3 card-style bg-29" data-card-height="120">
                            <div class="card-center"><h3 class="color-white font-800 pb-0 mb-0 ps-3">Accessories</h3></div>
                            <div class="card-overlay bg-gradient"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-style">
            <div class="content mb-0">
                <p class="mb-n1 font-600 color-highlight">Fresh arrivals</p>
                <h2 class="mb-4">New in Store</h2>

                <div class="row text-center mb-0">
                    <div class="col-6 mb-4">
                        <a href="#"><img src="{{ asset('front-theme/images/pictures/17s.jpg') }}" class="rounded-sm shadow-xl img-fluid" alt="New arrival"></a>
                        <a href="#" class="d-block mt-3">
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i><br>
                            <span class="font-10 d-block mt-n1">138 reviews</span>
                        </a>
                        <a href="#">
                            <h5 class="mt-1">Commuter Backpack</h5>
                            <span class="color-green-dark font-10">In Stock</span>
                        </a>
                        <h1 class="mt-1 mb-n2 font-800">EUR 99</h1>
                    </div>

                    <div class="col-6 mb-4">
                        <a href="#"><img src="{{ asset('front-theme/images/pictures/18s.jpg') }}" class="rounded-sm shadow-xl img-fluid" alt="New arrival"></a>
                        <a href="#" class="d-block mt-3">
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star color-yellow-dark"></i>
                            <i class="fa fa-star-half color-yellow-dark"></i><br>
                            <span class="font-10 d-block mt-n1">64 reviews</span>
                        </a>
                        <a href="#">
                            <h5 class="mt-1">Utility Jacket</h5>
                            <span class="color-green-dark font-10">In Stock</span>
                        </a>
                        <h1 class="mt-1 mb-n2 font-800">EUR 129</h1>
                    </div>
                </div>

                @auth
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-full btn-sm rounded-s mb-2 font-600 gradient-highlight">Open Admin</a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                        @csrf
                        <button type="submit" class="btn btn-full btn-sm rounded-s mb-3 font-600 btn-border border-gray-dark color-gray-dark">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-full btn-sm rounded-s mb-2 font-600 gradient-highlight">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-full btn-sm rounded-s mb-3 font-600 btn-border border-gray-dark color-gray-dark">Register</a>
                @endauth
            </div>
        </div>
    </div>

    <div id="menu-cart" class="menu menu-box-bottom rounded-m bg-theme" data-menu-height="340">
        <div class="menu-title">
            <p class="color-highlight">Customize your Order</p>
            <h1 class="font-22 font-800">Add to Cart</h1>
            <a href="#" class="close-menu"><i class="fa fa-times-circle"></i></a>
        </div>
        <div class="content">
            <div class="d-flex mb-4">
                <div>
                    <img src="{{ asset('front-theme/images/pictures/17s.jpg') }}" class="rounded-sm" width="80" alt="Product">
                </div>
                <div class="w-100 ms-3 pt-1">
                    <h6 class="font-500 font-14 pb-2">Transit Pro Backpack</h6>
                    <h4>EUR 89</h4>
                </div>
            </div>

            <div class="row mb-0">
                <div class="col-6">
                    <div class="input-style has-borders no-icon input-style-always-active mb-4">
                        <label for="cart-size" class="color-highlight">Size</label>
                        <select id="cart-size">
                            <option selected>Standard</option>
                            <option>Large</option>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="input-style has-borders no-icon input-style-always-active mb-4">
                        <label for="cart-qty" class="color-highlight">Quantity</label>
                        <select id="cart-qty">
                            <option selected>1</option>
                            <option>2</option>
                            <option>3</option>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                    </div>
                </div>
            </div>

            <a href="#" data-menu="menu-added" class="close-menu btn btn-full gradient-blue font-13 btn-m font-600 mt-2 rounded-s">Add to cart</a>
        </div>
    </div>

    <div id="menu-heart" class="menu menu-box-modal rounded-m" data-menu-hide="900" data-menu-width="250" data-menu-height="170">
        <h1 class="text-center mt-3 pt-2"><i class="fa fa-check-circle color-green-dark fa-3x"></i></h1>
        <h3 class="text-center pt-2">Saved to favorites</h3>
    </div>

    <div id="menu-added" class="menu menu-box-modal rounded-m" data-menu-hide="900" data-menu-width="250" data-menu-height="170">
        <h1 class="text-center mt-3 pt-2"><i class="fa fa-shopping-bag color-brown-dark fa-3x"></i></h1>
        <h3 class="text-center pt-2">Added to cart</h3>
    </div>

    <div id="menu-main" class="menu menu-box-left rounded-0" data-menu-width="280">
        @include('front.mobile.menu-main')
    </div>

    <div id="menu-colors" class="menu menu-box-bottom rounded-m" data-menu-load="/front-theme/menu-colors.html" data-menu-height="480"></div>
    <div id="menu-share" class="menu menu-box-bottom rounded-m" data-menu-load="/front-theme/menu-share.html" data-menu-height="370"></div>
</div>

<script defer src="{{ asset('front-theme/scripts/bootstrap.min.js') }}"></script>
<script defer src="{{ asset('front-theme/scripts/custom.js') }}"></script>
</body>
</html>
