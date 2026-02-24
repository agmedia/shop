@extends('front.mobile.layouts.store')

@php
    $translation = $product->translations->firstWhere('locale', $locale)
        ?? $product->translations->firstWhere('locale', $fallbackLocale);
    $manufacturerTranslation = $product->manufacturer?->translations?->firstWhere('locale', $locale)
        ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale);
    $firstCategoryTranslation = $product->categories->first()?->translations?->firstWhere('locale', $locale)
        ?? $product->categories->first()?->translations?->firstWhere('locale', $fallbackLocale);
    $manufacturerEnabled = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
    $displayBasePrice = app(\App\Services\Pricing\TaxPricingService::class)->grossFromStored((float) $product->base_price, $product);

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
            return [
                'id' => (int) $mediaItem->id,
                'full' => (string) $mediaItem->getUrl(),
                'alt' => (string) ($translation?->name ?? $product->code),
            ];
        })
        ->values();

    $optionRows = $product->optionValues->where('is_active', true)->values();
@endphp

@section('title', $translation?->name ?? __('ui.product.sku'))
@section('header_title', $translation?->name ?? __('ui.shop.page_title'))
@section('page_title', $translation?->name ?? __('ui.shop.page_title'))

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style">
        @if ($gallery->isNotEmpty())
            <div class="content p-0" data-mobile-product-gallery>
                <div class="d-flex overflow-auto" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; overflow-y: hidden;" data-mobile-gallery-track>
                    @foreach ($gallery as $index => $image)
                        <button
                            type="button"
                            class="border-0 bg-transparent p-0 flex-shrink-0"
                            style="min-width: 100%; scroll-snap-align: start;"
                            data-gallery-open="{{ $index }}"
                            aria-label="{{ $image['alt'] }}"
                        >
                            <img
                                src="{{ $image['full'] }}"
                                alt="{{ $image['alt'] }}"
                                class="d-block w-100"
                                style="height: auto;"
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
            <h2 class="mb-0">{{ number_format($displayBasePrice, 2) }} €</h2>
            <p class="font-12 opacity-60 mb-2">{{ __('ui.product.sku') }} {{ $product->sku ?: 'n/a' }}</p>

            @if ($manufacturerTranslation && $manufacturerEnabled)
                <p class="font-12 mb-3">
                    <a href="{{ route('manufacturers.show', ['slug' => $manufacturerTranslation->slug]) }}" class="color-highlight">{{ $manufacturerTranslation->name }}</a>
                </p>
            @endif

            <form
                method="POST"
                action="{{ route('cart.items.store') }}"
                data-product-detail-form
                data-ga4-add-to-cart-form
                data-ga4-item-id="{{ (string) ($product->sku ?: $product->id) }}"
                data-ga4-item-name="{{ $translation?->name ?? $product->code }}"
                data-ga4-item-price="{{ number_format((float) $displayBasePrice, 2, '.', '') }}"
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
                    <div class="input-style has-borders no-icon input-style-always-active mb-3">
                        <label for="product-option" class="color-highlight">{{ __('ui.product.select_size') }}</label>
                        <select id="product-option" name="product_option_value_id">
                            <option value="">--</option>
                            @foreach ($optionRows as $row)
                                @php
                                    $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                                        ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                                        ?? $row->optionValue?->translations?->first();
                                    $label = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                                @endphp
                                <option value="{{ $row->id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                    </div>
                    <p class="font-11 color-red-dark mb-3 d-none" data-option-error>{{ __('ui.cart.errors.select_size') }}</p>
                @endif

                <div class="row mb-2">
                    <div class="col-5">
                        <div class="d-flex h-100" data-qty-control>
                            <button type="button" class="btn btn-border border-gray-dark color-gray-dark rounded-0" data-qty-dec>-</button>
                            <input type="text" name="quantity" value="1" readonly class="form-control text-center rounded-0" data-qty-input>
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
                </div>
            </form>

            @if (!empty($translation?->description))
                <div class="divider mt-4"></div>
                <div class="font-13">{!! $translation->description !!}</div>
            @elseif (!empty($translation?->excerpt))
                <div class="divider mt-4"></div>
                <p class="font-13">{{ $translation->excerpt }}</p>
            @endif

            @php
                $commentFormHasErrors = $errors->has('author_name') || $errors->has('author_email') || $errors->has('body') || $errors->has('rating');
                $commentUser = auth()->user();
            @endphp

            <div class="divider mt-4"></div>
            <div id="product-comments" class="d-flex justify-content-between align-items-center gap-2 mb-2">
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
@endpush
