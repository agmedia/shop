<?php

namespace App\Services\Integrations\Klaviyo;

use App\Models\Sales\Order\Order;
use App\Models\Sales\Order\OrderItem;
use App\Services\Front\StoreSettingsService;
use App\Services\Payments\CorvusPayFormService;
use App\Services\Payments\KeksPayService;
use App\Services\Payments\WSPayFormService;
use App\Support\Consent\StorefrontConsent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KlaviyoOrderService
{
    private const REVISION = '2026-07-15';

    public function __construct(
        private readonly StoreSettingsService $settings,
        private readonly CorvusPayFormService $corvus,
        private readonly WSPayFormService $wspay,
        private readonly KeksPayService $keks,
    ) {}

    public function enabled(): bool
    {
        $settings = $this->settings->newsletter();

        return ($settings['provider'] ?? '') === 'klaviyo'
            && trim((string) ($settings['klaviyo_api_key'] ?? '')) !== '';
    }

    public function eligible(Order $order): bool
    {
        if (! StorefrontConsent::orderAllowsMarketing($order)
            || $order->source !== 'web' || ! $order->placed_at
            || ! filter_var(trim((string) $order->customer_email), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $status = $order->status;
        if ($status?->is_cancelled || in_array(strtolower((string) $status?->code), [
            'cancelled', 'canceled', 'failed', 'payment_failed',
        ], true)) {
            return false;
        }

        $code = (string) $order->payment_method_code;
        $requiresPayment = $this->corvus->isCorvusCode($code)
            || $this->wspay->isWspayCode($code)
            || $this->keks->isKeksCode($code);

        return ! $requiresPayment || $order->paid_at !== null;
    }

    public function track(Order $order): void
    {
        if (! $this->enabled() || ! $this->eligible($order)) {
            return;
        }

        $order->loadMissing('items');
        $settings = $this->settings->newsletter();
        $items = $order->items->map(fn (OrderItem $item): array => [
            'ProductID' => $item->product_id !== null ? (string) $item->product_id : (string) ($item->code ?: $item->sku),
            'SKU' => (string) $item->sku,
            'ProductName' => (string) $item->name,
            'Quantity' => (int) $item->quantity,
            'ItemPrice' => (float) $item->unit_price,
            'RowTotal' => (float) $item->line_total,
        ])->values()->all();

        $response = Http::timeout(15)
            ->accept('application/vnd.api+json')
            ->contentType('application/vnd.api+json')
            ->withHeaders([
                'Authorization' => 'Klaviyo-API-Key '.trim((string) $settings['klaviyo_api_key']),
                'revision' => self::REVISION,
            ])
            ->post('https://a.klaviyo.com/api/events/', [
                'data' => [
                    'type' => 'event',
                    'attributes' => [
                        'unique_id' => 'web-order-'.($order->order_number ?: $order->id),
                        'time' => $order->placed_at->toIso8601String(),
                        'value' => (float) $order->grand_total,
                        'value_currency' => strtoupper((string) $order->currency_code),
                        'metric' => [
                            'data' => [
                                'type' => 'metric',
                                'attributes' => ['name' => 'Placed Order'],
                            ],
                        ],
                        'profile' => [
                            'data' => [
                                'type' => 'profile',
                                'attributes' => [
                                    'email' => strtolower(trim((string) $order->customer_email)),
                                    'first_name' => trim((string) $order->billing_first_name),
                                    'last_name' => trim((string) $order->billing_last_name),
                                ],
                            ],
                        ],
                        'properties' => [
                            'OrderId' => (string) $order->id,
                            'OrderNumber' => (string) $order->order_number,
                            'ItemNames' => $order->items->pluck('name')->values()->all(),
                            'Items' => $items,
                            'DiscountCode' => (string) data_get($order->payload, 'coupon_code', ''),
                            'DiscountValue' => (float) $order->discount_total,
                            'ShippingCost' => (float) $order->shipping_total,
                            'PaymentMethod' => (string) ($order->payment_method_name ?: $order->payment_method_code),
                            'ShippingMethod' => (string) ($order->shipping_method_name ?: $order->shipping_method_code),
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            // Keep provider response data and credentials out of queue failure logs.
            throw new RuntimeException('Klaviyo order event failed (HTTP '.$response->status().').');
        }
    }
}
