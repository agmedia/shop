@extends('front.desktop.layouts.store')

@php
    $translation = $product->translations->firstWhere('locale', $locale)
        ?? $product->translations->firstWhere('locale', $fallbackLocale);
    $manufacturerTranslation = $product->manufacturer?->translations?->firstWhere('locale', $locale)
        ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale);
    $manufacturerEnabled = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
    $currentPrice = number_format((float) ($pricePresentation['current_gross'] ?? 0), 2).' €';
    $oldPrice = isset($pricePresentation['old_gross']) && $pricePresentation['old_gross'] !== null
        ? number_format((float) $pricePresentation['old_gross'], 2).' €'
        : null;
    $discountPercent = (int) ($pricePresentation['discount_percent'] ?? 0);
    $lowest30DaysPrice = isset($pricePresentation['lowest_30_days_gross']) && $pricePresentation['lowest_30_days_gross'] !== null
        ? number_format((float) $pricePresentation['lowest_30_days_gross'], 2).' €'
        : null;
    $isWishlisted = app(\App\Services\Front\WishlistService::class)->has((int) $product->id);

    $mediaItems = $product->relationLoaded('media')
        ? $product->media
            ->whereIn('collection_name', ['product_main', 'product_gallery'])
            ->sortBy(static fn ($mediaItem) => (int) ($mediaItem->order_column ?? 0))
            ->values()
        : collect();
    $mainMedia = $mediaItems->firstWhere('collection_name', 'product_main')
        ?? $mediaItems->firstWhere('collection_name', 'product_gallery')
        ?? $product->getFirstMedia('product_main')
        ?? $product->getFirstMedia('product_gallery');

    $galleryItems = collect();
    if ($mainMedia) {
        $galleryItems->push($mainMedia);
    }
    foreach ($mediaItems as $mediaItem) {
        if ($mainMedia && (int) $mediaItem->id === (int) $mainMedia->id) {
            continue;
        }

        $galleryItems->push($mediaItem);
    }

    $gallery = $galleryItems
        ->unique(fn ($mediaItem) => (int) $mediaItem->id)
        ->map(function ($mediaItem) use ($translation, $product) {
            $displayUrl = $mediaItem->hasGeneratedConversion('detail_960x960')
                ? $mediaItem->getUrl('detail_960x960')
                : $mediaItem->getUrl();

            return [
                'id' => (int) $mediaItem->id,
                'full' => (string) $mediaItem->getUrl(),
                'display' => (string) $displayUrl,
                'thumb' => (string) ($mediaItem->hasGeneratedConversion('thumb_100x100') ? $mediaItem->getUrl('thumb_100x100') : $mediaItem->getUrl()),
                'alt' => (string) ($translation?->name ?? $product->code),
            ];
        })
        ->values();

    $optionRows = $product->optionValues->where('is_active', true)->values();

    $firstCategory = $product->categories->first();
    $firstCategoryTranslation = $firstCategory?->translations?->firstWhere('locale', $locale)
        ?? $firstCategory?->translations?->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->name ?? 'Product')
@section('main_class', 'w-full px-0 py-8')

