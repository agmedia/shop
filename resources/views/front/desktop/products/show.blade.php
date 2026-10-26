@extends('front.desktop.layouts.store')

@php
    $translation = $product->translations->firstWhere('locale', $locale)
        ?? $product->translations->firstWhere('locale', $fallbackLocale);
    $manufacturerTranslation = $product->manufacturer?->translations?->firstWhere('locale', $locale)
        ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale);
    $manufacturerEnabled = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
    $formatGrossPrice = static fn ($value): string => number_format((float) $value, 2).' €';
    $formatGrossDecimal = static fn ($value): string => number_format((float) $value, 2, '.', '');
    $formatPriceData = static function (array $priceData) use ($formatGrossPrice, $formatGrossDecimal): array {
        $oldGross = $priceData['old_gross'] ?? null;
        $lowestGross = $priceData['lowest_30_days_gross'] ?? null;
        $discountPercent = (int) ($priceData['discount_percent'] ?? 0);

        return [
            'current' => $formatGrossPrice((float) ($priceData['current_gross'] ?? 0)),
            'current_value' => $formatGrossDecimal((float) ($priceData['current_gross'] ?? 0)),
            'old' => $oldGross !== null ? $formatGrossPrice((float) $oldGross) : '',
            'discount_percent' => $discountPercent > 0 ? (string) $discountPercent : '',
            'lowest_30_days' => $lowestGross !== null
                ? __('ui.product.lowest_price_30_days', ['price' => $formatGrossPrice((float) $lowestGross)])
                : '',
        ];
    };
    $pricePresenter = app(\App\Services\Pricing\ProductPricePresentationService::class);
    $authUser = auth()->user();
    $productPriceData = $formatPriceData($pricePresentation);
    $optionPriceData = static function ($row) use ($pricePresenter, $product, $authUser, $formatPriceData): array {
        $storedBase = $row->price_override !== null
            ? (float) $row->price_override
            : (float) $product->base_price;

        return $formatPriceData($pricePresenter->forStoredBase($product, $storedBase, $authUser));
    };
    $currentPrice = $productPriceData['current'];
    $oldPrice = isset($pricePresentation['old_gross']) && $pricePresentation['old_gross'] !== null
        ? $formatGrossPrice((float) $pricePresentation['old_gross'])
        : null;
    $discountPercent = (int) ($pricePresentation['discount_percent'] ?? 0);
    $lowest30DaysPrice = isset($pricePresentation['lowest_30_days_gross']) && $pricePresentation['lowest_30_days_gross'] !== null
        ? $formatGrossPrice((float) $pricePresentation['lowest_30_days_gross'])
        : null;
    $isWishlisted = app(\App\Services\Front\WishlistService::class)->has((int) $product->id);
    $preferWebp = (bool) ($storeSettings['images']['use_webp'] ?? false);

    $mediaItems = $product->relationLoaded('media')
        ? $product->media
            ->whereIn('collection_name', ['product_main', 'product_gallery'])
            ->sortBy(static fn ($mediaItem) => (int) ($mediaItem->order_column ?? 0))
            ->values()
        : collect();
    $usableMediaItems = $mediaItems
        ->filter(fn ($mediaItem) => \App\Support\Media\MediaUrl::hasUsableSource($mediaItem, ['detail_960x960', 'thumb_100x100']))
        ->values();
    $mainMedia = $usableMediaItems->firstWhere('collection_name', 'product_main')
        ?? $usableMediaItems->firstWhere('collection_name', 'product_gallery')
        ?? $product->getMedia('*')
            ->whereIn('collection_name', ['product_main', 'product_gallery'])
            ->first(fn ($mediaItem) => \App\Support\Media\MediaUrl::hasUsableSource($mediaItem, ['detail_960x960', 'thumb_100x100']));

    $galleryItems = collect();
    if ($mainMedia) {
        $galleryItems->push($mainMedia);
    }
    foreach ($usableMediaItems as $mediaItem) {
        if ($mainMedia && (int) $mediaItem->id === (int) $mainMedia->id) {
            continue;
        }

        $galleryItems->push($mediaItem);
    }

    $gallery = $galleryItems
        ->unique(fn ($mediaItem) => (int) $mediaItem->id)
        ->map(function ($mediaItem) use ($translation, $product, $preferWebp) {
            $displayUrl = \App\Support\Media\MediaUrl::conversion($mediaItem, 'detail_960x960', $preferWebp);
            $thumbUrl = \App\Support\Media\MediaUrl::conversion($mediaItem, 'thumb_100x100', $preferWebp);

            return [
                'id' => (int) $mediaItem->id,
                'full' => (string) ($displayUrl ?? $mediaItem->getUrl()),
                'display' => (string) $displayUrl,
                'thumb' => (string) ($thumbUrl ?? $mediaItem->getUrl()),
                'alt' => (string) ($translation?->name ?? $product->code),
            ];
        })
        ->values();

    $allOptionRows = $product->visibleOptionRows();
    $availableOptionRows = $product->availableOptionRows();
    $optionRows = $availableOptionRows->isNotEmpty() ? $availableOptionRows : $allOptionRows;
    $isPurchasable = $product->storefrontIsPurchasable();
    $hasLinkedOptions = $optionRows->contains(fn ($row) => (int) ($row->parent_option_value_id ?? 0) > 0);
    $primaryOptionLabel = __('ui.cart.modal.option');
    $secondaryOptionLabel = __('ui.cart.modal.option');
    $linkedPrimaryValues = collect();
    if ($hasLinkedOptions) {
        $firstLinkedRow = $optionRows->first(fn ($row) => (int) ($row->parent_option_value_id ?? 0) > 0);
        $parentOptionTranslation = $firstLinkedRow?->parentOptionValue?->option?->translations?->firstWhere('locale', $locale)
            ?? $firstLinkedRow?->parentOptionValue?->option?->translations?->firstWhere('locale', $fallbackLocale)
            ?? $firstLinkedRow?->parentOptionValue?->option?->translations?->first();
        $childOptionTranslation = $firstLinkedRow?->optionValue?->option?->translations?->firstWhere('locale', $locale)
            ?? $firstLinkedRow?->optionValue?->option?->translations?->firstWhere('locale', $fallbackLocale)
            ?? $firstLinkedRow?->optionValue?->option?->translations?->first();
        $primaryOptionLabel = trim((string) ($parentOptionTranslation?->name ?? '')) ?: __('ui.cart.modal.option');
        $secondaryOptionLabel = trim((string) ($childOptionTranslation?->name ?? '')) ?: __('ui.cart.modal.option');

        $linkedPrimaryValues = $optionRows
            ->filter(fn ($row) => (int) ($row->parent_option_value_id ?? 0) > 0)
            ->map(function ($row) use ($locale, $fallbackLocale): array {
                $translation = $row->parentOptionValue?->translations?->firstWhere('locale', $locale)
                    ?? $row->parentOptionValue?->translations?->firstWhere('locale', $fallbackLocale)
                    ?? $row->parentOptionValue?->translations?->first();
                $label = trim((string) ($translation?->name ?? $row->parentOptionValue?->code ?? ''));

                return [
                    'id' => (int) ($row->parent_option_value_id ?? 0),
                    'label' => $label,
                ];
            })
            ->unique('id')
            ->values();
    }

    $firstCategory = $product->categories->first();
    $firstCategoryTranslation = $firstCategory?->translations?->firstWhere('locale', $locale)
        ?? $firstCategory?->translations?->firstWhere('locale', $fallbackLocale);
    $fitFinderEnabled = (bool) ($storeSettings['product']['fit_finder_enabled'] ?? false);
    $fitFinderSavedSize = trim((string) ($fitFinderSelection['size_label'] ?? ''));
    $fitFinderSavedSignature = trim((string) ($fitFinderSelection['size_signature'] ?? ''));
    $desktopDefaultCols = in_array((int) ($storeSettings['product']['desktop_default_cols'] ?? 4), [4, 5], true)
        ? (int) ($storeSettings['product']['desktop_default_cols'] ?? 4)
        : 4;
    $requestedGridCols = request()->query('cols', request()->cookie('front_grid_cols', $desktopDefaultCols));
    $preferredGridCols = in_array((int) $requestedGridCols, [1, 2, 3, 4, 5], true)
        ? (int) $requestedGridCols
        : $desktopDefaultCols;
    $hasProductStory = ! empty($translation?->description) || ! empty($translation?->excerpt);
    $reviewSummary = $product->approvedCommentSummary([$locale, $fallbackLocale]);
    $colorVariants = $colorVariants ?? collect();
@endphp

@section('title', $translation?->name ?? 'Product')
@section('main_class', 'w-full px-0 py-8')

