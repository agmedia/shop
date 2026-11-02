@extends('front.mobile.layouts.store')

@php
    $translation = $product->translations->firstWhere('locale', $locale)
        ?? $product->translations->firstWhere('locale', $fallbackLocale);
    $manufacturerTranslation = $product->manufacturer?->translations?->firstWhere('locale', $locale)
        ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale);
    $firstCategoryTranslation = $product->categories->first()?->translations?->firstWhere('locale', $locale)
        ?? $product->categories->first()?->translations?->firstWhere('locale', $fallbackLocale);
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

            return [
                'id' => (int) $mediaItem->id,
                'full' => (string) ($displayUrl ?? $mediaItem->getUrl()),
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
    $hasProductStory = ! empty($translation?->description) || ! empty($translation?->excerpt);
    $colorVariants = $colorVariants ?? collect();
@endphp

@section('title', $translation?->name ?? __('ui.product.sku'))
@section('header_title', $translation?->name ?? __('ui.shop.page_title'))
@section('page_title', $translation?->name ?? __('ui.shop.page_title'))

@section('content')
    <style>
        html {
            scroll-behavior: smooth;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }
        }

        [data-mobile-gallery-frame] {
            aspect-ratio: 2 / 3;
            overflow: hidden;
            background: #f8fafc;
        }

        [data-mobile-gallery-frame] img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
        }

        .mobile-product-color-variant-link {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            background: #ffffff;
        }

        .mobile-product-color-variant-link[aria-current="true"] {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }

        .mobile-product-color-variant-swatch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid rgba(15, 23, 42, 0.16);
            background-color: #f8fafc;
        }
    </style>

    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style">
        @if ($gallery->isNotEmpty())
            <div class="content p-0" style="margin-top: -1px;" data-mobile-product-gallery>
                <div class="d-flex overflow-auto" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; overflow-y: hidden;" data-mobile-gallery-track>
                    @foreach ($gallery as $index => $image)
                        <button
                            type="button"
                            class="border-0 bg-transparent p-0 flex-shrink-0"
                            style="min-width: 100%; scroll-snap-align: start;"
                            data-mobile-gallery-frame
                            data-gallery-open="{{ $index }}"
                            aria-label="{{ $image['alt'] }}"
                        >
                            <img
                                src="{{ $image['full'] }}"
                                alt="{{ $image['alt'] }}"
                                class="d-block w-100"
                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                decoding="async"
                            >
                        </button>
                    @endforeach
                </div>
                <div class="d-flex justify-content-center gap-2 py-3">
                    @foreach ($gallery as $index => $image)
                        <button
                            type="button"
                            class="border-0 rounded-circle bg-white/70"
                            style="width: 10px; height: 10px;"
                            data-mobile-gallery-dot="{{ $index }}"
                            aria-label="{{ __('ui.product.slide_aria', ['index' => $index + 1]) }}"
                        ></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="card card-style">
        <div class="content">
            <h2 class="mb-0" data-product-price-current>{{ $productPriceData['current'] }}</h2>
            <p class="font-12 opacity-60 mb-2">{{ __('ui.product.sku') }} <span data-product-sku-value>{{ $product->sku ?: $product->code ?: 'n/a' }}</span></p>

            @if ($manufacturerTranslation && $manufacturerEnabled)
                <p class="font-12 mb-3">
                    <a href="{{ route('manufacturers.show', ['slug' => $manufacturerTranslation->slug]) }}" class="color-highlight">{{ $manufacturerTranslation->name }}</a>
                </p>
            @endif

            @if ($colorVariants->isNotEmpty())
                <div class="divider mt-2 mb-3"></div>
                <div class="mb-3" data-product-color-variants>
                    <p class="font-600 mb-2">{{ __('ui.product.color_variants') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($colorVariants as $variant)
                            @php
                                $swatchStyle = $variant['swatch_image_url']
                                    ? 'background-image:url('.$variant['swatch_image_url'].');background-size:cover;background-position:center;background-repeat:no-repeat;background-color:transparent;'
                                    : $variant['swatch_style'];
                            @endphp
                            <a
                                href="{{ $variant['url'] }}"
                                class="mobile-product-color-variant-link"
                                title="{{ $variant['label'] }}"
                                aria-label="{{ $variant['label'] }}"
                                @if ($variant['is_current']) aria-current="true" @endif
                                data-color-variant-link
                                data-color-variant-label="{{ $variant['label'] }}"
                            >
                                <span class="mobile-product-color-variant-swatch" style="{{ $swatchStyle }}" data-color-variant-swatch aria-hidden="true">{{ $swatchStyle === '' ? '?' : '' }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('cart.items.store') }}"
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
                    @if ($hasLinkedOptions)
                        <div class="input-style has-borders no-icon input-style-always-active mb-3">
                            <label class="color-highlight">{{ $primaryOptionLabel }}</label>
                            <select data-linked-option-primary>
                                <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                @foreach ($linkedPrimaryValues as $primaryValue)
                                    <option value="{{ $primaryValue['id'] }}">{{ $primaryValue['label'] }}</option>
                                @endforeach
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="input-style has-borders no-icon input-style-always-active mb-3">
                            <label class="color-highlight">{{ $secondaryOptionLabel }}</label>
                            <select name="product_option_value_id" data-linked-option-secondary disabled>
                                <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                @foreach ($optionRows as $row)
                                    @php
                                        $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                            ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                            ?? $row->optionValue?->translations?->first();
                                        $label = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
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
                                    >{{ $label }}</option>
                                @endforeach
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                        </div>
                    @else
                        <div class="input-style has-borders no-icon input-style-always-active mb-3">
                            <label for="product-option" class="color-highlight">{{ __('ui.product.select_size') }}</label>
                            <select id="product-option" name="product_option_value_id">
                                <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                @foreach ($optionRows as $row)
                                    @php
                                        $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                            ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                            ?? $row->optionValue?->translations?->first();
                                        $label = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                                        $rowPriceData = $optionPriceData($row);
                                    @endphp
                                    <option
                                        value="{{ $row->id }}"
                                        data-option-sku="{{ (string) ($row->sku ?: '') }}"
                                        data-option-price-current="{{ $rowPriceData['current'] }}"
                                        data-option-price-current-value="{{ $rowPriceData['current_value'] }}"
                                        data-option-price-old="{{ $rowPriceData['old'] }}"
                                        data-option-price-discount="{{ $rowPriceData['discount_percent'] }}"
                                        data-option-price-lowest="{{ $rowPriceData['lowest_30_days'] }}"
                                    >{{ $label }}</option>
                                @endforeach
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                        </div>
                    @endif
                    <p class="font-11 color-red-dark mb-3 d-none" data-option-error>{{ __('ui.cart.errors.select_size') }}</p>
                    @if (!empty($sizeGuide))
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn p-0 font-600 text-uppercase font-11" data-size-guide-open>
                                {{ __('ui.product.size_guide') }}
                            </button>
                        </div>
                    @endif
                @endif

                <div class="row mb-2">
                    @if ($isPurchasable)
                        <div class="col-5">
                            <div class="d-flex h-100" data-qty-control>
                                <button type="button" class="btn btn-border border-gray-dark color-gray-dark rounded-0" data-qty-dec>-</button>
                                <input type="text" name="quantity" value="1" readonly aria-label="{{ __('ui.cart.modal.quantity') }}" class="form-control text-center rounded-0" data-qty-input>
                                <button type="button" class="btn btn-border border-gray-dark color-gray-dark rounded-0" data-qty-inc>+</button>
                            </div>
                        </div>
                        <div class="col-7">
                            <button type="submit" class="btn btn-full bg-black color-white font-600 rounded-0 mt-1 d-inline-flex align-items-center justify-content-center gap-2">
                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true">
                                    <path d="M7 9h10l-1 10H8L7 9Z"></path>
                                    <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
                                </svg>
                                {{ __('ui.product.add_to_cart') }}
                            </button>
                        </div>
                    @else
                        <div class="col-12">
                            <button type="button" disabled class="btn btn-full border border-gray-300 bg-light color-gray-dark font-600 rounded-0 mt-1">
                                {{ __('ui.product.unavailable') }}
                            </button>
                        </div>
                    @endif
                </div>
            </form>

            @if (!empty($translation?->description))
                <div class="divider mt-4"></div>
                <div class="font-13">{!! $translation->description !!}</div>
            @elseif (!empty($translation?->excerpt))
                <div class="divider mt-4"></div>
                <p class="font-13">{{ $translation->excerpt }}</p>
            @endif

            @include('front.partials.product-attribute-panels', [
                'product' => $product,
                'locale' => $locale,
                'fallbackLocale' => $fallbackLocale,
                'containerClass' => $hasProductStory ? 'mt-4' : 'mt-2',
            ])

            @php
                $commentFormHasErrors = $errors->has('author_name') || $errors->has('author_email') || $errors->has('body') || $errors->has('rating');
                $commentUser = auth()->user();
            @endphp

            <div class="divider mt-4"></div>
            <div id="product-comments" class="d-flex justify-content-between align-items-center gap-2 mb-2" style="scroll-margin-top: 90px;">
                <h4 class="mb-0">{{ __('ui.product.comments_title') }}</h4>
                <button type="button" class="btn p-0 font-600 text-uppercase font-11" data-comment-form-toggle aria-expanded="{{ $commentFormHasErrors ? 'true' : 'false' }}">
                    {{ __('ui.product.comment_form.toggle') }}
                </button>
            </div>

            <div class="{{ $commentFormHasErrors ? '' : 'd-none' }} border border-slate-200 bg-slate-50 p-3 mb-3" data-comment-form-panel>
                <form method="POST" action="{{ route('products.comments.store', ['slug' => $translation?->slug ?? request()->route('slug')]) }}">
                    @csrf
                    <div class="input-style has-borders no-icon input-style-always-active mb-2">
                        <label class="color-highlight">{{ __('ui.product.comment_form.name') }}</label>
                        <input type="text" name="author_name" value="{{ old('author_name', $commentUser?->name ?? '') }}" @if($commentUser) readonly @endif>
                    </div>
                    @error('author_name') <p class="font-11 color-red-dark mb-2">{{ $message }}</p> @enderror

                    <div class="input-style has-borders no-icon input-style-always-active mb-2">
                        <label class="color-highlight">{{ __('ui.product.comment_form.email') }}</label>
                        <input type="email" name="author_email" value="{{ old('author_email', $commentUser?->email ?? '') }}" @if($commentUser) readonly @endif>
                    </div>
                    @error('author_email') <p class="font-11 color-red-dark mb-2">{{ $message }}</p> @enderror

                    <div class="input-style has-borders no-icon input-style-always-active mb-2">
                        <label class="color-highlight">{{ __('ui.product.comment_form.rating') }}</label>
                        <select name="rating">
                            <option value="">{{ __('ui.product.comment_form.rating_optional') }}</option>
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected((string) old('rating') === (string) $i)>{{ $i }} ★</option>
                            @endfor
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                    </div>
                    @error('rating') <p class="font-11 color-red-dark mb-2">{{ $message }}</p> @enderror

                    <div class="input-style has-borders no-icon input-style-always-active mb-2">
                        <label class="color-highlight">{{ __('ui.product.comment_form.body') }}</label>
                        <textarea name="body" rows="4" required>{{ old('body') }}</textarea>
                    </div>
                    @error('body') <p class="font-11 color-red-dark mb-2">{{ $message }}</p> @enderror

                    <button type="submit" class="btn btn-full bg-black color-white font-600 rounded-0 mt-2">{{ __('ui.product.comment_form.submit') }}</button>
                </form>
            </div>

            @if (($comments ?? collect())->isNotEmpty())
                <div class="d-flex flex-column gap-2">
                    @foreach ($comments as $comment)
                        <article class="border border-slate-200 bg-slate-50 p-3">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <p class="mb-0 font-600">{{ $comment->author_name ?: ($comment->user?->name ?? __('ui.product.comments_anonymous')) }}</p>
                                @if ((int) ($comment->rating ?? 0) > 0)
                                    <p class="mb-0 font-11 opacity-70">{{ str_repeat('★', (int) $comment->rating) }}</p>
                                @endif
                            </div>
                            <p class="mb-0 mt-2 font-13">{{ $comment->body }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="font-13 opacity-60 mb-0">{{ __('ui.product.comments_empty') }}</p>
            @endif
        </div>
    </div>

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
            [data-size-guide-modal] .content-richtext h2 { font-size: 1.3rem; }
            [data-size-guide-modal] .content-richtext h3 { font-size: 1.05rem; }
            [data-size-guide-modal] .content-richtext p,
            [data-size-guide-modal] .content-richtext li {
                margin-bottom: 0.4rem;
            }
            [data-size-guide-modal] .content-richtext table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.8rem;
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
        <div class="fixed inset-0 z-[120] hidden items-end justify-center bg-black/50" data-size-guide-modal aria-hidden="true">
            <div class="w-full max-h-[88vh] overflow-hidden bg-white shadow-2xl sm:mx-auto sm:max-w-4xl">
                <div class="d-flex justify-content-between align-items-center border-bottom px-4 py-3">
                    <h4 class="mb-0">{{ $sizeGuide['title'] ?: __('ui.product.size_guide') }}</h4>
                    <button type="button" class="btn btn-border border-slate-300 color-theme rounded-0 font-11 text-uppercase px-3 py-2" data-size-guide-close>
                        {{ __('ui.product.size_guide_close') }}
                    </button>
                </div>
                <div class="px-4 py-3 overflow-auto" style="max-height: calc(88vh - 64px);">
                    <div class="content-richtext">{!! $sizeGuide['body_html'] !!}</div>
                </div>
            </div>
        </div>
    @endif

    @if ($related->isNotEmpty())
        <div class="card card-style">
            <div class="content mb-1">
                <h4>{{ __('ui.product.related') }}</h4>
            </div>
        </div>
        @foreach ($related as $product)
            @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
        @endforeach
    @endif

    @if (($recentlyViewed ?? collect())->isNotEmpty())
        <div class="card card-style">
            <div class="content mb-1">
                <h4>{{ __('ui.product.recently_viewed') }}</h4>
            </div>
        </div>
        @foreach ($recentlyViewed as $product)
            @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
        @endforeach
    @endif

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery-bundle.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
    <script defer src="{{ asset('front-theme/scripts/product-detail.js') }}?v={{ md5_file(public_path('front-theme/scripts/product-detail.js')) }}"></script>
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
                panel.classList.toggle('d-none');
                const isOpen = !panel.classList.contains('d-none');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    </script>
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
                modal.classList.add('d-flex');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = function () {
                modal.classList.add('hidden');
                modal.classList.remove('d-flex');
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
                if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
                    closeModal();
                }
            });
        });
    </script>
@endpush
