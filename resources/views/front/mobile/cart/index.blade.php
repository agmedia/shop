@extends('front.mobile.layouts.store')

@section('title', 'Cart')
@section('header_title', 'Your Cart')
@section('page_title', 'Cart')

@section('content')
    @if ($lines->isEmpty())
        <div class="card card-style"><div class="content"><p class="mb-2">Your cart is empty.</p><a href="{{ route('shop.index') }}" class="btn btn-s gradient-blue rounded-s font-600">Start shopping</a></div></div>
    @else
        @foreach ($lines as $line)
            @php
                $product = $line['product'];
                $translation = $line['translation'];
                $manufacturerTranslation = $product->relationLoaded('manufacturer')
                    ? ($product->manufacturer?->translations?->firstWhere('locale', app()->getLocale())
                        ?? $product->manufacturer?->translations?->firstWhere('locale', config('app.locale')))
                    : null;
                $categoryTranslation = $product->relationLoaded('categories')
                    ? ($product->categories?->first()?->translations?->firstWhere('locale', app()->getLocale())
                        ?? $product->categories?->first()?->translations?->firstWhere('locale', config('app.locale')))
                    : null;
                $displayCurrent = (float) ($line['display_unit_price'] ?? $line['unit_price'] ?? 0);
            @endphp
            <div class="card card-style mb-2">
                <div class="content">
                    <div class="d-flex">
                        <div class="w-100 me-2">
                            <h6 class="font-500 font-14 pb-1">{{ $translation?->name ?? $product->code }}</h6>
                            @if (!empty($line['sku']))
                                <p class="font-11 opacity-60 mb-1">SKU: {{ $line['sku'] }}</p>
                            @endif
                            @if (!empty($line['option_label']))
                                <p class="font-11 opacity-60 mb-1">{{ $line['option_label'] }}</p>
                            @endif
                            <h4 class="font-700 mb-1">EUR {{ number_format((float) ($line['display_line_total'] ?? $line['line_total']), 2) }}</h4>
                            <p class="font-11 opacity-60 mb-0">Unit EUR {{ number_format((float) ($line['display_unit_price'] ?? $line['unit_price']), 2) }}</p>
                        </div>
                        <div class="align-self-center" style="min-width:88px;">
                            <form method="POST" action="{{ route('cart.items.update', ['product' => $product->id]) }}" class="mb-2">
                                @csrf
                                @method('PATCH')
                                @if (!empty($line['product_option_value_id']))
                                    <input type="hidden" name="product_option_value_id" value="{{ (int) $line['product_option_value_id'] }}">
                                @endif
                                <input type="number" name="quantity" value="{{ (int) $line['quantity'] }}" min="0" max="999" class="form-control mb-1" style="height:32px;">
                                <button type="submit" class="btn btn-3d btn-xs font-600 bg-highlight">Save</button>
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
                                <button type="submit" class="btn btn-xs bg-red-dark font-600">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card card-style mt-n2">
            <div class="content mb-2 mt-3">
                <div class="d-flex">
                    <div class="pe-4 w-60">
                        <p class="font-600 color-highlight mb-0 font-13">Total</p>
                        <h2>EUR {{ number_format((float) ($summary['grand_total'] ?? $summary['subtotal']), 2) }}</h2>
                    </div>
                    <div class="w-100 pt-1">
                        <h6 class="font-14 font-700">Items <span class="float-end color-theme">{{ $summary['item_qty'] }}</span></h6>
                        <div class="divider mb-2 mt-1"></div>
                        <h6 class="font-14 font-700">Lines <span class="float-end color-theme">{{ $summary['line_count'] }}</span></h6>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('checkout.create') }}" class="btn btn-margins btn-full gradient-blue font-13 btn-l font-600 mt-3 rounded-sm">Proceed to Checkout</a>

        <form method="POST" action="{{ route('cart.clear') }}" class="content mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-full btn-border border-gray-dark color-gray-dark rounded-sm font-600">Clear cart</button>
        </form>
    @endif
@endsection
