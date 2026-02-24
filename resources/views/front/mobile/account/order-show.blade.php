@extends('front.mobile.layouts.store')

@section('title', __('ui.account.order_show.page_title', ['number' => $order->order_number]))
@section('header_title', __('ui.account.orders.title'))
@section('page_title', $order->order_number)

@section('content')
    @php
        $boxNow = is_array($order->payload['shipping']['boxnow'] ?? null) ? $order->payload['shipping']['boxnow'] : null;
    @endphp

    <div class="card card-style">
        <div class="content">
            <div class="d-flex mb-2">
                <h4 class="mb-0">{{ $order->order_number }}</h4>
                <a href="{{ route('account.orders') }}" class="ms-auto font-12 color-theme">{{ __('ui.account.nav.orders') }}</a>
            </div>
            <p class="font-12 opacity-70 mb-1">{{ optional($order->placed_at ?? $order->created_at)->format('Y-m-d H:i') }}</p>
            <span class="badge bg-highlight">{{ $order->status?->name ?? __('ui.account.orders.status_new') }}</span>
        </div>
    </div>

    @if (!empty($boxNow['locker_id']))
        <div class="card card-style">
            <div class="content">
                <h4 class="mb-2">{{ __('ui.account.order_show.boxnow.title') }}</h4>
                <p class="mb-1 font-13"><strong>{{ __('ui.account.order_show.boxnow.locker') }}:</strong> {{ $boxNow['locker_name'] ?: '-' }} ({{ $boxNow['locker_id'] }})</p>
                <p class="mb-0 font-13"><strong>{{ __('ui.account.order_show.boxnow.address') }}:</strong> {{ trim(($boxNow['address_line_1'] ?? '').', '.($boxNow['postal_code'] ?? '').' '.($boxNow['city'] ?? ''), ', ') ?: '-' }}</p>
            </div>
        </div>
    @endif

    <div class="card card-style">
        <div class="content">
            <h4 class="mb-3">{{ __('ui.account.order_show.table.item') }}</h4>
            @foreach ($order->items as $item)
                @php
                    $product = $item->product;
                    $productTranslation = $product?->translations?->firstWhere('locale', app()->getLocale())
                        ?? $product?->translations?->firstWhere('locale', config('app.locale'))
                        ?? $product?->translations?->first();
                    $productUrl = $product && $product->is_active && $productTranslation?->slug
                        ? route('products.show', ['slug' => $productTranslation->slug])
                        : null;
                    $valueTranslation = $item->productOptionValue?->optionValue?->translations?->firstWhere('locale', app()->getLocale())
                        ?? $item->productOptionValue?->optionValue?->translations?->firstWhere('locale', config('app.locale'))
                        ?? $item->productOptionValue?->optionValue?->translations?->first();
                    $parentTranslation = $item->productOptionValue?->parentOptionValue?->translations?->firstWhere('locale', app()->getLocale())
                        ?? $item->productOptionValue?->parentOptionValue?->translations?->firstWhere('locale', config('app.locale'))
                        ?? $item->productOptionValue?->parentOptionValue?->translations?->first();
                    $optionLabel = trim((string) (($parentTranslation?->name ? $parentTranslation->name.': ' : '').($valueTranslation?->name ?? '')));
                @endphp
                <div class="d-flex mb-2">
                    <div class="w-100 pe-3">
                        <h6 class="font-14 mb-1">
                            @if ($productUrl)
                                <a href="{{ $productUrl }}" class="color-theme">{{ $item->name }}</a>
                            @else
                                {{ $item->name }}
                            @endif
                        </h6>
                        @if ($item->sku)
                            <p class="font-11 opacity-60 mb-1">SKU: {{ $item->sku }}</p>
                        @endif
                        @if ($optionLabel !== '')
                            <p class="font-11 opacity-60 mb-1">{{ $optionLabel }}</p>
                        @endif
                        <p class="font-11 opacity-60 mb-1">{{ __('ui.account.order_show.table.qty') }} {{ (int) $item->quantity }} • {{ __('ui.account.order_show.table.price') }} {{ \App\Support\Currency::format((float) $item->unit_price, $order->currency_code) }}</p>

                        @if ($product && $product->is_active)
                            <form method="POST" action="{{ route('cart.items.store') }}" class="mt-1">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                @if ($item->product_option_value_id)
                                    <input type="hidden" name="product_option_value_id" value="{{ (int) $item->product_option_value_id }}">
                                @endif
                                <input type="hidden" name="quantity" value="{{ max(1, (int) $item->quantity) }}">
                                <button type="submit" class="btn btn-xxs rounded-0 border border-gray-dark color-theme bg-white px-3">{{ __('ui.account.order_show.reorder') }}</button>
                            </form>
                        @endif
                    </div>
                    <p class="font-14 font-600 mb-0">{{ \App\Support\Currency::format((float) $item->line_total, $order->currency_code) }}</p>
                </div>
                @if (! $loop->last)
                    <div class="divider my-2"></div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <h4 class="mb-3">{{ __('ui.account.order_show.totals.title') }}</h4>
            @foreach ($order->totals as $total)
                <div class="d-flex mb-2">
                    <span class="font-13 opacity-70">{{ $total->title }}</span>
                    <span class="ms-auto font-600">{{ \App\Support\Currency::format((float) $total->value, $order->currency_code) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <h4 class="mb-3">{{ __('ui.account.order_show.timeline.title') }}</h4>
            @forelse ($order->history as $entry)
                <div class="mb-2">
                    <p class="mb-1 font-13">{{ optional($entry->created_at)->format('Y-m-d H:i') }} {{ __('ui.account.order_show.timeline.to') }} {{ $entry->toStatus?->name ?? __('ui.account.order_show.timeline.status_updated') }}</p>
                    @if ($entry->comment)
                        <p class="mb-0 font-12 opacity-70">{{ $entry->comment }}</p>
                    @endif
                </div>
                @if (! $loop->last)
                    <div class="divider my-2"></div>
                @endif
            @empty
                <p class="mb-0 opacity-70">{{ __('ui.account.order_show.timeline.empty') }}</p>
            @endforelse
        </div>
    </div>
@endsection
