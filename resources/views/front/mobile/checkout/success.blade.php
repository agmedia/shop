@extends('front.mobile.layouts.store')

@section('title', __('ui.checkout.success.page_title'))
@section('header_title', __('ui.checkout.success.order_complete'))
@section('page_title', __('ui.checkout.success.page_title'))

@section('content')
    @php
        $boxNow = is_array($order->payload['shipping']['boxnow'] ?? null) ? $order->payload['shipping']['boxnow'] : null;
    @endphp

    <div class="card card-style bg-green-dark rounded-0" data-card-height="180">
        <div class="card-center text-center px-3">
            <i class="fa fa-circle-check color-white font-40 d-block mb-2"></i>
            <h2 class="color-white font-800 mb-1">{{ __('ui.checkout.success.title') }}</h2>
            <p class="color-white mb-0 opacity-70">{{ __('ui.checkout.success.order_confirmed', ['number' => $order->order_number]) }}</p>
        </div>
        <div class="card-overlay bg-black opacity-30"></div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <h4 class="mb-3">{{ __('ui.checkout.success.summary') }}</h4>
            <div class="d-flex mb-2">
                <span class="font-13 opacity-70">{{ __('ui.checkout.success.status') }}</span>
                <span class="ms-auto font-600">{{ $order->status?->name ?? __('ui.checkout.success.status_fallback_new') }}</span>
            </div>
            <div class="d-flex mb-2">
                <span class="font-13 opacity-70">{{ __('ui.checkout.success.items') }}</span>
                <span class="ms-auto font-600">{{ (int) $order->item_qty }}</span>
            </div>
            <div class="d-flex">
                <span class="font-13 opacity-70">{{ __('ui.checkout.success.grand_total') }}</span>
                <span class="ms-auto font-700">{{ $order->currency_code }} {{ number_format((float) $order->grand_total, 2) }}</span>
            </div>

            @if ($order->totals->isNotEmpty())
                <div class="divider mt-3 mb-3"></div>
                @foreach ($order->totals as $total)
                    <div class="d-flex mb-2">
                        <span class="font-13 opacity-70">{{ $total->title }}</span>
                        <span class="ms-auto">{{ $order->currency_code }} {{ number_format((float) $total->value, 2) }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    @if ($order->items->isNotEmpty())
        <div class="card card-style rounded-0">
            <div class="content">
                <h4 class="mb-3">{{ __('ui.checkout.success.items') }}</h4>
                @foreach ($order->items as $item)
                    <div class="d-flex mb-2">
                        <div class="w-100 pe-3">
                            <h6 class="font-14 mb-1">{{ $item->name }}</h6>
                            <p class="font-11 opacity-60 mb-0">{{ __('ui.checkout.success.qty') }} {{ (int) $item->quantity }}</p>
                        </div>
                        <div class="text-end">
                            <p class="font-14 font-600 mb-0">{{ $order->currency_code }} {{ number_format((float) $item->line_total, 2) }}</p>
                        </div>
                    </div>
                    @if (! $loop->last)
                        <div class="divider my-2"></div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if (!empty($bankTransfer) && !empty($bankTransfer['receiver_iban']))
        <div class="card card-style rounded-0">
            <div class="content">
                <h4 class="mb-3">{{ __('ui.checkout.success.bank_transfer_title') }}</h4>
                <p class="font-13 opacity-70 mb-2">{{ __('ui.checkout.success.bank_transfer_note') }}</p>
                <div class="font-13">
                    <div><strong>{{ __('ui.checkout.success.bank_recipient') }}:</strong> {{ $bankTransfer['receiver_name'] ?? '-' }}</div>
                    <div><strong>{{ __('ui.checkout.success.bank_iban') }}:</strong> {{ $bankTransfer['receiver_iban'] ?? '-' }}</div>
                    <div><strong>{{ __('ui.checkout.success.bank_model') }}:</strong> {{ $bankTransfer['model'] ?? '-' }}</div>
                    <div><strong>{{ __('ui.checkout.success.bank_reference') }}:</strong> {{ $bankTransfer['reference'] ?? '-' }}</div>
                    <div><strong>{{ __('ui.checkout.success.bank_amount') }}:</strong> {{ $order->currency_code }} {{ number_format((float) ($bankTransfer['amount'] ?? 0), 2) }}</div>
                    <div><strong>{{ __('ui.checkout.success.bank_description') }}:</strong> {{ $bankTransfer['description'] ?? '-' }}</div>
                </div>

                @if (!empty($bankTransfer['qr_image_base64']))
                    <div class="text-center mt-3">
                        <img
                            src="data:{{ $bankTransfer['qr_image_mime'] ?? 'image/png' }};base64,{{ $bankTransfer['qr_image_base64'] }}"
                            alt="{{ __('ui.checkout.success.bank_qr_alt') }}"
                            style="max-width: 300px; width: 100%; height: auto;"
                        >
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if (!empty($boxNow['locker_id']))
        <div class="card card-style rounded-0">
            <div class="content">
                <h4 class="mb-3">{{ __('ui.checkout.success.boxnow_title') }}</h4>
                <p class="font-13 mb-2"><strong>{{ __('ui.checkout.success.boxnow_locker') }}:</strong> {{ $boxNow['locker_name'] ?: '-' }} ({{ $boxNow['locker_id'] }})</p>
                <p class="font-13 mb-0"><strong>{{ __('ui.checkout.success.boxnow_address') }}:</strong> {{ trim(($boxNow['address_line_1'] ?? '').', '.($boxNow['postal_code'] ?? '').' '.($boxNow['city'] ?? ''), ', ') ?: '-' }}</p>
            </div>
        </div>
    @endif

    <a href="{{ route('shop.index') }}" class="btn btn-margins btn-full gradient-blue font-13 btn-l font-600 rounded-0">{{ __('ui.checkout.success.continue_shopping') }}</a>

    @auth
        <a href="{{ route('account.orders.show', ['orderNumber' => $order->order_number]) }}" class="btn btn-margins btn-full btn-border border-gray-dark color-gray-dark font-13 btn-l font-600 rounded-0 mt-2">{{ __('ui.checkout.success.view_in_account') }}</a>
    @endauth

    @php
        $analytics = $storeSettings['analytics'] ?? [];
        $shouldTrackPurchase = (bool) ($analytics['enabled'] ?? false)
            && (bool) ($analytics['purchase_event_enabled'] ?? true)
            && trim((string) ($analytics['ga4_measurement_id'] ?? '')) !== '';
        $eventName = trim((string) ($analytics['purchase_event_name'] ?? 'purchase')) ?: 'purchase';
        $eventItems = $order->items->map(static function ($item): array {
            return [
                'item_id' => (string) ($item->sku ?: $item->code ?: $item->id),
                'item_name' => (string) $item->name,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->unit_price,
            ];
        })->values()->all();
    @endphp

    @if ($shouldTrackPurchase)
        @push('scripts')
            <script>
                if (typeof gtag === 'function') {
                    gtag('{{ $eventName }}', {
                        transaction_id: @json((string) $order->order_number),
                        currency: @json((string) ($order->currency_code ?: 'EUR')),
                        value: {{ (float) $order->grand_total }},
                        tax: {{ (float) $order->tax_total }},
                        shipping: {{ (float) $order->shipping_total }},
                        coupon: @json((string) ($order->payload['coupon_code'] ?? '')),
                        items: @json($eventItems)
                    });
                }
            </script>
        @endpush
    @endif
@endsection