@section('content')
    <style>
        html {
            scroll-behavior: smooth;
        }

        .product-detail-layout {
            display: grid;
            gap: 2rem;
        }

        .product-ipad-slider {
            display: block;
            margin-top: -2rem;
            padding-bottom: 0;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .product-default-grid {
            display: none;
        }

        .product-detail-gallery-frame {
            display: block;
            aspect-ratio: 2 / 3;
        }

        .product-detail-gallery-image {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
        }

        @media (max-width: 768px) {
            .product-detail-layout {
                gap: .75rem;
            }

            .product-ipad-slider .splide__track {
                overflow: hidden;
            }

            .product-ipad-slider .splide__slide > button {
                display: block;
                width: 100%;
                aspect-ratio: 2 / 3;
                overflow: hidden;
                border: 0;
                padding: 0;
                background: #f8fafc;
            }

            .product-ipad-slider .splide__slide img {
                display: block;
                width: 100%;
                max-width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: top center;
            }

            .product-ipad-slider .splide__pagination {
                bottom: .9rem !important;
                gap: .35rem;
                z-index: 20;
            }

            .product-ipad-slider .splide__pagination__page {
                width: 10px;
                height: 10px;
                margin: 0;
                opacity: .95;
                background: #fff !important;
                border: 2px solid transparent;
            }

            .product-ipad-slider .splide__pagination__page.is-active {
                transform: none;
                background: #0f172a !important;
                border-color: #fff;
            }

            .product-detail-layout > aside {
                padding-top: .5rem;
            }
        }

        @media (min-width: 769px) {
            .product-detail-layout {
                grid-template-columns: minmax(0, 1.2fr) minmax(360px, 1fr);
            }

            .product-detail-layout > aside {
                position: sticky;
                top: 4rem;
                align-self: start;
            }

            .product-ipad-slider {
                display: none;
                margin-top: 0;
            }

            .product-default-grid {
                display: block;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }
        }

        [data-product-detail-form] .product-size-radio:focus-visible + .product-size-label {
            outline: 3px solid #005fcc;
            outline-offset: 3px;
        }

        [data-product-detail-form] .product-size-radio:checked + .product-size-label,
        [data-product-detail-form] .product-size-radio:checked + .product-size-label span {
            border-color: #0f172a;
            background: #0f172a;
            color: #ffffff !important;
        }

        .product-color-variant-link {
            display: inline-flex;
            width: 2.25rem;
            height: 2.25rem;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .product-color-variant-link:hover,
        .product-color-variant-link:focus-visible {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
            outline: none;
        }

        .product-color-variant-link[aria-current="true"] {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }

        .product-color-variant-swatch {
            display: block;
            width: 1.625rem;
            height: 1.625rem;
            border: 1px solid rgba(15, 23, 42, 0.16);
            background-color: #f8fafc;
        }

        [data-fit-finder-modal] [data-fit-step].hidden {
            display: none;
        }

        [data-fit-finder-option][aria-pressed="true"] {
            border-color: #0f172a;
            background: #0f172a;
            color: #ffffff;
        }

        [data-fit-finder-result-row].is-selected {
            border-color: #166534;
            background: #f0fdf4;
        }

        [data-fit-finder-result-bar] {
            width: 0;
            transition: width .3s ease;
        }

        [data-fit-finder-modal] [data-fit-finder-timeline] {
            position: relative;
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            align-items: center;
            gap: .6rem;
            margin-top: .75rem;
        }

        [data-fit-finder-modal] [data-fit-finder-timeline]::before {
            content: "";
            position: absolute;
            left: .35rem;
            right: .35rem;
            top: .38rem;
            height: 1px;
            background: #cbd5e1;
            z-index: 0;
        }

        [data-fit-finder-modal] [data-fit-timeline-point] {
            position: relative;
            z-index: 1;
            width: .78rem;
            height: .78rem;
            border: 1px solid #94a3b8;
            background: #ffffff;
        }

        [data-fit-finder-modal] [data-fit-timeline-label] {
            margin-top: .35rem;
            font-size: 10px;
            line-height: 1.2;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
        }

        [data-fit-finder-modal] [data-fit-timeline-item].is-current [data-fit-timeline-point] {
            border-color: #0f172a;
            background: #0f172a;
        }

        [data-fit-finder-modal] [data-fit-timeline-item].is-done [data-fit-timeline-point] {
            border-color: #0f172a;
            background: #e2e8f0;
        }

        [data-fit-finder-modal] [data-fit-timeline-item].is-current [data-fit-timeline-label] {
            color: #0f172a;
        }

        [data-fit-finder-modal] .fit-finder-range {
            height: 1.15rem;
        }

        [data-fit-finder-modal] .fit-finder-range::-webkit-slider-runnable-track {
            height: 2px;
            background: #cbd5e1;
        }

        [data-fit-finder-modal] .fit-finder-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px;
            height: 12px;
            margin-top: -5px;
            border: 1px solid #0f172a;
            border-radius: 0;
            background: #0f172a;
            cursor: pointer;
        }

        [data-fit-finder-modal] .fit-finder-range::-moz-range-track {
            height: 2px;
            background: #cbd5e1;
            border: 0;
        }

        [data-fit-finder-modal] .fit-finder-range::-moz-range-thumb {
            width: 12px;
            height: 12px;
            border: 1px solid #0f172a;
            border-radius: 0;
            background: #0f172a;
            cursor: pointer;
        }

        @media (min-width: 1024px) {
            [data-fit-finder-modal] [data-fit-step] {
                padding-right: .25rem;
            }
        }
    </style>

    @if ($topBlocks->isNotEmpty())
        <section class="mb-8">
            @include('components.content-placement', ['items' => $topBlocks])
        </section>
    @endif

    <section class="product-detail-layout" data-product-detail>
        <div class="bg-white xl:pl-8">
            @if ($gallery->isNotEmpty())
                @php
                    $displayGallery = $gallery->values();
                    $topGallery = $displayGallery->take(2)->values();
                    $bottomGallery = $displayGallery->slice(2)->values();
                    $bottomCount = $bottomGallery->count();
                    $middleBottomCount = $bottomCount;
                    $tailBottomCount = 0;

                    if ($bottomCount > 0) {
                        $remainder = $bottomCount % 3;
                        if ($remainder === 1 && $bottomCount >= 4) {
                            $tailBottomCount = 4;
                            $middleBottomCount = $bottomCount - 4;
                        } elseif ($remainder === 2) {
                            $tailBottomCount = 2;
                            $middleBottomCount = $bottomCount - 2;
                        }
                    }

                    $middleBottomGallery = $bottomGallery->take($middleBottomCount)->values();
                    $tailBottomGallery = $bottomGallery->slice($middleBottomCount)->values();
                @endphp

                <div id="product-mobile-splide" class="product-ipad-slider splide slider-no-arrows" data-product-splide>
                    <div class="splide__track">
                        <ul class="splide__list">
                            @foreach ($displayGallery as $index => $image)
                                <li class="splide__slide">
                                    <button
                                        type="button"
                                        data-gallery-thumb
                                        data-index="{{ $index }}"
                                        data-full="{{ $image['full'] }}"
                                        data-alt="{{ $image['alt'] }}"
                                        data-gallery-open="{{ $index }}"
                                        aria-label="{{ $image['alt'] }}"
                                    >
                                        <img
                                            src="{{ $image['display'] }}"
                                            alt="{{ $image['alt'] }}"
                                            class="block h-auto w-full bg-slate-50"
                                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                            decoding="async"
                                        >
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="product-default-grid block">
                    <div class="grid gap-1 md:grid-cols-2">
                        @foreach ($topGallery as $index => $image)
                            <button
                                type="button"
                                class="product-detail-gallery-frame overflow-hidden border border-slate-200 bg-slate-50"
                                data-gallery-thumb
                                data-index="{{ $index }}"
                                data-full="{{ $image['full'] }}"
                                    data-alt="{{ $image['alt'] }}"
                                    data-gallery-open="{{ $index }}"
                                    aria-label="{{ $image['alt'] }}"
                                >
                                    <img
                                        src="{{ $image['display'] }}"
                                        alt="{{ $image['alt'] }}"
                                        class="product-detail-gallery-image bg-slate-50"
                                        loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                        decoding="async"
                                        @if ($index === 0) data-gallery-main @endif
                                        @if ($index === 1) data-gallery-secondary @endif
                                    >
                            </button>
                        @endforeach
                    </div>

                    @if ($middleBottomGallery->isNotEmpty() || $tailBottomGallery->isNotEmpty())
                        @if ($middleBottomGallery->isNotEmpty())
                            <div class="mt-1 grid grid-cols-3 gap-1">
                                @foreach ($middleBottomGallery as $bottomIndex => $image)
                                    @php $index = $bottomIndex + 2; @endphp
                                    <button
                                        type="button"
                                        class="product-detail-gallery-frame overflow-hidden border border-slate-200 bg-slate-50"
                                        data-gallery-thumb
                                        data-index="{{ $index }}"
                                        data-full="{{ $image['full'] }}"
                                        data-alt="{{ $image['alt'] }}"
                                        data-gallery-open="{{ $index }}"
                                        aria-label="{{ $image['alt'] }}"
                                    >
                                        <img
                                            src="{{ $image['display'] }}"
                                            alt="{{ $image['alt'] }}"
                                            class="product-detail-gallery-image bg-slate-50"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if ($tailBottomGallery->isNotEmpty())
                            <div class="mt-1 grid grid-cols-2 gap-1">
                                @foreach ($tailBottomGallery as $tailIndex => $image)
                                    @php $index = $tailIndex + 2 + $middleBottomCount; @endphp
                                    <button
                                        type="button"
                                        class="product-detail-gallery-frame overflow-hidden border border-slate-200 bg-slate-50"
                                        data-gallery-thumb
                                        data-index="{{ $index }}"
                                        data-full="{{ $image['full'] }}"
                                        data-alt="{{ $image['alt'] }}"
                                        data-gallery-open="{{ $index }}"
                                        aria-label="{{ $image['alt'] }}"
                                    >
                                        <img
                                            src="{{ $image['display'] }}"
                                            alt="{{ $image['alt'] }}"
                                            class="product-detail-gallery-image bg-slate-50"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @else
                <div class="flex min-h-[420px] items-center justify-center border border-slate-200 bg-slate-100 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {{ __('ui.product.no_image') }}
                </div>
            @endif
        </div>

        <aside class="bg-white p-5">
            <nav aria-label="Breadcrumb" class="text-xs text-slate-500">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a></li>
                    <li class="text-slate-400">&gt;</li>
                    <li><a href="{{ route('shop.index') }}" class="hover:text-slate-700">{{ __('ui.shop.page_title') }}</a></li>
                    @if ($firstCategoryTranslation)
                        <li class="text-slate-400">&gt;</li>
                        <li>
                            <a href="{{ route('categories.show', ['slug' => $firstCategoryTranslation->slug]) }}" class="hover:text-slate-700">
                                {{ $firstCategoryTranslation->name }}
                            </a>
                        </li>
                    @endif
                    <li class="text-slate-400">&gt;</li>
                    <li class="text-slate-700">{{ $translation?->name ?? $product->code }}</li>
                </ol>
            </nav>

            <div class="mt-4">
                <div>
                    @if ((int) ($reviewSummary['count'] ?? 0) > 0)
                        <div class="mb-2">
                            @include('front.partials.product-review-summary', [
                                'count' => (int) ($reviewSummary['count'] ?? 0),
                                'average' => (float) ($reviewSummary['avg'] ?? 0),
                                'href' => '#product-comments',
                            ])
                        </div>
                    @endif
                    <h1 class="text-2xl font-extrabold leading-tight text-slate-900">{{ $translation?->name ?? $product->code }}</h1>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.product.sku') }}: <span data-product-sku-value>{{ $product->sku ?: $product->code ?: 'n/a' }}</span></p>
                    @if ($manufacturerTranslation && $manufacturerEnabled)
                        <p class="mt-1 text-xs text-slate-600">
                            <a href="{{ route('manufacturers.show', ['slug' => $manufacturerTranslation->slug]) }}" class="font-semibold text-slate-700 hover:text-slate-900">{{ $manufacturerTranslation->name }}</a>
                        </p>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xl font-semibold text-slate-900" data-product-price-current>{{ $currentPrice }}</p>
                        <span class="{{ $discountPercent > 0 ? 'inline-flex' : 'hidden' }} h-7 items-center border border-rose-600 bg-rose-600 px-2 text-xs font-bold text-white" data-product-price-discount>
                            @if ($discountPercent > 0)
                                -{{ $discountPercent }}%
                            @endif
                        </span>
                    </div>
                    <p class="{{ $oldPrice ? '' : 'hidden' }} mt-1 text-sm text-slate-500 line-through" data-product-price-old>{{ $oldPrice ?: '' }}</p>
                    <p class="{{ $lowest30DaysPrice ? '' : 'hidden' }} mt-1 text-xs text-slate-600" data-product-price-lowest>{{ $lowest30DaysPrice ? __('ui.product.lowest_price_30_days', ['price' => $lowest30DaysPrice]) : '' }}</p>
                </div>
            </div>

            @if ($colorVariants->isNotEmpty())
                <div class="mt-5 border-y border-slate-200 py-4" data-product-color-variants>
                    <p class="text-sm font-extrabold text-slate-900">{{ __('ui.product.color_variants') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($colorVariants as $variant)
                            @php
                                $swatchStyle = $variant['swatch_image_url']
                                    ? 'background-image:url('.$variant['swatch_image_url'].');background-size:cover;background-position:center;background-repeat:no-repeat;background-color:transparent;'
                                    : $variant['swatch_style'];
                            @endphp
                            <a
                                href="{{ $variant['url'] }}"
                                class="product-color-variant-link"
                                title="{{ $variant['label'] }}"
                                aria-label="{{ $variant['label'] }}"
                                @if ($variant['is_current']) aria-current="true" @endif
                                data-color-variant-link
                                data-color-variant-label="{{ $variant['label'] }}"
                            >
                                <span class="product-color-variant-swatch" style="{{ $swatchStyle }}" data-color-variant-swatch aria-hidden="true"></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('cart.items.store') }}"
                class="mt-6 space-y-4"
                data-product-detail-form
                data-ga4-add-to-cart-form
                data-ga4-item-id="{{ (string) ($product->sku ?: $product->id) }}"
                data-ga4-item-name="{{ $translation?->name ?? $product->code }}"
                data-ga4-item-price="{{ $productPriceData['current_value'] }}"
                data-ga4-item-brand="{{ (string) ($manufacturerTranslation?->name ?? '') }}"
                data-ga4-item-category="{{ (string) ($firstCategoryTranslation?->name ?? '') }}"
                data-ga4-currency="EUR"
                data-product-base-sku="{{ (string) ($product->sku ?: $product->code ?: '') }}"
                data-product-fallback-id="{{ (int) $product->id }}"
                data-product-name="{{ $translation?->name ?? $product->code }}"
                data-product-image="{{ (string) (($gallery->first()['full'] ?? '') ?: '') }}"
                data-cart-url="{{ route('cart.index') }}"
                data-modal-continue="{{ __('ui.cart.modal.continue') }}"
                data-modal-go-cart="{{ __('ui.cart.modal.go_to_cart') }}"
                data-modal-option="{{ __('ui.cart.modal.option') }}"
                data-modal-quantity="{{ __('ui.cart.modal.quantity') }}"
                data-option-error-required="{{ __('ui.cart.errors.select_size') }}"
                data-option-error-unavailable="{{ __('ui.cart.status.unavailable') }}"
                data-product-default-price-current="{{ $productPriceData['current'] }}"
                data-product-default-price-current-value="{{ $productPriceData['current_value'] }}"
                data-product-default-price-old="{{ $productPriceData['old'] }}"
                data-product-default-price-discount="{{ $productPriceData['discount_percent'] }}"
                data-product-default-price-lowest="{{ $productPriceData['lowest_30_days'] }}"
            >
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($optionRows->isNotEmpty())
                    <div>
                        <div class="mb-4 flex flex-col items-start gap-2 sm:mb-3 sm:flex-row sm:items-center sm:justify-between">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-900">{{ $hasLinkedOptions ? __('ui.product.select_options') : __('ui.product.select_size') }} <span class="text-rose-600">*</span></label>
                            <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 sm:w-auto sm:justify-end">
                                @if ($fitFinderEnabled && $optionRows->count() > 1)
                                    @if ($fitFinderSavedSize !== '')
                                        <button type="button" class="inline-flex items-baseline gap-1 text-xs font-normal tracking-normal text-slate-600 underline underline-offset-2 hover:text-slate-900 sm:text-sm" data-fit-finder-open>
                                            <span>{{ __('ui.product.fit_finder.saved_size_prefix') }}</span>
                                            <span class="font-semibold text-slate-800">{{ $fitFinderSavedSize }}</span>
                                        </button>
                                    @else
                                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline underline-offset-2 hover:text-slate-900" data-fit-finder-open>
                                            {{ __('ui.product.fit_finder.trigger') }}
                                        </button>
                                    @endif
                                @endif
                                @if (!empty($sizeGuide))
                                    <button type="button" class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline underline-offset-2 hover:text-slate-900" data-size-guide-open>
                                        {{ __('ui.product.size_guide') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                        @if ($hasLinkedOptions)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $primaryOptionLabel }}</label>
                                    <select class="h-10 w-full border border-slate-300 bg-white px-3 text-sm text-slate-700" data-linked-option-primary>
                                        <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                        @foreach ($linkedPrimaryValues as $primaryValue)
                                            <option value="{{ $primaryValue['id'] }}">{{ $primaryValue['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $secondaryOptionLabel }}</label>
                                    <select name="product_option_value_id" class="h-10 w-full border border-slate-300 bg-white px-3 text-sm text-slate-700" data-linked-option-secondary disabled>
                                        <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                        @foreach ($optionRows as $row)
                                            @php
                                                $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                                    ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                                    ?? $row->optionValue?->translations?->first();
                                                $secondaryLabel = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                                                $rowPriceData = $optionPriceData($row);
                                            @endphp
                                            <option
                                                value="{{ $row->id }}"
                                                data-parent-id="{{ (int) ($row->parent_option_value_id ?? 0) }}"
                                                data-option-sku="{{ (string) ($row->sku ?: '') }}"
                                                data-option-price-current="{{ $rowPriceData['current'] }}"
                                                data-option-price-current-value="{{ $rowPriceData['current_value'] }}"
                                                data-option-price-old="{{ $rowPriceData['old'] }}"
                                                data-option-price-discount="{{ $rowPriceData['discount_percent'] }}"
                                                data-option-price-lowest="{{ $rowPriceData['lowest_30_days'] }}"
                                            >{{ $secondaryLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($optionRows as $row)
                                    @php
                                        $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                            ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                            ?? $row->optionValue?->translations?->first();
                                        $label = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                                        $inputId = 'product-detail-pov-'.$product->id.'-'.$row->id;
                                        $rowPriceData = $optionPriceData($row);
                                    @endphp
                                    <span class="inline-flex">
                                        <input
                                            id="{{ $inputId }}"
                                            type="radio"
                                            name="product_option_value_id"
                                            value="{{ $row->id }}"
                                            class="sr-only product-size-radio"
                                            data-size-label="{{ $label }}"
                                            data-option-sku="{{ (string) ($row->sku ?: '') }}"
                                            data-option-price-current="{{ $rowPriceData['current'] }}"
                                            data-option-price-current-value="{{ $rowPriceData['current_value'] }}"
                                            data-option-price-old="{{ $rowPriceData['old'] }}"
                                            data-option-price-discount="{{ $rowPriceData['discount_percent'] }}"
                                            data-option-price-lowest="{{ $rowPriceData['lowest_30_days'] }}"
                                        >
                                        <label for="{{ $inputId }}" class="product-size-label inline-flex h-10 min-w-10 cursor-pointer items-center justify-center border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:bg-slate-100">
                                            <span>{{ $label }}</span>
                                        </label>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <p class="hidden mt-2 text-xs font-semibold text-rose-600" data-option-error>
                            {{ __('ui.cart.errors.select_size') }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 sm:gap-2">
                    @if ($isPurchasable)
                        <div class="inline-flex h-10 items-stretch" data-qty-control>
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-xl font-semibold text-slate-700 hover:bg-slate-100" data-qty-dec aria-label="Decrease quantity">-</button>
                            <input type="text" name="quantity" value="1" inputmode="numeric" readonly aria-label="{{ __('ui.cart.modal.quantity') }}" class="h-10 w-10 border-y border-r border-slate-300 border-l-0 bg-white p-0 text-center text-base font-normal text-slate-900" data-qty-input>
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-xl font-semibold text-slate-700 hover:bg-slate-100" data-qty-inc aria-label="Increase quantity">+</button>
                        </div>

                        <button type="submit" class="inline-flex h-10 min-w-0 items-center justify-center gap-2 border border-slate-900 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-700 sm:px-4 sm:text-sm" aria-label="{{ __('ui.product.add_to_cart') }}">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 9h10l-1 10H8L7 9Z"></path>
                                <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
                            </svg>
                            <span class="text-center leading-tight sm:truncate">{{ __('ui.product.add_to_cart') }}</span>
                        </button>
                    @else
                        <div></div>
                        <button type="button" disabled class="inline-flex h-10 min-w-0 items-center justify-center gap-2 border border-slate-300 bg-slate-100 px-3 text-xs font-semibold text-slate-500 sm:px-4 sm:text-sm" aria-label="{{ __('ui.product.unavailable') }}">
                            <span class="text-center leading-tight sm:truncate">{{ __('ui.product.unavailable') }}</span>
                        </button>
                    @endif

                    <button
                        type="submit"
                        form="wishlist-product-{{ $product->id }}"
                        class="inline-flex h-10 w-10 items-center justify-center border transition sm:h-10 sm:w-10 {{ $isWishlisted ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-700' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-900 hover:text-slate-900' }}"
                        aria-label="{{ $isWishlisted ? __('ui.wishlist.remove') : __('ui.wishlist.add') }}"
                        data-wishlist-button
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.8 8.6c0 5.9-8.8 10.9-8.8 10.9S3.2 14.5 3.2 8.6a4.8 4.8 0 0 1 8.8-2.7 4.8 4.8 0 0 1 8.8 2.7Z"></path>
                        </svg>
                    </button>
                </div>
            </form>

            @if ($fitFinderEnabled && $optionRows->count() > 1)
                <div
                    class="fixed inset-0 z-[90] hidden flex items-center justify-center overflow-y-auto bg-black/55 p-4 backdrop-blur-[2px]"
                    data-fit-finder-modal
                    data-text-error-height="{{ __('ui.product.fit_finder.errors.height') }}"
                    data-text-error-weight="{{ __('ui.product.fit_finder.errors.weight') }}"
                    data-text-error-age="{{ __('ui.product.fit_finder.errors.age') }}"
                    data-text-step-template="{{ __('ui.product.fit_finder.step_of', ['current' => '__CURRENT__', 'total' => '__TOTAL__']) }}"
                    data-text-recommendation-ready="{{ __('ui.product.fit_finder.recommendation_ready') }}"
                    data-text-summary-template="{{ __('ui.product.fit_finder.summary', ['size' => '__SIZE__', 'percent' => '__PERCENT__']) }}"
                    data-text-cta-template="{{ __('ui.product.fit_finder.add_cta', ['size' => '__SIZE__']) }}"
                    data-text-trigger="{{ __('ui.product.fit_finder.trigger') }}"
                    data-text-saved-prefix="{{ __('ui.product.fit_finder.saved_size_prefix') }}"
                    data-fit-save-url="{{ route('products.fit_finder.preferences') }}"
                    data-fit-product-id="{{ (int) $product->id }}"
                    data-fit-initial-height="{{ (string) ($fitFinderSelection['height'] ?? '') }}"
                    data-fit-initial-weight="{{ (string) ($fitFinderSelection['weight'] ?? '') }}"
                    data-fit-initial-age="{{ (string) ($fitFinderSelection['age'] ?? '') }}"
                    data-fit-initial-fit="{{ (string) ($fitFinderSelection['fit'] ?? 'average') }}"
                    data-fit-initial-chest="{{ (string) ($fitFinderSelection['chest'] ?? 'average') }}"
                    data-fit-initial-belly="{{ (string) ($fitFinderSelection['belly'] ?? 'average') }}"
                    data-fit-initial-size="{{ $fitFinderSavedSize }}"
                    data-fit-initial-size-signature="{{ $fitFinderSavedSignature }}"
                    aria-hidden="true"
                >
                    <div class="mx-auto flex w-full max-w-2xl flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ __('ui.product.fit_finder.title') }}</p>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 opacity-0 transition-opacity" data-fit-save-indicator aria-live="polite">Spremljeno</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center border border-slate-300 text-sm font-bold text-slate-700 hover:bg-slate-100"
                                        data-fit-finder-help-toggle
                                        aria-expanded="false"
                                        aria-controls="fit-finder-help-panel-{{ $product->id }}"
                                        aria-label="{{ __('ui.product.fit_finder.help_toggle') }}"
                                    >
                                        <span data-fit-help-icon-closed>?</span>
                                        <span class="hidden text-xs leading-none" data-fit-help-icon-open>▴</span>
                                    </button>
                                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center border border-slate-300 text-base text-slate-700 hover:bg-slate-100" data-fit-finder-close aria-label="{{ __('ui.product.size_guide_close') }}">
                                        ×
                                    </button>
                                </div>
                            </div>
                            <div id="fit-finder-help-panel-{{ $product->id }}" class="mt-3 hidden border border-slate-200 bg-white p-3 text-left" data-fit-finder-help-panel>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-700">{{ __('ui.product.fit_finder.help_title') }}</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ __('ui.product.fit_finder.help_body') }}</p>
                            </div>

                            <div class="mt-2" data-fit-finder-timeline>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Mjere</span>
                                </div>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Dob</span>
                                </div>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Fit</span>
                                </div>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Prsa</span>
                                </div>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Trbuh</span>
                                </div>
                                <div class="flex flex-col items-center" data-fit-timeline-item>
                                    <span data-fit-timeline-point></span>
                                    <span data-fit-timeline-label>Rezultat</span>
                                </div>
                            </div>
                            <div>
                                <p class="sr-only" data-fit-finder-progress>{{ __('ui.product.fit_finder.step_of', ['current' => 1, 'total' => 5]) }}</p>
                            </div>
                        </div>

                        <div class="grid gap-7 p-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-stretch">
                            <div class="hidden lg:block lg:h-[290px]">
                                @if ($gallery->isNotEmpty())
                                    <img src="{{ (string) (($gallery->first()['display'] ?? $gallery->first()['full'] ?? '') ?: '') }}" alt="{{ $translation?->name ?? $product->code }}" class="h-full w-full border border-slate-200 bg-slate-50 object-cover object-top">
                                @endif
                            </div>

                            <div class="flex flex-col lg:h-[290px]">
                                <div class="flex-1" data-fit-steps-wrap>
                                    <section data-fit-step="0">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.measurements_title') }}</h3>
                                    <p class="mb-4 text-xs text-slate-600">{{ __('ui.product.fit_finder.measurements_desc') }}</p>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <label class="space-y-2">
                                            <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                <span>{{ __('ui.product.fit_finder.height') }}</span>
                                                <span class="text-slate-800" data-fit-height-value>130</span>
                                            </div>
                                            <input type="range" min="130" max="230" step="1" class="fit-finder-range w-full appearance-none bg-transparent p-0 focus:outline-none" data-fit-height>
                                            <div class="flex items-center justify-between text-[10px] text-slate-400">
                                                <span>130</span>
                                                <span>230</span>
                                            </div>
                                        </label>
                                        <label class="space-y-2">
                                            <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                <span>{{ __('ui.product.fit_finder.weight') }}</span>
                                                <span class="text-slate-800" data-fit-weight-value>35</span>
                                            </div>
                                            <input type="range" min="35" max="220" step="1" class="fit-finder-range w-full appearance-none bg-transparent p-0 focus:outline-none" data-fit-weight>
                                            <div class="flex items-center justify-between text-[10px] text-slate-400">
                                                <span>35</span>
                                                <span>220</span>
                                            </div>
                                        </label>
                                    </div>
                                    <p class="hidden text-xs font-semibold text-rose-600" data-fit-error></p>
                                    </section>

                                    <section data-fit-step="1" class="hidden">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.age_title') }}</h3>
                                    <label class="mt-3 block max-w-[280px] space-y-2">
                                        <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                            <span>{{ __('ui.product.fit_finder.age') }}</span>
                                            <span class="text-slate-800" data-fit-age-value>12</span>
                                        </div>
                                        <input type="range" min="12" max="100" step="1" class="fit-finder-range w-full appearance-none bg-transparent p-0 focus:outline-none" data-fit-age>
                                        <div class="flex items-center justify-between text-[10px] text-slate-400">
                                            <span>12</span>
                                            <span>100</span>
                                        </div>
                                    </label>
                                    <p class="mt-4 mb-2 text-xs text-slate-600">{{ __('ui.product.fit_finder.age_desc') }}</p>
                                    <p class="hidden text-xs font-semibold text-rose-600" data-fit-error></p>
                                    </section>

                                    <section data-fit-step="2" class="hidden">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.fit_title') }}</h3>
                                    <p class="mb-4 text-xs text-slate-600">{{ __('ui.product.fit_finder.fit_desc') }}</p>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-fit="tighter">{{ __('ui.product.fit_finder.fit_tighter') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-fit="average" aria-pressed="true">{{ __('ui.product.fit_finder.fit_average') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-fit="looser">{{ __('ui.product.fit_finder.fit_looser') }}</button>
                                    </div>
                                    </section>

                                    <section data-fit-step="3" class="hidden">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.chest_title') }}</h3>
                                    <p class="mb-4 text-xs text-slate-600">{{ __('ui.product.fit_finder.chest_desc') }}</p>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-chest="slimmer">{{ __('ui.product.fit_finder.chest_slimmer') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-chest="average" aria-pressed="true">{{ __('ui.product.fit_finder.chest_average') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-chest="broader">{{ __('ui.product.fit_finder.chest_broader') }}</button>
                                    </div>
                                    </section>

                                    <section data-fit-step="4" class="hidden">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.belly_title') }}</h3>
                                    <p class="mb-4 text-xs text-slate-600">{{ __('ui.product.fit_finder.belly_desc') }}</p>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-belly="flatter">{{ __('ui.product.fit_finder.belly_flatter') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-belly="average" aria-pressed="true">{{ __('ui.product.fit_finder.belly_average') }}</button>
                                        <button type="button" class="h-10 border border-slate-300 text-xs font-semibold text-slate-700 hover:border-slate-900" data-fit-finder-option data-fit-belly="rounder">{{ __('ui.product.fit_finder.belly_rounder') }}</button>
                                    </div>
                                    </section>

                                    <section data-fit-step="5" class="hidden">
                                    <h3 class="text-base font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('ui.product.fit_finder.result_title') }}</h3>
                                    <p class="mb-4 text-xs text-slate-600">{{ __('ui.product.fit_finder.result_desc') }}</p>
                                    <div class="space-y-2">
                                        <div class="border border-slate-300 p-3" data-fit-finder-result-row="0">
                                            <div class="flex items-center justify-between">
                                                <p class="text-base font-bold text-slate-900" data-fit-finder-result-size="0"></p>
                                                <p class="text-xs font-semibold text-emerald-700" data-fit-finder-result-percent="0"></p>
                                            </div>
                                            <div class="mt-2 h-2 bg-slate-200">
                                                <div class="h-2 bg-emerald-700" data-fit-finder-result-bar="0"></div>
                                            </div>
                                        </div>
                                        <div class="border border-slate-300 p-3" data-fit-finder-result-row="1">
                                            <div class="flex items-center justify-between">
                                                <p class="text-base font-bold text-slate-700" data-fit-finder-result-size="1"></p>
                                                <p class="text-xs font-semibold text-slate-600" data-fit-finder-result-percent="1"></p>
                                            </div>
                                            <div class="mt-2 h-2 bg-slate-200">
                                                <div class="h-2 bg-slate-400" data-fit-finder-result-bar="1"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-4 text-xs text-slate-600" data-fit-finder-summary></p>
                                    </section>
                                </div>

                                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-200 pt-5">
                                    <button type="button" class="inline-flex h-10 items-center border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100" data-fit-prev>
                                        {{ __('ui.product.fit_finder.actions.back') }}
                                    </button>
                                    <button type="button" class="inline-flex h-10 items-center border border-slate-900 bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-700" data-fit-next>
                                        {{ __('ui.product.fit_finder.actions.continue') }}
                                    </button>
                                    <button type="button" class="hidden h-10 items-center border border-slate-900 bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-700" data-fit-apply>
                                        {{ __('ui.product.fit_finder.add_cta', ['size' => '']) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form
                id="wishlist-product-{{ $product->id }}"
                method="POST"
                action="{{ route('wishlist.items.toggle', ['product' => $product->id]) }}"
                class="hidden"
                data-wishlist-form
                data-wishlisted="{{ $isWishlisted ? '1' : '0' }}"
                data-label-add="{{ __('ui.wishlist.add') }}"
                data-label-remove="{{ __('ui.wishlist.remove') }}"
                data-msg-failed="{{ __('ui.wishlist.status.failed') }}"
            >
                @csrf
            </form>

            @if (! empty($translation?->description))
                <div class="mt-6 px-1 pb-2 text-[0.9rem] leading-[1.55] text-slate-700 [&_p]:mb-[15px] [&_p:last-child]:mb-0">{!! $translation->description !!}</div>
            @elseif (! empty($translation?->excerpt))
                <p class="mt-6 px-1 pb-2 text-[0.9rem] leading-[1.55] text-slate-700">{{ $translation->excerpt }}</p>
            @endif

            @include('front.partials.product-attribute-panels', [
                'product' => $product,
                'locale' => $locale,
                'fallbackLocale' => $fallbackLocale,
                'containerClass' => $hasProductStory ? 'mt-5' : 'mt-6',
            ])

            @php
                $commentFormHasErrors = $errors->has('author_name') || $errors->has('author_email') || $errors->has('body') || $errors->has('rating');
                $commentUser = auth()->user();
            @endphp

            <div id="product-comments" class="mt-6 border-t border-slate-200 pt-4" style="scroll-margin-top: 110px;">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-slate-900">{{ __('ui.product.comments_title') }}</h3>
                    <button
                        type="button"
                        class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline underline-offset-2 hover:text-slate-900"
                        data-comment-form-toggle
                        aria-expanded="{{ $commentFormHasErrors ? 'true' : 'false' }}"
                    >
                        {{ __('ui.product.comment_form.toggle') }}
                    </button>
                </div>

                <div class="{{ $commentFormHasErrors ? '' : 'hidden' }} mt-3 border border-slate-200 bg-slate-50 p-3" data-comment-form-panel>
                    <form method="POST" action="{{ route('products.comments.store', ['slug' => $translation?->slug ?? request()->route('slug')]) }}" class="space-y-3">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.product.comment_form.name') }}</label>
                                <input type="text" name="author_name" value="{{ old('author_name', $commentUser?->name ?? '') }}" class="h-10 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" @if($commentUser) readonly @endif>
                                @error('author_name') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.product.comment_form.email') }}</label>
                                <input type="email" name="author_email" value="{{ old('author_email', $commentUser?->email ?? '') }}" class="h-10 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" @if($commentUser) readonly @endif>
                                @error('author_email') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.product.comment_form.rating') }}</label>
                            <select name="rating" class="h-10 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0">
                                <option value="">{{ __('ui.product.comment_form.rating_optional') }}</option>
                                @for ($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}" @selected((string) old('rating') === (string) $i)>{{ $i }} ★</option>
                                @endfor
                            </select>
                            @error('rating') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.product.comment_form.body') }}</label>
                            <textarea name="body" rows="4" class="w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>{{ old('body') }}</textarea>
                            @error('body') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="inline-flex h-10 items-center border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-700">
                            {{ __('ui.product.comment_form.submit') }}
                        </button>
                    </form>
                </div>

                @if (($comments ?? collect())->isNotEmpty())
                    <div class="mt-3 space-y-3">
                        @foreach ($comments as $comment)
                            <article class="border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ $comment->author_name ?: ($comment->user?->name ?? __('ui.product.comments_anonymous')) }}</p>
                                    @if ((int) ($comment->rating ?? 0) > 0)
                                        <p class="text-xs font-semibold text-slate-600">{{ str_repeat('★', (int) $comment->rating) }}</p>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $comment->body }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-500">{{ __('ui.product.comments_empty') }}</p>
                @endif
            </div>
        </aside>
    </section>

    @if (!empty($sizeGuide) && $optionRows->isNotEmpty())
        <style>
            [data-size-guide-modal] .content-richtext {
                font-size: 0.9rem;
                line-height: 1.45;
            }
            [data-size-guide-modal] .content-richtext h1,
            [data-size-guide-modal] .content-richtext h2,
            [data-size-guide-modal] .content-richtext h3,
            [data-size-guide-modal] .content-richtext h4 {
                margin: 0.6rem 0 0.45rem;
                line-height: 1.25;
            }
            [data-size-guide-modal] .content-richtext h2 { font-size: 1.45rem; }
            [data-size-guide-modal] .content-richtext h3 { font-size: 1.15rem; }
            [data-size-guide-modal] .content-richtext p,
            [data-size-guide-modal] .content-richtext li {
                margin-bottom: 0.4rem;
            }
            [data-size-guide-modal] .content-richtext table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.82rem;
            }
            [data-size-guide-modal] .content-richtext th,
            [data-size-guide-modal] .content-richtext td {
                border: 1px solid #e2e8f0;
                padding: 0.35rem 0.45rem;
                vertical-align: middle;
            }
            [data-size-guide-modal] .content-richtext thead th {
                background: #f8fafc;
                font-weight: 700;
            }
        </style>
        <div class="fixed inset-0 z-[80] hidden flex items-center justify-center overflow-y-auto bg-black/50 p-4" data-size-guide-modal aria-hidden="true">
            <div class="mx-auto max-h-[86vh] w-full max-w-5xl overflow-hidden bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-extrabold text-slate-900">{{ $sizeGuide['title'] ?: __('ui.product.size_guide') }}</h2>
                    <button type="button" class="inline-flex h-9 min-w-9 items-center justify-center border border-slate-300 bg-white px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-100" data-size-guide-close>
                        {{ __('ui.product.size_guide_close') }}
                    </button>
                </div>
                <div class="max-h-[calc(86vh-64px)] overflow-y-auto px-5 py-4">
                    <div class="content-richtext">{!! $sizeGuide['body_html'] !!}</div>
                </div>
            </div>
        </div>
    @endif

    @if ($related->isNotEmpty())
        <section class="mt-10 px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('ui.product.related') }}</h2>
            <style>
                #related-products-carousel-{{ $product->id }} .splide__arrow {
                    opacity: 0;
                    width: 46px;
                    height: 46px;
                    border-radius: 9999px;
                    border: 1px solid rgba(255, 255, 255, 0.75);
                    background: rgba(15, 23, 42, 0.35);
                    backdrop-filter: blur(6px);
                    transform: translateY(-50%) scale(0.92);
                    transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
                }

                #related-products-carousel-{{ $product->id }}:hover .splide__arrow,
                #related-products-carousel-{{ $product->id }}:focus-within .splide__arrow {
                    opacity: 1;
                    transform: translateY(-50%) scale(1);
                }

                #related-products-carousel-{{ $product->id }} .splide__arrow:hover {
                    background: rgba(15, 23, 42, 0.55);
                }

                #related-products-carousel-{{ $product->id }} .splide__arrow svg {
                    fill: #fff;
                }

                @media (hover: none) {
                    #related-products-carousel-{{ $product->id }} .splide__arrow {
                        opacity: 1;
                        transform: translateY(-50%) scale(1);
                    }
                }
            </style>
            <div class="mt-4">
                <div id="related-products-carousel-{{ $product->id }}" class="splide" data-related-products-splide>
                    <div class="splide__track">
                        <ul class="splide__list">
                            @foreach ($related as $relatedProduct)
                                <li class="splide__slide">
                                    @include('front.desktop.partials.product-card', ['product' => $relatedProduct, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale, 'flat' => true])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (($recentlyViewed ?? collect())->isNotEmpty())
        @php
            $recentlyViewedCount = ($recentlyViewed ?? collect())->count();
        @endphp
        <section class="mt-10 px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('ui.product.recently_viewed') }}</h2>
            @if ($recentlyViewedCount <= 1)
                <div class="mt-4 grid gap-x-4 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    @foreach ($recentlyViewed as $recentlyViewedProduct)
                        @include('front.desktop.partials.product-card', ['product' => $recentlyViewedProduct, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale, 'flat' => true])
                    @endforeach
                </div>
            @else
                <style>
                    #recently-viewed-products-carousel-{{ $product->id }} .splide__arrow {
                        opacity: 0;
                        width: 46px;
                        height: 46px;
                        border-radius: 9999px;
                        border: 1px solid rgba(255, 255, 255, 0.75);
                        background: rgba(15, 23, 42, 0.35);
                        backdrop-filter: blur(6px);
                        transform: translateY(-50%) scale(0.92);
                        transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
                    }

                    #recently-viewed-products-carousel-{{ $product->id }}:hover .splide__arrow,
                    #recently-viewed-products-carousel-{{ $product->id }}:focus-within .splide__arrow {
                        opacity: 1;
                        transform: translateY(-50%) scale(1);
                    }

                    #recently-viewed-products-carousel-{{ $product->id }} .splide__arrow:hover {
                        background: rgba(15, 23, 42, 0.55);
                    }

                    #recently-viewed-products-carousel-{{ $product->id }} .splide__arrow svg {
                        fill: #fff;
                    }

                    @media (hover: none) {
                        #recently-viewed-products-carousel-{{ $product->id }} .splide__arrow {
                            opacity: 1;
                            transform: translateY(-50%) scale(1);
                        }
                    }
                </style>
                <div class="mt-4">
                    <div id="recently-viewed-products-carousel-{{ $product->id }}" class="splide" data-related-products-splide data-fixed-grid-cols="1">
                        <div class="splide__track">
                            <ul class="splide__list">
                                @foreach ($recentlyViewed as $recentlyViewedProduct)
                                    <li class="splide__slide">
                                        @include('front.desktop.partials.product-card', ['product' => $recentlyViewedProduct, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale, 'flat' => true])
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10">
            @include('components.content-placement', ['items' => $bottomBlocks])
        </section>
    @endif
@endsection

@push('scripts')
    @include('front.partials.splide-assets')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery-bundle.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
    <script defer src="{{ asset('front-theme/scripts/product-detail.js') }}?v={{ md5_file(public_path('front-theme/scripts/product-detail.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fitModal = document.querySelector('[data-fit-finder-modal]');
            const openButtons = document.querySelectorAll('[data-fit-finder-open]');
            const form = document.querySelector('[data-product-detail-form]');
            if (!fitModal || !openButtons.length || !form) {
                return;
            }

            const sizeInputs = Array.from(form.querySelectorAll('input[name="product_option_value_id"][data-size-label]'));
            const linkedPrimarySelect = form.querySelector('[data-linked-option-primary]');
            const linkedSecondarySelect = form.querySelector('[data-linked-option-secondary]');
            const hasLinkedSelectors = !!(linkedPrimarySelect && linkedSecondarySelect);

            const closeButtons = fitModal.querySelectorAll('[data-fit-finder-close]');
            const helpToggleButton = fitModal.querySelector('[data-fit-finder-help-toggle]');
            const helpPanel = fitModal.querySelector('[data-fit-finder-help-panel]');
            const helpIconClosed = fitModal.querySelector('[data-fit-help-icon-closed]');
            const helpIconOpen = fitModal.querySelector('[data-fit-help-icon-open]');
            const steps = Array.from(fitModal.querySelectorAll('[data-fit-step]'));
            const timelineItems = Array.from(fitModal.querySelectorAll('[data-fit-timeline-item]'));
            const progress = fitModal.querySelector('[data-fit-finder-progress]');
            const nextButton = fitModal.querySelector('[data-fit-next]');
            const prevButton = fitModal.querySelector('[data-fit-prev]');
            const applyButton = fitModal.querySelector('[data-fit-apply]');

            const inputHeight = fitModal.querySelector('[data-fit-height]');
            const inputWeight = fitModal.querySelector('[data-fit-weight]');
            const inputAge = fitModal.querySelector('[data-fit-age]');
            const inputHeightValue = fitModal.querySelector('[data-fit-height-value]');
            const inputWeightValue = fitModal.querySelector('[data-fit-weight-value]');
            const inputAgeValue = fitModal.querySelector('[data-fit-age-value]');

            const resultSize = [
                fitModal.querySelector('[data-fit-finder-result-size="0"]'),
                fitModal.querySelector('[data-fit-finder-result-size="1"]'),
            ];
            const resultPercent = [
                fitModal.querySelector('[data-fit-finder-result-percent="0"]'),
                fitModal.querySelector('[data-fit-finder-result-percent="1"]'),
            ];
            const resultBar = [
                fitModal.querySelector('[data-fit-finder-result-bar="0"]'),
                fitModal.querySelector('[data-fit-finder-result-bar="1"]'),
            ];
            const resultRow = [
                fitModal.querySelector('[data-fit-finder-result-row="0"]'),
                fitModal.querySelector('[data-fit-finder-result-row="1"]'),
            ];
            const summary = fitModal.querySelector('[data-fit-finder-summary]');
            const textErrorHeight = String(fitModal.dataset.textErrorHeight || 'Invalid height');
            const textErrorWeight = String(fitModal.dataset.textErrorWeight || 'Invalid weight');
            const textErrorAge = String(fitModal.dataset.textErrorAge || 'Invalid age');
            const textStepTemplate = String(fitModal.dataset.textStepTemplate || 'Step __CURRENT__ of __TOTAL__');
            const textRecommendationReady = String(fitModal.dataset.textRecommendationReady || 'Recommendation ready');
            const textSummaryTemplate = String(fitModal.dataset.textSummaryTemplate || 'Recommended size is __SIZE__ with confidence __PERCENT__%.');
            const textCtaTemplate = String(fitModal.dataset.textCtaTemplate || 'Add size __SIZE__ to cart');
            const textTrigger = String(fitModal.dataset.textTrigger || 'Find size');
            const textSavedPrefix = String(fitModal.dataset.textSavedPrefix || 'Your size is');
            const fitSaveUrl = String(fitModal.dataset.fitSaveUrl || '');
            const fitProductId = Number(fitModal.dataset.fitProductId || 0);
            const initialSizeLabel = String(fitModal.dataset.fitInitialSize || '').trim().toUpperCase();
            const initialSizeSignature = String(fitModal.dataset.fitInitialSizeSignature || '').trim();
            const saveIndicator = fitModal.querySelector('[data-fit-save-indicator]');

            const state = {
                step: 0,
                fit: String(fitModal.dataset.fitInitialFit || 'average'),
                chest: String(fitModal.dataset.fitInitialChest || 'average'),
                belly: String(fitModal.dataset.fitInitialBelly || 'average'),
                savedSizeLabel: initialSizeLabel,
                recommendation: null,
            };

            const sizeRankMap = {
                XXS: 1,
                XS: 2,
                S: 3,
                M: 4,
                L: 5,
                XL: 6,
                XXL: 7,
                XXXL: 8,
                '4XL': 9,
                '5XL': 10,
            };

            const sizeOptions = (function () {
                if (sizeInputs.length >= 2) {
                    return sizeInputs.map(function (input, index) {
                        const label = String(input.dataset.sizeLabel || '').trim();
                        const key = label.toUpperCase();
                        const rank = Object.prototype.hasOwnProperty.call(sizeRankMap, key) ? sizeRankMap[key] : (index + 3);
                        return { input, label, rank };
                    });
                }

                if (hasLinkedSelectors) {
                    return Array.from(linkedPrimarySelect.options)
                        .filter(function (option) {
                            return String(option.value || '').trim() !== '';
                        })
                        .map(function (option, index) {
                            const label = String(option.textContent || '').trim();
                            const key = label.toUpperCase();
                            const rank = Object.prototype.hasOwnProperty.call(sizeRankMap, key) ? sizeRankMap[key] : (index + 3);
                            return {
                                input: {
                                    __linked: true,
                                    parentId: String(option.value || '').trim(),
                                },
                                label,
                                rank,
                            };
                        });
                }

                return [];
            })();
            if (sizeOptions.length < 2) {
                return;
            }
            const currentSizeSignature = sizeOptions
                .map(function (option) {
                    return String(option.label || '').trim().toUpperCase();
                })
                .filter(function (label) {
                    return label !== '';
                })
                .sort()
                .join('|');
            state.savedSizeSignature = currentSizeSignature;

            const clamp = function (value, min, max) {
                return Math.max(min, Math.min(max, value));
            };

            const updateFitFinderOpenButtons = function (sizeLabel) {
                const cleaned = String(sizeLabel || '').trim().toUpperCase();
                openButtons.forEach(function (button) {
                    if (cleaned === '') {
                        button.textContent = textTrigger;
                        return;
                    }

                    button.textContent = textSavedPrefix + ' ' + cleaned;
                });
            };

            const syncRangeValue = function (input, output) {
                if (!input || !output) {
                    return;
                }
                output.textContent = String(input.value || '');
            };

            let persistTimer = null;
            let saveIndicatorTimer = null;

            const setSaveIndicator = function (status) {
                if (!saveIndicator) {
                    return;
                }

                if (saveIndicatorTimer) {
                    window.clearTimeout(saveIndicatorTimer);
                    saveIndicatorTimer = null;
                }

                if (status === 'saving') {
                    saveIndicator.textContent = 'Spremanje...';
                    saveIndicator.classList.remove('text-emerald-700', 'text-rose-600', 'opacity-0');
                    saveIndicator.classList.add('text-slate-500');
                    return;
                }

                if (status === 'saved') {
                    saveIndicator.textContent = 'Spremljeno';
                    saveIndicator.classList.remove('text-slate-500', 'text-rose-600', 'opacity-0');
                    saveIndicator.classList.add('text-emerald-700');
                    saveIndicatorTimer = window.setTimeout(function () {
                        saveIndicator.classList.add('opacity-0');
                    }, 1200);
                    return;
                }

                if (status === 'error') {
                    saveIndicator.textContent = 'Greška spremanja';
                    saveIndicator.classList.remove('text-slate-500', 'text-emerald-700', 'opacity-0');
                    saveIndicator.classList.add('text-rose-600');
                    saveIndicatorTimer = window.setTimeout(function () {
                        saveIndicator.classList.add('opacity-0');
                    }, 2200);
                    return;
                }

                saveIndicator.classList.add('opacity-0');
            };

            const persistFitPreference = function (sizeLabel) {
                if (!fitSaveUrl || !fitProductId) {
                    return;
                }

                const tokenInput = form.querySelector('input[name="_token"]');
                const token = tokenInput ? String(tokenInput.value) : '';
                if (!token) {
                    return;
                }
                setSaveIndicator('saving');

                const payload = new URLSearchParams();
                const normalizedCandidate = String(sizeLabel || state.savedSizeLabel || '').trim().toUpperCase();
                const normalizedSizeLabel = state.savedSizeSignature === currentSizeSignature ? normalizedCandidate : '';
                payload.set('_token', token);
                payload.set('product_id', String(fitProductId));
                payload.set('size_label', normalizedSizeLabel);
                payload.set('size_signature', currentSizeSignature);
                payload.set('height', String(inputHeight.value || ''));
                payload.set('weight', String(inputWeight.value || ''));
                payload.set('age', String(inputAge.value || ''));
                payload.set('fit', String(state.fit || 'average'));
                payload.set('chest', String(state.chest || 'average'));
                payload.set('belly', String(state.belly || 'average'));

                if (typeof navigator.sendBeacon === 'function') {
                    const blob = new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
                    navigator.sendBeacon(fitSaveUrl, blob);
                    setSaveIndicator('saved');
                    return;
                }

                fetch(fitSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json',
                    },
                    body: payload.toString(),
                    credentials: 'same-origin',
                    keepalive: true,
                })
                    .then(function (response) {
                        if (response && response.ok) {
                            setSaveIndicator('saved');
                            return;
                        }
                        setSaveIndicator('error');
                    })
                    .catch(function () {
                        setSaveIndicator('error');
                    });
            };

            const schedulePersist = function () {
                if (persistTimer) {
                    window.clearTimeout(persistTimer);
                }

                persistTimer = window.setTimeout(function () {
                    persistTimer = null;
                    persistFitPreference(state.savedSizeLabel);
                }, 300);
            };

            const clearStepErrors = function () {
                const errors = fitModal.querySelectorAll('[data-fit-error]');
                errors.forEach(function (error) {
                    error.textContent = '';
                    error.classList.add('hidden');
                });
            };

            const setStepError = function (message) {
                const error = steps[state.step] ? steps[state.step].querySelector('[data-fit-error]') : null;
                if (!error) {
                    return;
                }
                error.textContent = message;
                error.classList.remove('hidden');
            };

            const setPressed = function (attribute, value) {
                const buttons = fitModal.querySelectorAll('[' + attribute + ']');
                buttons.forEach(function (button) {
                    const isActive = button.getAttribute(attribute) === value;
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            const validateCurrentStep = function () {
                clearStepErrors();

                if (state.step === 0) {
                    const height = Number(inputHeight.value);
                    const weight = Number(inputWeight.value);
                    if (!Number.isFinite(height) || height < 130 || height > 230) {
                        setStepError(textErrorHeight);
                        return false;
                    }
                    if (!Number.isFinite(weight) || weight < 35 || weight > 220) {
                        setStepError(textErrorWeight);
                        return false;
                    }
                }

                if (state.step === 1) {
                    const age = Number(inputAge.value);
                    if (!Number.isFinite(age) || age < 12 || age > 100) {
                        setStepError(textErrorAge);
                        return false;
                    }
                }

                return true;
            };

            const calculateRecommendation = function () {
                const height = Number(inputHeight.value);
                const weight = Number(inputWeight.value);
                const age = Number(inputAge.value);
                const heightMeters = height / 100;
                const bmi = weight / (heightMeters * heightMeters);

                let target = 4.2;
                if (bmi < 20) target -= 1;
                else if (bmi < 23) target -= 0.4;
                else if (bmi < 26) target += 0.2;
                else if (bmi < 29) target += 0.8;
                else target += 1.3;

                if (height > 188) target += 0.2;
                if (height < 170) target -= 0.2;
                if (age > 45) target += 0.2;
                if (age > 60) target += 0.25;

                if (state.fit === 'tighter') target -= 0.5;
                if (state.fit === 'looser') target += 0.5;

                if (state.chest === 'slimmer') target -= 0.2;
                if (state.chest === 'broader') target += 0.35;

                if (state.belly === 'flatter') target -= 0.15;
                if (state.belly === 'rounder') target += 0.35;

                const scored = sizeOptions
                    .map(function (option) {
                        const distance = Math.abs(option.rank - target);
                        const score = Math.max(25, 100 - (distance * 22));
                        return { option, score };
                    })
                    .sort(function (a, b) {
                        return b.score - a.score;
                    });

                const first = scored[0];
                const second = scored[1] || scored[0];
                const firstPercent = clamp(Math.round(68 + (first.score - second.score) * 0.9), 55, 93);
                let secondPercent = clamp(Math.round((100 - firstPercent) + 22), 35, 88);
                if (secondPercent >= firstPercent) {
                    secondPercent = Math.max(30, firstPercent - 8);
                }

                state.recommendation = {
                    primary: { label: first.option.label, input: first.option.input, percent: firstPercent },
                    secondary: { label: second.option.label, input: second.option.input, percent: secondPercent },
                };
            };

            const applyRecommendedInput = function (inputRef) {
                if (!inputRef) {
                    return false;
                }

                if (inputRef.__linked) {
                    if (!hasLinkedSelectors) {
                        return false;
                    }

                    const parentId = String(inputRef.parentId || '').trim();
                    if (parentId === '') {
                        return false;
                    }

                    linkedPrimarySelect.value = parentId;
                    linkedPrimarySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }

                if (inputRef.checked) {
                    return true;
                }

                inputRef.checked = true;
                inputRef.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            };

            const renderResults = function () {
                calculateRecommendation();
                if (!state.recommendation) {
                    return;
                }

                resultSize[0].textContent = state.recommendation.primary.label;
                resultPercent[0].textContent = state.recommendation.primary.percent + '%';
                resultBar[0].style.width = state.recommendation.primary.percent + '%';
                resultRow[0].classList.add('is-selected');

                resultSize[1].textContent = state.recommendation.secondary.label;
                resultPercent[1].textContent = state.recommendation.secondary.percent + '%';
                resultBar[1].style.width = state.recommendation.secondary.percent + '%';
                resultRow[1].classList.remove('is-selected');

                summary.textContent = textSummaryTemplate
                    .replace('__SIZE__', state.recommendation.primary.label)
                    .replace('__PERCENT__', String(state.recommendation.primary.percent));
                applyButton.textContent = textCtaTemplate.replace('__SIZE__', state.recommendation.primary.label);

                if (state.recommendation.primary.input) {
                    applyRecommendedInput(state.recommendation.primary.input);
                    const recommendedLabel = String(state.recommendation.primary.label || '').trim();
                    if (recommendedLabel !== '') {
                        state.savedSizeSignature = currentSizeSignature;
                        state.savedSizeLabel = recommendedLabel.toUpperCase();
                        updateFitFinderOpenButtons(recommendedLabel);
                        persistFitPreference(recommendedLabel);
                    }
                }
            };

            const renderStep = function () {
                steps.forEach(function (step, index) {
                    step.classList.toggle('hidden', index !== state.step);
                });

                timelineItems.forEach(function (item, index) {
                    item.classList.toggle('is-current', index === state.step);
                    item.classList.toggle('is-done', index < state.step);
                });

                const totalInputSteps = 5;
                progress.textContent = state.step < totalInputSteps
                    ? textStepTemplate
                        .replace('__CURRENT__', String(state.step + 1))
                        .replace('__TOTAL__', String(totalInputSteps))
                    : textRecommendationReady;

                prevButton.classList.toggle('invisible', state.step === 0);
                nextButton.classList.toggle('hidden', state.step >= steps.length - 1);
                applyButton.classList.toggle('hidden', state.step < steps.length - 1);

                if (state.step === steps.length - 1) {
                    renderResults();
                }
            };

            const setHelpToggleState = function (isOpen) {
                if (helpToggleButton) {
                    helpToggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
                if (helpIconClosed) {
                    helpIconClosed.classList.toggle('hidden', isOpen);
                }
                if (helpIconOpen) {
                    helpIconOpen.classList.toggle('hidden', !isOpen);
                }
            };

            const openModal = function () {
                state.step = 0;
                clearStepErrors();
                renderStep();
                if (helpPanel) {
                    helpPanel.classList.add('hidden');
                }
                setHelpToggleState(false);
                fitModal.classList.remove('hidden');
                fitModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = function () {
                schedulePersist();
                if (helpPanel) {
                    helpPanel.classList.add('hidden');
                }
                setHelpToggleState(false);
                fitModal.classList.add('hidden');
                fitModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            if (helpToggleButton && helpPanel) {
                helpToggleButton.addEventListener('click', function () {
                    const shouldShow = helpPanel.classList.contains('hidden');
                    helpPanel.classList.toggle('hidden', !shouldShow);
                    setHelpToggleState(shouldShow);
                });
            }

            openButtons.forEach(function (button) {
                button.addEventListener('click', openModal);
            });

            closeButtons.forEach(function (button) {
                button.addEventListener('click', closeModal);
            });

            fitModal.addEventListener('click', function (event) {
                if (event.target === fitModal) {
                    closeModal();
                }
            });

            nextButton.addEventListener('click', function () {
                if (!validateCurrentStep()) {
                    return;
                }
                if (state.step < steps.length - 1) {
                    state.step += 1;
                    renderStep();
                    schedulePersist();
                }
            });

            prevButton.addEventListener('click', function () {
                if (state.step > 0) {
                    clearStepErrors();
                    state.step -= 1;
                    renderStep();
                    schedulePersist();
                }
            });

            fitModal.querySelectorAll('[data-fit-fit]').forEach(function (button) {
                button.addEventListener('click', function () {
                    state.fit = button.dataset.fitFit || 'average';
                    setPressed('data-fit-fit', state.fit);
                    schedulePersist();
                });
            });

            fitModal.querySelectorAll('[data-fit-chest]').forEach(function (button) {
                button.addEventListener('click', function () {
                    state.chest = button.dataset.fitChest || 'average';
                    setPressed('data-fit-chest', state.chest);
                    schedulePersist();
                });
            });

            fitModal.querySelectorAll('[data-fit-belly]').forEach(function (button) {
                button.addEventListener('click', function () {
                    state.belly = button.dataset.fitBelly || 'average';
                    setPressed('data-fit-belly', state.belly);
                    schedulePersist();
                });
            });

            applyButton.addEventListener('click', function () {
                if (!state.recommendation || !state.recommendation.primary.input) {
                    return;
                }

                applyRecommendedInput(state.recommendation.primary.input);
                state.savedSizeSignature = currentSizeSignature;
                state.savedSizeLabel = String(state.recommendation.primary.label || '').trim().toUpperCase();
                persistFitPreference(state.recommendation.primary.label);
                closeModal();

                const selectedOptionSelect = form.querySelector('select[name="product_option_value_id"]');
                const hasSelectedForSubmit = selectedOptionSelect
                    ? (!selectedOptionSelect.disabled && String(selectedOptionSelect.value || '').trim() !== '')
                    : !!form.querySelector('input[name="product_option_value_id"]:checked');
                if (hasSelectedForSubmit) {
                    const submitButton = form.querySelector('button[type="submit"]:not([form])');
                    if (submitButton) {
                        form.requestSubmit(submitButton);
                    }
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !fitModal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            setPressed('data-fit-fit', state.fit);
            setPressed('data-fit-chest', state.chest);
            setPressed('data-fit-belly', state.belly);

            if (!inputHeight.value) {
                inputHeight.value = '170';
            }
            if (!inputWeight.value) {
                inputWeight.value = '70';
            }
            if (!inputAge.value) {
                inputAge.value = '30';
            }

            if (fitModal.dataset.fitInitialHeight) {
                inputHeight.value = String(fitModal.dataset.fitInitialHeight);
            }
            if (fitModal.dataset.fitInitialWeight) {
                inputWeight.value = String(fitModal.dataset.fitInitialWeight);
            }
            if (fitModal.dataset.fitInitialAge) {
                inputAge.value = String(fitModal.dataset.fitInitialAge);
            }

            syncRangeValue(inputHeight, inputHeightValue);
            syncRangeValue(inputWeight, inputWeightValue);
            syncRangeValue(inputAge, inputAgeValue);

            inputHeight.addEventListener('input', function () {
                syncRangeValue(inputHeight, inputHeightValue);
                schedulePersist();
            });
            inputWeight.addEventListener('input', function () {
                syncRangeValue(inputWeight, inputWeightValue);
                schedulePersist();
            });
            inputAge.addEventListener('input', function () {
                syncRangeValue(inputAge, inputAgeValue);
                schedulePersist();
            });

            if (initialSizeLabel !== '') {
                const signatureMatches = initialSizeSignature !== '' && initialSizeSignature === currentSizeSignature;
                const savedSizeInput = signatureMatches ? sizeOptions.find(function (sizeOption) {
                    return String(sizeOption.label || '').trim().toUpperCase() === initialSizeLabel;
                }) : null;

                if (savedSizeInput) {
                    applyRecommendedInput(savedSizeInput.input);
                    state.savedSizeSignature = currentSizeSignature;
                    state.savedSizeLabel = initialSizeLabel;
                    updateFitFinderOpenButtons(initialSizeLabel);
                } else {
                    state.savedSizeLabel = '';
                    updateFitFinderOpenButtons('');
                }
            }

        });
    </script>
    @if (!empty($sizeGuide) && $optionRows->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.querySelector('[data-size-guide-modal]');
                if (!modal) {
                    return;
                }

                const openButtons = document.querySelectorAll('[data-size-guide-open]');
                const closeButtons = modal.querySelectorAll('[data-size-guide-close]');

                const openModal = function () {
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                };

                const closeModal = function () {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                };

                openButtons.forEach(function (button) {
                    button.addEventListener('click', openModal);
                });

                closeButtons.forEach(function (button) {
                    button.addEventListener('click', closeModal);
                });

                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            });
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const commentsAnchor = document.getElementById('product-comments');
            if (commentsAnchor) {
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
                const scrollToComments = function () {
                    commentsAnchor.scrollIntoView({
                        behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
                        block: 'start',
                    });
                };

                if (window.location.hash === '#product-comments') {
                    window.setTimeout(scrollToComments, 60);
                }

                window.addEventListener('hashchange', function () {
                    if (window.location.hash === '#product-comments') {
                        scrollToComments();
                    }
                });
            }

            const toggle = document.querySelector('[data-comment-form-toggle]');
            const panel = document.querySelector('[data-comment-form-panel]');
            if (!toggle || !panel) return;

            toggle.addEventListener('click', function () {
                panel.classList.toggle('hidden');
                const isOpen = !panel.classList.contains('hidden');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            const initRelatedProductsCarousel = function () {
                if (typeof window.Splide !== 'function') {
                    return false;
                }

                const sliders = document.querySelectorAll('[data-related-products-splide]');
                sliders.forEach(function (el) {
                    if (el.dataset.splideReady === '1') {
                        return;
                    }
                    el.dataset.splideReady = '1';

                    const count = el.querySelectorAll('.splide__slide').length;
                    const mobilePerPage = {{ in_array((int) ($storeSettings['product']['mobile_default_cols'] ?? 2), [1, 2], true) ? (int) ($storeSettings['product']['mobile_default_cols'] ?? 2) : 2 }};
                    const preferredDesktopPerPage = {{ $preferredGridCols }};
                    const fixedGridCols = el.dataset.fixedGridCols === '1';
                    const desktopPerPage = fixedGridCols
                        ? Math.max(1, preferredDesktopPerPage)
                        : Math.min(Math.max(1, preferredDesktopPerPage), Math.max(1, count));
                    new window.Splide(el, {
                        type: count > desktopPerPage ? 'loop' : 'slide',
                        perPage: desktopPerPage,
                        perMove: 1,
                        gap: '1.25rem',
                        drag: count > 1,
                        snap: true,
                        pagination: false,
                        arrows: count > 1,
                        updateOnMove: true,
                        speed: 520,
                        breakpoints: {
                            1536: { perPage: fixedGridCols ? Math.min(Math.max(1, preferredDesktopPerPage), 5) : Math.min(Math.min(Math.max(1, preferredDesktopPerPage), 5), Math.max(1, count)) },
                            1280: { perPage: fixedGridCols ? Math.min(Math.max(1, preferredDesktopPerPage), 4) : Math.min(Math.min(Math.max(1, preferredDesktopPerPage), 4), Math.max(1, count)) },
                            1024: { perPage: fixedGridCols ? Math.min(Math.max(1, preferredDesktopPerPage), 3) : Math.min(Math.min(Math.max(1, preferredDesktopPerPage), 3), Math.max(1, count)) },
                            860: { perPage: Math.min(mobilePerPage, Math.max(1, count)), gap: '1rem' },
                            640: { perPage: Math.min(mobilePerPage, Math.max(1, count)), gap: '0.8rem' },
                        },
                    }).mount();
                });

                return true;
            };

            if (initRelatedProductsCarousel()) {
                return;
            }

            let attempts = 0;
            const timer = window.setInterval(function () {
                attempts += 1;
                if (initRelatedProductsCarousel() || attempts > 40) {
                    window.clearInterval(timer);
                }
            }, 120);
        });
    </script>
@endpush
