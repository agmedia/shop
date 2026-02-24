@php
    $hasOptionErrorForCard = (int) old('product_id', 0) === $productId && $errors->has('product_option_value_id');
@endphp

<article class="group w-full min-w-0 overflow-hidden {{ $flat ? 'bg-white p-4' : 'rounded-2xl bg-white p-5 shadow-sm' }}" data-product-card>
    <div class="relative {{ $flat ? '-mt-4 overflow-hidden' : '-mt-5 overflow-hidden rounded-t-2xl' }}">
        <a href="{{ $productUrl }}" class="group block">
            @if ($imageUrl)
                <div class="relative">
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $productName }}"
                        class="{{ $flat ? 'block h-auto w-full max-w-full' : 'block h-auto w-full max-w-full rounded-xl' }} {{ $hoverImageUrl ? 'transition-opacity duration-300 group-hover:opacity-0' : '' }}"
                        loading="lazy"
                        decoding="async"
                    >
                    @if ($hoverImageUrl)
                        <img
                            src="{{ $hoverImageUrl }}"
                            alt="{{ $productName }}"
                            class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-300 group-hover:opacity-100 {{ $flat ? '' : 'rounded-xl' }}"
                            loading="lazy"
                            decoding="async"
                        >
                    @endif
                </div>
            @else
                <div class="{{ $flat ? 'flex min-h-56 w-full items-center justify-center bg-slate-100 text-xs font-semibold uppercase text-slate-500' : 'flex min-h-56 w-full items-center justify-center rounded-xl bg-slate-100 text-xs font-semibold uppercase text-slate-500' }}">
                    {{ __('ui.product.no_image') }}
                </div>
            @endif
        </a>
        <form
            method="POST"
            action="{{ route('wishlist.items.toggle', ['product' => $productId]) }}"
            class="absolute right-2 top-2"
            data-wishlist-form
            data-wishlisted="{{ $isWishlisted ? '1' : '0' }}"
            data-label-add="{{ __('ui.wishlist.add') }}"
            data-label-remove="{{ __('ui.wishlist.remove') }}"
            data-msg-failed="{{ __('ui.wishlist.status.failed') }}"
        >
            @csrf
            <button
                type="submit"
                class="inline-flex h-9 w-9 items-center justify-center border transition hover:border-slate-900 {{ $isWishlisted ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-700' : 'border-slate-200 bg-white/95 text-slate-700 hover:text-slate-900' }}"
                data-wishlist-button
                aria-label="{{ $isWishlisted ? __('ui.wishlist.remove') : __('ui.wishlist.add') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.8 8.6c0 5.9-8.8 10.9-8.8 10.9S3.2 14.5 3.2 8.6a4.8 4.8 0 0 1 8.8-2.7 4.8 4.8 0 0 1 8.8 2.7Z"></path>
                </svg>
            </button>
        </form>
        @if (! empty($discountPercent))
            <span class="absolute left-2 top-2 inline-flex h-7 items-center border border-rose-600 bg-rose-600 px-2 text-xs font-bold text-white">
                -{{ $discountPercent }}%
            </span>
        @endif

        <form
            method="POST"
            action="{{ route('cart.items.store') }}"
            class="pointer-events-none absolute inset-x-0 bottom-0 z-20 space-y-3 bg-black/80 p-3 opacity-0 transition-all duration-200 {{ $hasOptionErrorForCard ? 'opacity-100 pointer-events-auto' : '' }}"
            data-card-overlay
            data-product-card-form
            data-ga4-add-to-cart-form
            data-ga4-item-id="{{ $productSku }}"
            data-ga4-item-name="{{ $productName }}"
            data-ga4-item-price="{{ number_format((float) $productPriceValue, 2, '.', '') }}"
            data-ga4-item-brand="{{ $productBrand }}"
            data-ga4-item-category="{{ $productCategory }}"
            data-ga4-currency="EUR"
            data-product-name="{{ $productName }}"
            data-product-image="{{ (string) ($imageUrl ?? '') }}"
            data-cart-url="{{ route('cart.index') }}"
            data-modal-continue="{{ __('ui.cart.modal.continue') }}"
            data-modal-go-cart="{{ __('ui.cart.modal.go_to_cart') }}"
            data-modal-option="{{ __('ui.cart.modal.option') }}"
            data-modal-quantity="{{ __('ui.cart.modal.quantity') }}"
        >
            @csrf
            <input type="hidden" name="product_id" value="{{ $productId }}">
            @if ($optionRows !== [])
                <div class="w-full">
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach ($optionRows as $row)
                            @php $desktopInputId = $row['input_id'].'-desktop'; @endphp
                            <label for="{{ $desktopInputId }}" class="inline-flex h-9 min-w-9 cursor-pointer items-center justify-center border border-white/55 bg-transparent px-2.5 text-xs font-semibold text-white transition hover:border-white hover:bg-white/10 has-[:checked]:border-white has-[:checked]:bg-white has-[:checked]:text-slate-900">
                                <input
                                    id="{{ $desktopInputId }}"
                                    type="radio"
                                    name="product_option_value_id"
                                    value="{{ $row['id'] }}"
                                    class="sr-only"
                                >
                                <span>{{ $row['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p
                        class="{{ $hasOptionErrorForCard ? '' : 'hidden' }} text-xs font-semibold text-rose-300"
                        data-option-error
                        aria-live="polite"
                    >
                        {{ __('ui.cart.errors.select_size') }}
                    </p>
                </div>
            @endif
            <div class="flex items-center gap-2">
                <div class="inline-flex h-10 items-stretch bg-transparent" data-qty-control>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-white/55 text-base font-semibold text-white hover:border-white hover:bg-white/10" data-qty-dec aria-label="Decrease quantity">-</button>
                    <input type="text" name="quantity" value="1" inputmode="numeric" readonly class="h-10 w-10 border-y border-r border-white/55 border-l-0 bg-transparent p-0 text-center text-sm font-semibold text-white focus:ring-0" data-qty-input data-qty-value>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center border border-l-0 border-white/55 text-base font-semibold text-white hover:border-white hover:bg-white/10" data-qty-inc aria-label="Increase quantity">+</button>
                </div>
                <button type="submit" class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap border border-white/55 bg-transparent px-3 text-base font-semibold text-white hover:border-white hover:bg-white/10" aria-label="{{ __('ui.product.to_cart') }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 9h10l-1 10H8L7 9Z"></path>
                        <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
                    </svg>
                    <span class="truncate">{{ __('ui.product.to_cart') }}</span>
                </button>
            </div>
        </form>
    </div>

    <a href="{{ $productUrl }}" class="mt-4 block">
        <h3 class="text-base font-medium leading-tight text-slate-900">{{ $productName }}</h3>
    </a>

    <div class="mt-2 flex items-end justify-between">
        <div class="flex flex-col gap-1">
            <p class="text-base font-bold leading-none text-slate-900">{{ $price }}</p>
            @if (! empty($oldPrice))
                <p class="text-sm leading-none text-slate-500 line-through">{{ $oldPrice }}</p>
            @endif
            @if (! empty($lowest30DaysPrice))
                <p class="text-[11px] leading-tight text-slate-500">{{ __('ui.product.lowest_price_30_days', ['price' => $lowest30DaysPrice]) }}</p>
            @endif
        </div>
        <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center self-end border border-slate-900 bg-slate-900 text-white transition hover:bg-slate-700"
            data-card-overlay-toggle
            aria-expanded="{{ $hasOptionErrorForCard ? 'true' : 'false' }}"
            aria-label="{{ __('ui.product.add_to_cart') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 9h10l-1 10H8L7 9Z"></path>
                <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
            </svg>
        </button>
    </div>

</article>

@once
    @push('scripts')
        <script defer src="{{ asset('front-theme/scripts/product-card-options.js') }}"></script>
        <script defer src="{{ asset('front-theme/scripts/product-card-quantity.js') }}"></script>
        <script defer src="{{ asset('front-theme/scripts/product-card-cart-modal.js') }}?v={{ filemtime(public_path('front-theme/scripts/product-card-cart-modal.js')) }}"></script>
        <script defer src="{{ asset('front-theme/scripts/product-card-overlay-cart.js') }}?v={{ filemtime(public_path('front-theme/scripts/product-card-overlay-cart.js')) }}"></script>
        <script defer src="{{ asset('front-theme/scripts/wishlist-toggle.js') }}"></script>
    @endpush
@endonce
