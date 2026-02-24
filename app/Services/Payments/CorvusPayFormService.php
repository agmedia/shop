<?php

namespace App\Services\Payments;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\Sales\Order\Order;
use App\Models\Sales\Order\OrderHistory;
use App\Models\Sales\Order\OrderTransaction;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\Local\PaymentMethod;
use Illuminate\Support\Facades\DB;

class CorvusPayFormService
{
    public const PAYLOAD_KEY = 'corvuspay';
    public const MODE_TEST = 'test';
    public const MODE_LIVE = 'live';
    public const FORM_URL_TEST = 'https://wallet.test.corvuspay.com/checkout/';
    public const FORM_URL_LIVE = 'https://wallet.corvuspay.com/checkout/';
    public const API_VERSION = '1.6';

    public function isCorvusCode(string $code): bool
    {
        return in_array(strtolower(trim($code)), ['corvus', 'corvuspay', 'corvus_pay'], true);
    }

    public function isCorvusOrder(Order $order): bool
    {
        return $this->isCorvusCode((string) $order->payment_method_code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildFormData(Order $order): ?array
    {
        if (! $this->isCorvusOrder($order)) {
            return null;
        }

        $settings = $this->resolveMethodSettings((string) $order->payment_method_code);
        $mode = strtolower(trim((string) ($settings['corvus_mode'] ?? self::MODE_TEST)));
        $formUrl = $this->resolveFormUrl($mode);
        $storeId = trim((string) ($settings['corvus_store_id'] ?? ''));
        $secret = trim((string) ($settings['corvus_secret_key'] ?? ''));

        if ($storeId === '' || $secret === '') {
            return null;
        }

        $language = strtolower(trim((string) ($settings['corvus_language'] ?? app()->getLocale() ?? 'hr')));
        if (! in_array($language, ['hr', 'en', 'it', 'de', 'rs', 'sl', 'mk', 'sq'], true)) {
            $language = 'hr';
        }

        $currency = strtoupper(trim((string) ($settings['corvus_currency'] ?? $order->currency_code ?? 'EUR')));
        if ($currency === '') {
            $currency = 'EUR';
        }

        $requireComplete = filter_var((string) ($settings['corvus_require_complete'] ?? 'false'), FILTER_VALIDATE_BOOL)
            ? 'true'
            : 'false';

        $payload = [
            'amount' => number_format(max(0, (float) $order->grand_total), 2, '.', ''),
            'cardholder_address' => $this->limit((string) ($order->billing_address_line_1 ?: $order->shipping_address_line_1 ?: ''), 100),
            'cardholder_city' => $this->limit((string) ($order->billing_city ?: $order->shipping_city ?: ''), 20),
            'cardholder_country' => $this->limit((string) ($order->billing_country_code ?: $order->shipping_country_code ?: 'HR'), 30),
            'cardholder_country_code' => strtoupper($this->limit((string) ($order->billing_country_code ?: $order->shipping_country_code ?: 'HR'), 2)),
            'cardholder_email' => $this->limit((string) ($order->customer_email ?: ''), 100),
            'cardholder_name' => $this->limit((string) ($order->billing_first_name ?: $order->shipping_first_name ?: ''), 40),
            'cardholder_surname' => $this->limit((string) ($order->billing_last_name ?: $order->shipping_last_name ?: ''), 40),
            'cardholder_zip_code' => $this->limit((string) ($order->billing_postal_code ?: $order->shipping_postal_code ?: ''), 9),
            'cancel_url' => route('checkout.corvus.cancel.static'),
            'cart' => $this->limit($this->buildCartDescription($order), 255),
            'currency' => $currency,
            'language' => $language,
            'order_number' => (string) $order->order_number,
            'require_complete' => $requireComplete,
            'store_id' => $storeId,
            'success_url' => route('checkout.corvus.success.static'),
            'version' => self::API_VERSION,
        ];

        $payload['signature'] = $this->signatureForPost($payload, $secret);

        $metaPayload = is_array($order->payload) ? $order->payload : [];
        $existing = is_array($metaPayload[self::PAYLOAD_KEY] ?? null) ? $metaPayload[self::PAYLOAD_KEY] : [];
        $metaPayload[self::PAYLOAD_KEY] = array_merge($existing, [
            'request' => [
                'mode' => $mode,
                'form_url' => $formUrl,
                'store_id' => $storeId,
                'order_number' => (string) $order->order_number,
                'amount' => $payload['amount'],
                'currency' => $currency,
                'language' => $language,
                'require_complete' => $requireComplete,
                'prepared_at' => now()->toIso8601String(),
            ],
        ]);
        $order->forceFill(['payload' => $metaPayload])->save();

        return [
            'action_url' => $formUrl,
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{paid:bool,status:string,signature_valid:bool,message:string}
     */
    public function handleCallback(Order $order, array $input, string $context): array
    {
        if (! $this->isCorvusOrder($order)) {
            return [
                'paid' => false,
                'status' => 'ignored',
                'signature_valid' => false,
                'message' => 'Order is not CorvusPay.',
            ];
        }

        $settings = $this->resolveMethodSettings((string) $order->payment_method_code);
        $secret = trim((string) ($settings['corvus_secret_key'] ?? ''));
        $approvalCode = trim((string) ($input['approval_code'] ?? $input['approvalCode'] ?? ''));
        $language = trim((string) ($input['language'] ?? ''));
        $orderNumber = trim((string) ($input['order_number'] ?? ''));
        $signature = trim((string) ($input['signature'] ?? ''));

        $signatureValid = false;
        if ($secret !== '' && $signature !== '' && $orderNumber !== '') {
            $signedFields = [
                'approval_code' => $approvalCode,
                'language' => $language,
                'order_number' => $orderNumber,
            ];
            $expected = $this->signatureForPost($signedFields, $secret);
            $signatureValid = hash_equals(strtolower($expected), strtolower($signature));
        }

        $isPaid = strtolower($context) === 'success'
            && $signatureValid
            && $orderNumber === (string) $order->order_number
            && $approvalCode !== '';

        $status = $isPaid ? 'approved' : match (strtolower($context)) {
            'cancel' => 'cancelled',
            default => 'declined',
        };
        if (! $signatureValid && strtolower($context) === 'success') {
            $status = 'invalid_signature';
        }

        DB::transaction(function () use ($order, $isPaid, $status, $signatureValid, $input, $approvalCode, $context): void {
            OrderTransaction::query()->create([
                'order_id' => $order->id,
                'provider' => 'corvuspay',
                'transaction_ref' => $approvalCode !== '' ? $approvalCode : (string) ($input['order_number'] ?? ''),
                'status' => $status,
                'amount' => (float) $order->grand_total,
                'currency_code' => (string) ($order->currency_code ?: 'EUR'),
                'processed_at' => now(),
                'payload' => [
                    'context' => $context,
                    'signature_valid' => $signatureValid,
                    'raw' => $input,
                ],
                'created_by' => $order->user_id,
            ]);

            $payload = is_array($order->payload) ? $order->payload : [];
            $existing = is_array($payload[self::PAYLOAD_KEY] ?? null) ? $payload[self::PAYLOAD_KEY] : [];
            $callbacks = is_array($existing['callbacks'] ?? null) ? $existing['callbacks'] : [];
            $callbacks[] = [
                'at' => now()->toIso8601String(),
                'context' => $context,
                'status' => $status,
                'signature_valid' => $signatureValid,
                'approval_code' => $approvalCode,
            ];

            $payload[self::PAYLOAD_KEY] = array_merge($existing, [
                'status' => $status,
                'signature_valid' => $signatureValid,
                'approval_code' => $approvalCode,
                'callbacks' => $callbacks,
            ]);

            $beforeStatusId = (int) $order->status_id;
            if ($isPaid) {
                $paidStatus = $this->resolvePaidStatus();
                if ($paidStatus && (int) $order->status_id !== (int) $paidStatus->id) {
                    $order->status_id = (int) $paidStatus->id;
                    OrderHistory::query()->create([
                        'order_id' => $order->id,
                        'from_status_id' => $beforeStatusId > 0 ? $beforeStatusId : null,
                        'to_status_id' => (int) $paidStatus->id,
                        'changed_by' => $order->user_id,
                        'comment' => 'CorvusPay callback: payment approved.',
                    ]);
                }
                if (! $order->paid_at) {
                    $order->paid_at = now();
                }
            }

            $order->payload = $payload;
            $order->save();
        });

        return [
            'paid' => $isPaid,
            'status' => $status,
            'signature_valid' => $signatureValid,
            'message' => $isPaid ? 'Payment approved.' : 'Payment was not approved.',
        ];
    }

    public function handleCancellationEffects(Order $order): void
    {
        if (! $this->isCorvusOrder($order)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['items'])
                ->firstOrFail();

            $payload = is_array($locked->payload) ? $locked->payload : [];
            $existing = is_array($payload[self::PAYLOAD_KEY] ?? null) ? $payload[self::PAYLOAD_KEY] : [];
            if (! empty($existing['cancel_restocked_at'])) {
                return;
            }

            foreach ($locked->items as $item) {
                $qty = max(0, (int) $item->quantity);
                if ($qty <= 0) {
                    continue;
                }

                $optionValueId = (int) ($item->product_option_value_id ?? 0);
                if ($optionValueId > 0) {
                    $optionRow = ProductOptionValue::query()->lockForUpdate()->find($optionValueId);
                    if ($optionRow) {
                        $optionRow->stock_qty = max(0, (int) $optionRow->stock_qty) + $qty;
                        $optionRow->save();
                    }
                    continue;
                }

                $productId = (int) ($item->product_id ?? 0);
                if ($productId > 0) {
                    $product = Product::query()->lockForUpdate()->find($productId);
                    if ($product) {
                        $product->stock_qty = max(0, (int) $product->stock_qty) + $qty;
                        $product->save();
                    }
                }
            }

            $beforeStatusId = (int) $locked->status_id;
            $cancelledStatus = $this->resolveCancelledStatus();
            if ($cancelledStatus && (int) $locked->status_id !== (int) $cancelledStatus->id) {
                $locked->status_id = (int) $cancelledStatus->id;
                OrderHistory::query()->create([
                    'order_id' => $locked->id,
                    'from_status_id' => $beforeStatusId > 0 ? $beforeStatusId : null,
                    'to_status_id' => (int) $cancelledStatus->id,
                    'changed_by' => $locked->user_id,
                    'comment' => 'CorvusPay callback: payment cancelled, stock restored.',
                ]);
            }

            $payload[self::PAYLOAD_KEY] = array_merge($existing, [
                'cancel_restocked_at' => now()->toIso8601String(),
            ]);
            $locked->paid_at = null;
            $locked->payload = $payload;
            $locked->save();
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function signatureForPost(array $fields, string $secret): string
    {
        unset($fields['signature']);
        ksort($fields, SORT_STRING);

        $message = '';
        foreach ($fields as $key => $value) {
            $message .= (string) $key.(string) $value;
        }

        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMethodSettings(string $methodCode): array
    {
        $method = PaymentMethod::query()->where('code', $methodCode)->first();

        return is_array($method?->settings) ? $method->settings : [];
    }

    private function resolveFormUrl(string $mode): string
    {
        return $mode === self::MODE_LIVE ? self::FORM_URL_LIVE : self::FORM_URL_TEST;
    }

    private function resolvePaidStatus(): ?OrderStatus
    {
        return OrderStatus::query()
            ->where('is_active', true)
            ->where('is_paid', true)
            ->where('is_cancelled', false)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function resolveCancelledStatus(): ?OrderStatus
    {
        return OrderStatus::query()
            ->where('is_active', true)
            ->where('is_cancelled', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function buildCartDescription(Order $order): string
    {
        $order->loadMissing('items');

        return $order->items
            ->map(static fn ($item): string => trim((string) $item->name).' x'.(int) $item->quantity)
            ->implode(', ');
    }

    private function limit(string $value, int $max): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return mb_substr($trimmed, 0, $max);
    }
}
