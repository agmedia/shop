@extends('front.desktop.layouts.store')

@section('title', __('ui.checkout.success.page_title'))

@section('content')
    @php
        $boxNow = is_array($order->payload['shipping']['boxnow'] ?? null) ? $order->payload['shipping']['boxnow'] : null;
    @endphp

    <section class="border border-emerald-300 bg-white p-8">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('ui.checkout.success.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.checkout.success.title') }}</h1>
        <p class="mt-3 text-slate-600">{{ __('ui.checkout.success.order_number') }}: <span class="font-semibold text-slate-900">{{ $order->order_number }}</span></p>

        <dl class="mt-6 grid gap-3 text-sm md:grid-cols-2">
            <div class="border border-slate-200 bg-slate-100 p-4">
                <dt class="text-slate-500">{{ __('ui.checkout.success.status') }}</dt>
                <dd class="mt-1 font-semibold text-slate-900">{{ $order->status?->name ?? __('ui.checkout.success.status_fallback_new') }}</dd>
            </div>
            <div class="border border-slate-200 bg-slate-100 p-4">
                <dt class="text-slate-500">{{ __('ui.checkout.success.grand_total') }}</dt>
                <dd class="mt-1 font-semibold text-slate-900">{{ $order->currency_code }} {{ number_format((float) $order->grand_total, 2) }}</dd>
            </div>
        </dl>

        @if (!empty($bankTransfer) && !empty($bankTransfer['receiver_iban']))
            <section class="mt-6 border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('ui.checkout.success.bank_transfer_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('ui.checkout.success.bank_transfer_note') }}</p>

                <div class="mt-3 grid gap-2 text-sm text-slate-800 md:grid-cols-2">
                    <p><strong>{{ __('ui.checkout.success.bank_recipient') }}:</strong> {{ $bankTransfer['receiver_name'] ?? '-' }}</p>
                    <p><strong>{{ __('ui.checkout.success.bank_iban') }}:</strong> {{ $bankTransfer['receiver_iban'] ?? '-' }}</p>
                    <p><strong>{{ __('ui.checkout.success.bank_model') }}:</strong> {{ $bankTransfer['model'] ?? '-' }}</p>
                    <p><strong>{{ __('ui.checkout.success.bank_reference') }}:</strong> {{ $bankTransfer['reference'] ?? '-' }}</p>
                    <p><strong>{{ __('ui.checkout.success.bank_amount') }}:</strong> {{ $order->currency_code }} {{ number_format((float) ($bankTransfer['amount'] ?? 0), 2) }}</p>
                    <p><strong>{{ __('ui.checkout.success.bank_description') }}:</strong> {{ $bankTransfer['description'] ?? '-' }}</p>
                </div>

                @if (!empty($bankTransfer['qr_image_base64']))
                    <div class="mt-4">
                        <img
                            src="data:{{ $bankTransfer['qr_image_mime'] ?? 'image/png' }};base64,{{ $bankTransfer['qr_image_base64'] }}"
                            alt="{{ __('ui.checkout.success.bank_qr_alt') }}"
                            class="h-auto w-full max-w-[380px] border border-slate-200 bg-white p-2"
                        >
                    </div>
                @endif
            </section>
        @endif

        @if (!empty($boxNow['locker_id']))
            <section class="mt-6 border border-blue-200 bg-blue-50 p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('ui.checkout.success.boxnow_title') }}</h2>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('ui.checkout.success.boxnow_locker') }}:</strong> {{ $boxNow['locker_name'] ?: '-' }} ({{ $boxNow['locker_id'] }})</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('ui.checkout.success.boxnow_address') }}:</strong> {{ trim(($boxNow['address_line_1'] ?? '').', '.($boxNow['postal_code'] ?? '').' '.($boxNow['city'] ?? ''), ', ') ?: '-' }}</p>
            </section>
        @endif

        <div class="mt-8 flex flex-wrap gap-2">
            @auth
                <a href="{{ route('account.orders.show', ['orderNumber' => $order->order_number]) }}" class="bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">{{ __('ui.checkout.success.view_in_account') }}</a>
            @endauth
            <a href="{{ route('shop.index') }}" class="border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('ui.checkout.success.continue_shopping') }}</a>
        </div>
    </section>

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
