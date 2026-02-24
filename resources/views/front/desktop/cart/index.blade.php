@extends('front.desktop.layouts.store')

@section('title', __('ui.cart.page_title'))

@section('content')
    <section class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.cart.title') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('ui.cart.subtitle') }}</p>
    </section>

    @if ($lines->isEmpty())
        <div class="border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-slate-600">{{ __('ui.cart.empty') }}</p>
            <a href="{{ route('shop.index') }}" class="mt-4 inline-flex bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">{{ __('ui.cart.actions.continue') }}</a>
        </div>
    @else
        <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1.45fr)_minmax(430px,1fr)]">
            <div class="border border-slate-200 bg-white">
                <div class="divide-y divide-slate-200 md:hidden">
                    @foreach ($lines as $line)
                        @php
                            $product = $line['product'];
                            $translation = $line['translation'];
                            $manufacturerTranslation = $product->relationLoaded('manufacturer')
                                ? ($product->manufacturer?->translations?->firstWhere('locale', $locale ?? app()->getLocale())
                                    ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale ?? config('app.locale')))
                                : null;
                            $categoryTranslation = $product->relationLoaded('categories')
                                ? ($product->categories?->first()?->translations?->firstWhere('locale', $locale ?? app()->getLocale())
                                    ?? $product->categories?->first()?->translations?->firstWhere('locale', $fallbackLocale ?? config('app.locale')))
                                : null;
                            $productImage = $product->getFirstMedia('product_main')
                                ?? $product->getFirstMedia('product_gallery');
                            $productImageUrl = $productImage
                                ? ($productImage->hasGeneratedConversion('thumb_100x100') ? $productImage->getUrl('thumb_100x100') : $productImage->getUrl())
                                : null;
                            $displayCurrent = (float) ($line['display_unit_price'] ?? $line['unit_price'] ?? 0);
                            $displayBase = (float) ($line['display_base_unit_price'] ?? $line['base_unit_price'] ?? $displayCurrent);
                        @endphp
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <a href="{{ route('products.show', ['slug' => $translation?->slug ?? $product->id]) }}" class="block w-16 shrink-0 border border-slate-200 bg-slate-50 p-1">
                                    @if ($productImageUrl)
                                        <img
                                            src="{{ $productImageUrl }}"
                                            alt="{{ $translation?->name ?? $product->code }}"
                                            class="h-auto w-full"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase text-slate-500">{{ __('ui.product.no_image') }}</span>
                                    @endif
                                </a>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('products.show', ['slug' => $translation?->slug ?? $product->id]) }}" class="block truncate font-semibold text-slate-900 hover:text-blue-700">
                                        {{ $translation?->name ?? $product->code }}
                                    </a>
                                    @if (!empty($line['sku']))
                                        <p class="mt-1 text-xs text-slate-500">SKU: {{ $line['sku'] }}</p>
                                    @endif
                                    @if (!empty($line['option_label']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $line['option_label'] }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.cart.table.price') }}</p>
                                    <div class="mt-0.5 flex flex-col gap-0.5">
                                        @if ($displayBase > ($displayCurrent + 0.0001))
                                            <span class="text-xs text-slate-500 line-through">{{ number_format($displayBase, 2) }} €</span>
                                        @endif
                                        <span class="font-semibold text-slate-900">{{ number_format($displayCurrent, 2) }} €</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.cart.table.total') }}</p>
                                    <p class="mt-0.5 font-semibold text-slate-900">{{ number_format((float) ($line['display_line_total'] ?? $line['line_total']), 2) }} €</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('cart.items.update', ['product' => $product->id]) }}" class="flex min-w-0 flex-1 items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    @if (!empty($line['product_option_value_id']))
                                        <input type="hidden" name="product_option_value_id" value="{{ (int) $line['product_option_value_id'] }}">
                                    @endif
                                    <input type="number" name="quantity" value="{{ (int) $line['quantity'] }}" min="0" max="999" class="h-10 min-w-0 w-20 border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                    <button type="submit" class="h-10 flex-1 border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('ui.cart.table.save') }}</button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('cart.items.destroy', ['product' => $product->id]) }}"
                                    data-ga4-remove-from-cart-form
                                    data-ga4-item-id="{{ (string) ($line['sku'] ?: $product->sku ?: $product->id) }}"
                                    data-ga4-item-name="{{ $translation?->name ?? $product->code }}"
                                    data-ga4-item-price="{{ number_format((float) $displayCurrent, 2, '.', '') }}"
                                    data-ga4-item-brand="{{ (string) ($manufacturerTranslation?->name ?? '') }}"
                                    data-ga4-item-category="{{ (string) ($categoryTranslation?->name ?? '') }}"
                                    data-ga4-currency="EUR"
                                    data-ga4-quantity="{{ (int) $line['quantity'] }}"
                                >
                                    @csrf
                                    @method('DELETE')
                                    @if (!empty($line['product_option_value_id']))
                                        <input type="hidden" name="product_option_value_id" value="{{ (int) $line['product_option_value_id'] }}">
                                    @endif
                                    <button
                                        type="submit"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-base font-bold leading-none text-white transition hover:bg-slate-700"
                                        aria-label="{{ __('ui.cart.table.remove') }}"
                                        title="{{ __('ui.cart.table.remove') }}"
                                    >
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto md:block md:overflow-visible">
                <table class="min-w-[720px] w-full text-sm md:min-w-0">
                    <thead class="bg-slate-100/70 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('ui.cart.table.product') }}</th>
                        <th class="px-4 py-3">{{ __('ui.cart.table.price') }}</th>
                        <th class="px-4 py-3">{{ __('ui.cart.table.quantity') }}</th>
                        <th class="px-4 py-3">{{ __('ui.cart.table.total') }}</th>
                        <th class="px-4 py-3">{{ __('ui.cart.table.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($lines as $line)
                        @php
                            $product = $line['product'];
                            $translation = $line['translation'];
                            $manufacturerTranslation = $product->relationLoaded('manufacturer')
                                ? ($product->manufacturer?->translations?->firstWhere('locale', $locale ?? app()->getLocale())
                                    ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale ?? config('app.locale')))
                                : null;
                            $categoryTranslation = $product->relationLoaded('categories')
                                ? ($product->categories?->first()?->translations?->firstWhere('locale', $locale ?? app()->getLocale())
                                    ?? $product->categories?->first()?->translations?->firstWhere('locale', $fallbackLocale ?? config('app.locale')))
                                : null;
                            $productImage = $product->getFirstMedia('product_main')
                                ?? $product->getFirstMedia('product_gallery');
                            $productImageUrl = $productImage
                                ? ($productImage->hasGeneratedConversion('thumb_100x100') ? $productImage->getUrl('thumb_100x100') : $productImage->getUrl())
                                : null;
                            $displayCurrent = (float) ($line['display_unit_price'] ?? $line['unit_price'] ?? 0);
                        @endphp
                        <tr class="border-t border-slate-200">
                            <td class="px-4 py-4">
                                <div class="flex items-start gap-3">
                                    <a href="{{ route('products.show', ['slug' => $translation?->slug ?? $product->id]) }}" class="block w-16 shrink-0 border border-slate-200 bg-slate-50 p-1">
                                        @if ($productImageUrl)
                                            <img
                                                src="{{ $productImageUrl }}"
                                                alt="{{ $translation?->name ?? $product->code }}"
                                                class="h-auto w-full"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase text-slate-500">{{ __('ui.product.no_image') }}</span>
                                        @endif
                                    </a>
                                    <div>
                                        <a href="{{ route('products.show', ['slug' => $translation?->slug ?? $product->id]) }}" class="font-semibold text-slate-900 hover:text-blue-700">
                                            {{ $translation?->name ?? $product->code }}
                                        </a>
                                        @if (!empty($line['sku']))
                                            <p class="mt-1 text-xs text-slate-500">SKU: {{ $line['sku'] }}</p>
                                        @endif
                                        @if (!empty($line['option_label']))
                                            <p class="mt-1 text-xs text-slate-500">{{ $line['option_label'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $displayCurrent = (float) ($line['display_unit_price'] ?? $line['unit_price'] ?? 0);
                                    $displayBase = (float) ($line['display_base_unit_price'] ?? $line['base_unit_price'] ?? $displayCurrent);
                                @endphp
                                <div class="flex flex-col gap-0.5">
                                    @if ($displayBase > ($displayCurrent + 0.0001))
                                        <span class="text-xs text-slate-500 line-through">{{ number_format($displayBase, 2) }} €</span>
                                    @endif
                                    <span class="font-semibold text-slate-900">{{ number_format($displayCurrent, 2) }} €</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <form method="POST" action="{{ route('cart.items.update', ['product' => $product->id]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    @if (!empty($line['product_option_value_id']))
                                        <input type="hidden" name="product_option_value_id" value="{{ (int) $line['product_option_value_id'] }}">
                                    @endif
                                    <input type="number" name="quantity" value="{{ (int) $line['quantity'] }}" min="0" max="999" class="w-20 border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                    <button type="submit" class="border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('ui.cart.table.save') }}</button>
                                </form>
                            </td>
                            <td class="px-4 py-4 font-semibold text-slate-900">{{ number_format((float) ($line['display_line_total'] ?? $line['line_total']), 2) }} €</td>
                            <td class="px-4 py-4">
                                <form
                                    method="POST"
                                    action="{{ route('cart.items.destroy', ['product' => $product->id]) }}"
                                    data-ga4-remove-from-cart-form
                                    data-ga4-item-id="{{ (string) ($line['sku'] ?: $product->sku ?: $product->id) }}"
                                    data-ga4-item-name="{{ $translation?->name ?? $product->code }}"
                                    data-ga4-item-price="{{ number_format((float) $displayCurrent, 2, '.', '') }}"
                                    data-ga4-item-brand="{{ (string) ($manufacturerTranslation?->name ?? '') }}"
                                    data-ga4-item-category="{{ (string) ($categoryTranslation?->name ?? '') }}"
                                    data-ga4-currency="EUR"
                                    data-ga4-quantity="{{ (int) $line['quantity'] }}"
                                >
                                    @csrf
                                    @method('DELETE')
                                    @if (!empty($line['product_option_value_id']))
                                        <input type="hidden" name="product_option_value_id" value="{{ (int) $line['product_option_value_id'] }}">
                                    @endif
                                    <button
                                        type="submit"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-base font-bold leading-none text-white transition hover:bg-slate-700"
                                        aria-label="{{ __('ui.cart.table.remove') }}"
                                        title="{{ __('ui.cart.table.remove') }}"
                                    >
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            <aside class="h-fit self-start border border-slate-200 bg-white p-4 sm:p-6 xl:sticky xl:top-28">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('ui.cart.summary.title') }}</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">{{ __('ui.cart.summary.items') }}</dt>
                        <dd class="font-semibold text-slate-900">{{ $summary['item_qty'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">{{ __('ui.cart.summary.subtotal') }}</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format((float) $summary['subtotal'], 2) }} €</dd>
                    </div>
                    @if ((float) ($summary['discount_total'] ?? 0) > 0)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">{{ __('ui.cart.summary.discount') }}</dt>
                            <dd class="font-semibold text-emerald-700">-{{ number_format((float) $summary['discount_total'], 2) }} €</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">
                            {{ __('ui.cart.summary.tax') }}
                            @if ((float) ($summary['tax_rate'] ?? 0) > 0)
                                ({{ rtrim(rtrim(number_format((float) $summary['tax_rate'], 2), '0'), '.') }}%)
                            @endif
                        </dt>
                        <dd class="font-semibold text-slate-900">{{ number_format((float) ($summary['tax_total'] ?? 0), 2) }} €</dd>
                    </div>
                    <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                        <dt class="text-slate-900">{{ __('ui.cart.summary.total') }}</dt>
                        <dd class="font-bold text-slate-900">{{ number_format((float) ($summary['grand_total'] ?? 0), 2) }} €</dd>
                    </div>
                </dl>

                @php
                    $couponOpen = $errors->has('coupon_code') || ((string) ($summary['coupon_code'] ?? '') !== '');
                @endphp
                <div class="mt-4 border-t border-slate-200 pt-4 {{ $couponOpen ? 'mb-4' : 'mb-0' }}" data-coupon-wrap>
                    <button
                        type="button"
                        class="h-10 w-full border border-slate-300 bg-white px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-50"
                        data-coupon-toggle
                        data-label-open="{{ __('ui.cart.coupon.toggle_open') }}"
                        data-label-close="{{ __('ui.cart.coupon.toggle_close') }}"
                        aria-expanded="{{ $couponOpen ? 'true' : 'false' }}"
                        aria-controls="cart-coupon-panel"
                    >
                        {{ $couponOpen ? __('ui.cart.coupon.toggle_close') : __('ui.cart.coupon.toggle_open') }}
                    </button>
                    <div
                        id="cart-coupon-panel"
                        class="mt-3 overflow-hidden transition-all duration-300"
                        data-coupon-panel
                        data-open="{{ $couponOpen ? '1' : '0' }}"
                        style="{{ $couponOpen ? '' : 'max-height:0;opacity:0;' }}"
                    >
                        <label for="coupon_code" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.cart.coupon.label') }}</label>
                        <form method="POST" action="{{ route('cart.coupon.apply') }}" class="flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input
                                id="coupon_code"
                                type="text"
                                name="coupon_code"
                                value="{{ (string) ($summary['coupon_code'] ?? '') }}"
                                placeholder="{{ __('ui.cart.coupon.placeholder') }}"
                                class="h-10 min-w-0 flex-1 border border-slate-300 px-3 text-sm"
                            >
                            <button type="submit" class="h-10 w-full border border-slate-300 bg-white px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-100 sm:w-auto">
                                {{ __('ui.cart.actions.apply_coupon') }}
                            </button>
                        </form>
                        @if ((string) ($summary['coupon_code'] ?? '') !== '')
                            <form method="POST" action="{{ route('cart.coupon.remove') }}" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="h-9 w-full border border-slate-300 px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-100">
                                    {{ __('ui.cart.actions.remove_coupon') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <a href="{{ route('checkout.create') }}" class="mt-0 block bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-700">{{ __('ui.cart.actions.checkout') }}</a>
            </aside>
        </div>
    @endif
@endsection

@push('scripts')
    <script defer src="{{ asset('front-theme/scripts/cart-coupon-toggle.js') }}?v={{ filemtime(public_path('front-theme/scripts/cart-coupon-toggle.js')) }}"></script>
@endpush