@section('content')
    <style>
        .product-detail-layout {
            display: grid;
            gap: 2rem;
        }

        .product-ipad-slider {
            display: block;
            margin-top: -2rem;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .product-default-grid {
            display: none;
        }

        @media (max-width: 768px) {
            .product-ipad-slider .splide__track {
                overflow: hidden;
            }

            .product-ipad-slider .splide__slide > button {
                display: block;
                width: 100%;
                border: 0;
                padding: 0;
                background: #f8fafc;
            }

            .product-ipad-slider .splide__slide img {
                display: block;
                width: 100%;
                max-width: 100%;
                height: auto;
            }
        }

        @media (min-width: 769px) {
            .product-detail-layout {
                grid-template-columns: minmax(0, 1.2fr) minmax(360px, 1fr);
            }

            .product-ipad-slider {
                display: none;
                margin-top: 0;
            }

            .product-default-grid {
                display: block;
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
                    $displayGallery = $gallery->take(5)->values();
                    $topGallery = $displayGallery->take(2)->values();
                    $bottomGallery = $displayGallery->slice(2, 3)->values();
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
                                class="overflow-hidden border border-slate-200 bg-slate-50"
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
                                        @if ($index === 0) data-gallery-main @endif
                                        @if ($index === 1) data-gallery-secondary @endif
                                    >
                            </button>
                        @endforeach
                    </div>

                    @if ($bottomGallery->isNotEmpty())
                        <div class="mt-1 grid grid-cols-3 gap-1">
                            @foreach ($bottomGallery as $bottomIndex => $image)
                                @php $index = $bottomIndex + 2; @endphp
                                <button
                                    type="button"
                                    class="overflow-hidden border border-slate-200 bg-slate-50"
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
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </button>
                            @endforeach
                        </div>
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
                    <h1 class="text-2xl font-extrabold leading-tight text-slate-900">{{ $translation?->name ?? $product->code }}</h1>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.product.sku') }}: {{ $product->sku ?: 'n/a' }}</p>
                    @if ($manufacturerTranslation && $manufacturerEnabled)
                        <p class="mt-1 text-xs text-slate-600">
                            <a href="{{ route('manufacturers.show', ['slug' => $manufacturerTranslation->slug]) }}" class="font-semibold text-slate-700 hover:text-slate-900">{{ $manufacturerTranslation->name }}</a>
                        </p>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xl font-semibold text-slate-900">{{ $currentPrice }}</p>
                        @if ($discountPercent > 0)
                            <span class="inline-flex h-7 items-center border border-rose-600 bg-rose-600 px-2 text-xs font-bold text-white">-{{ $discountPercent }}%</span>
                        @endif
                    </div>
                    @if ($oldPrice)
                        <p class="mt-1 text-sm text-slate-500 line-through">{{ $oldPrice }}</p>
                    @endif
                    @if ($lowest30DaysPrice)
                        <p class="mt-1 text-xs text-slate-600">{{ __('ui.product.lowest_price_30_days', ['price' => $lowest30DaysPrice]) }}</p>
                    @endif
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('cart.items.store') }}"
                class="mt-6 space-y-4"
                data-product-detail-form
                data-ga4-add-to-cart-form
                data-ga4-item-id="{{ (string) ($product->sku ?: $product->id) }}"
                data-ga4-item-name="{{ $translation?->name ?? $product->code }}"
                data-ga4-item-price="{{ number_format((float) ($pricePresentation['current_gross'] ?? 0), 2, '.', '') }}"
                data-ga4-item-brand="{{ (string) ($manufacturerTranslation?->name ?? '') }}"
                data-ga4-item-category="{{ (string) ($firstCategoryTranslation?->name ?? '') }}"
                data-ga4-currency="EUR"
                data-product-name="{{ $translation?->name ?? $product->code }}"
                data-product-image="{{ (string) (($gallery->first()['full'] ?? '') ?: '') }}"
                data-cart-url="{{ route('cart.index') }}"
                data-modal-continue="{{ __('ui.cart.modal.continue') }}"
                data-modal-go-cart="{{ __('ui.cart.modal.go_to_cart') }}"
                data-modal-option="{{ __('ui.cart.modal.option') }}"
                data-modal-quantity="{{ __('ui.cart.modal.quantity') }}"
            >
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($optionRows->isNotEmpty())
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-900">{{ __('ui.product.select_size') }} <span class="text-rose-600">*</span></label>
                            <a href="#" class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline underline-offset-2 hover:text-slate-900">{{ __('ui.product.size_guide') }}</a>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($optionRows as $row)
                                @php
                                    $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                        ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                        ?? $row->optionValue?->translations?->first();
                                    $label = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                                    $inputId = 'product-detail-pov-'.$product->id.'-'.$row->id;
                                @endphp
                                <label for="{{ $inputId }}" class="inline-flex h-10 min-w-10 cursor-pointer items-center justify-center border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:bg-slate-100 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white">
                                    <input id="{{ $inputId }}" type="radio" name="product_option_value_id" value="{{ $row->id }}" class="sr-only">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="hidden mt-2 text-xs font-semibold text-rose-600" data-option-error>
                            {{ __('ui.cart.errors.select_size') }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2">
                    <div class="inline-flex h-10 items-stretch" data-qty-control>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-slate-300 text-xl font-semibold text-slate-700 hover:bg-slate-100" data-qty-dec aria-label="Decrease quantity">-</button>
                        <input type="text" name="quantity" value="1" inputmode="numeric" readonly class="h-10 w-10 border-y border-r border-slate-300 border-l-0 bg-white p-0 text-center text-base font-normal text-slate-900" data-qty-input>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-l-0 border-slate-300 text-xl font-semibold text-slate-700 hover:bg-slate-100" data-qty-inc aria-label="Increase quantity">+</button>
                    </div>

                    <button type="submit" class="inline-flex h-10 min-w-0 items-center justify-center gap-2 whitespace-nowrap border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-700" aria-label="{{ __('ui.product.add_to_cart') }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 9h10l-1 10H8L7 9Z"></path>
                            <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
                        </svg>
                        <span class="truncate">{{ __('ui.product.add_to_cart') }}</span>
                    </button>

                    <button
                        type="submit"
                        form="wishlist-product-{{ $product->id }}"
                        class="inline-flex h-10 w-10 items-center justify-center border transition {{ $isWishlisted ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-700' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-900 hover:text-slate-900' }}"
                        aria-label="{{ $isWishlisted ? __('ui.wishlist.remove') : __('ui.wishlist.add') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.8 8.6c0 5.9-8.8 10.9-8.8 10.9S3.2 14.5 3.2 8.6a4.8 4.8 0 0 1 8.8-2.7 4.8 4.8 0 0 1 8.8 2.7Z"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <form id="wishlist-product-{{ $product->id }}" method="POST" action="{{ route('wishlist.items.toggle', ['product' => $product->id]) }}" class="hidden">
                @csrf
            </form>

            <button type="button" class="mt-4 inline-flex h-10 w-full items-center justify-center gap-3 border border-slate-300 bg-white px-4 text-xs font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="1"></rect>
                    <path d="M7 8h3M7 12h10M7 16h10M14 8h3"></path>
                </svg>
                {{ __('ui.product.check_store') }}
            </button>

            @if (! empty($translation?->description))
                <div class="mt-6 text-slate-700">{!! $translation->description !!}</div>
            @elseif (! empty($translation?->excerpt))
                <p class="mt-6 text-slate-700">{{ $translation->excerpt }}</p>
            @endif

            @php
                $commentFormHasErrors = $errors->has('author_name') || $errors->has('author_email') || $errors->has('body') || $errors->has('rating');
                $commentUser = auth()->user();
            @endphp

            <div id="product-comments" class="mt-6 border-t border-slate-200 pt-4">
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

    @if ($related->isNotEmpty())
        <section class="mt-10 px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('ui.product.related') }}</h2>
            <div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($related as $product)
                    @include('front.desktop.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale, 'flat' => true])
                @endforeach
            </div>
        </section>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10">
            @include('components.content-placement', ['items' => $bottomBlocks])
        </section>
    @endif
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery-bundle.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
    <script defer src="{{ asset('front-theme/scripts/product-detail.js') }}?v={{ md5_file(public_path('front-theme/scripts/product-detail.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-comment-form-toggle]');
            const panel = document.querySelector('[data-comment-form-panel]');
            if (!toggle || !panel) return;

            toggle.addEventListener('click', function () {
                panel.classList.toggle('hidden');
                const isOpen = !panel.classList.contains('hidden');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    </script>
@endpush
