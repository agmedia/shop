<?php

namespace App\Services\Integrations\Kipos;

use App\Models\Sales\Order\Order;
use App\Models\Sales\Order\OrderItem;
use App\Models\Settings\Local\ShippingMethod;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Settings\SystemSettingsService;
use RuntimeException;

class KiposOrderService
{
    private const BALIDOO_SHIPPING_ITEM_CODE_FOR_TEN_EUR = 'US00000001';

    private const BALIDOO_SHIPPING_ITEM_CODE_DEFAULT = 'US00000203';

    public function __construct(
        private readonly KiposSdkService $kipos,
        private readonly SystemSettingsService $settings,
        private readonly CatalogFeatureService $catalogFeatures
    ) {}

    public function connectorEnabled(): bool
    {
        return $this->catalogFeatures->useKiposApi() && $this->kipos->enabledInSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Order $order): array
    {
        $this->assertAvailable();

        $order->loadMissing([
            'items.product',
            'items.productOptionValue',
            'totals',
        ]);

        $settings = $this->orderSettings();
        $warnings = [];
        $productItems = [];
        $extraItems = [];

        foreach ($order->items as $item) {
            $line = $this->buildProductLine($item, $warnings);
            if ($line !== null) {
                $productItems[] = $line;
            }
        }

        $shippingTotal = round((float) $order->shipping_total, 2);
        $shippingLineAmount = $shippingTotal > 0
            ? $this->resolveShippingLineAmount($order, $shippingTotal)
            : 0.0;
        if ($shippingLineAmount > 0) {
            $shippingCode = $this->resolveShippingItemCode($shippingLineAmount, $settings);
            if ($shippingCode !== '') {
                $extraItems[] = $this->extraLine($shippingCode, $shippingLineAmount);
            } else {
                $warnings[] = 'Shipping total exists, but `Kipos shipping item code` is not configured.';
            }
        }

        $paymentFeeTotal = round((float) data_get($order->payload, 'pricing.payment_fee_gross', $order->payment_fee_total), 2);
        if ($paymentFeeTotal > 0) {
            $paymentFeeCode = trim((string) ($settings['kipos_order_payment_fee_item_code'] ?? ''));
            if ($paymentFeeCode !== '') {
                $extraItems[] = $this->extraLine($paymentFeeCode, $paymentFeeTotal);
            } else {
                $warnings[] = 'Payment fee total exists, but `Kipos payment fee item code` is not configured.';
            }
        }

        $items = array_merge($extraItems, $productItems);

        if ($items === []) {
            throw new RuntimeException('Order has no sendable Kipos line items.');
        }

        $lineTotal = round((float) collect($items)->sum(fn (array $row): float => (float) ($row['IZNOS'] ?? 0)), 2);
        if (abs($lineTotal - (float) $order->grand_total) > 0.01) {
            $warnings[] = sprintf(
                'Prepared ERP total (%s) differs from order grand total (%s).',
                number_format($lineTotal, 2, '.', ''),
                number_format((float) $order->grand_total, 2, '.', '')
            );
        }

        $request = [
            'narudzba' => $this->buildOrderHeader($order, $settings, $lineTotal),
            'stavke' => $items,
        ];

        $idFirma = $this->resolvePrivateCompanyId($order, $settings, $request['narudzba']);

        return [
            'prepared_at' => now()->toIso8601String(),
            'endpoint' => $this->endpointUrl($idFirma),
            'idfirma' => $idFirma,
            'request' => $request,
            'warnings' => array_values(array_unique($warnings)),
            'line_total' => $this->formatAmount($lineTotal),
            'order_number' => (string) $order->order_number,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function sendPrepared(array $preview): array
    {
        $this->assertAvailable();

        $request = $preview['request'] ?? null;
        if (! is_array($request)) {
            throw new RuntimeException('Prepared Kipos request payload is missing.');
        }

        $idFirma = isset($preview['idfirma']) && is_numeric($preview['idfirma'])
            ? (int) $preview['idfirma']
            : null;

        $query = [];
        if ($idFirma && $idFirma > 0) {
            $query['idfirma'] = $idFirma;
        }

        $response = $this->kipos->post('narudzba/create', $request, $query);

        return array_merge($preview, [
            'sent_at' => now()->toIso8601String(),
            'response' => $response,
        ]);
    }

    private function assertAvailable(): void
    {
        if (! $this->catalogFeatures->useKiposApi()) {
            throw new RuntimeException('Kipos API module is disabled in Catalog Features.');
        }

        if (! $this->kipos->enabledInSettings()) {
            throw new RuntimeException('Kipos connector is disabled in Kipos API settings.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSettings(): array
    {
        $defaults = [
            'kipos_order_prefix' => 'KHR',
            'kipos_order_valuta' => '978',
            'kipos_order_customer_cms_id' => '1',
            'kipos_order_shipping_item_code' => '',
            'kipos_order_payment_fee_item_code' => '',
            'kipos_order_private_at_company_id' => 2,
            'kipos_order_private_de_company_id' => 3,
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->settings->get($key, $default);
        }

        return $settings;
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, string>|null
     */
    private function buildProductLine(OrderItem $item, array &$warnings): ?array
    {
        $quantity = max(0, (int) ($item->quantity ?? 0));
        if ($quantity <= 0) {
            return null;
        }

        $itemCode = trim((string) (
            data_get($item->productOptionValue?->payload, 'kipos.item_code')
            ?: $item->productOptionValue?->sku
            ?: $item->sku
            ?: data_get($item->product?->payload, 'kipos.default_item_code')
            ?: $item->code
        ));

        if ($itemCode === '') {
            $warnings[] = sprintf('Order item `%s` has no Kipos item code / SKU.', (string) $item->name);

            return null;
        }

        $netUnitPrice = round((float) $item->unit_price, 2);
        $lineBase = round($netUnitPrice * $quantity, 2);
        $discountAmount = round((float) $item->discount_amount, 2);
        $discountPercent = $lineBase > 0
            ? round(min(100.0, max(0.0, ($discountAmount / $lineBase) * 100)), 2)
            : 0.0;
        $grossUnitPrice = $this->grossProductUnitPrice($item, $quantity);
        $grossLineTotal = $this->grossProductLineTotal($item);

        return [
            'IDROBA' => $itemCode,
            'KOLICINA' => (string) $quantity,
            'CIJENA' => $this->formatAmount($grossUnitPrice),
            'RABAT' => $this->formatAmount($discountPercent),
            'IZNOS' => $this->formatAmount($grossLineTotal),
        ];
    }

    private function grossProductUnitPrice(OrderItem $item, int $quantity): float
    {
        $netUnitPrice = round(max(0.0, (float) $item->unit_price), 2);
        $taxRate = max(0.0, (float) $item->tax_rate);

        if ($taxRate > 0.0) {
            return round($netUnitPrice * (1 + ($taxRate / 100)), 2);
        }

        $taxAmount = round(max(0.0, (float) $item->tax_amount), 2);
        $netLineTotal = round(max(0.0, (float) $item->line_total), 2);
        if ($quantity > 0 && $taxAmount > 0.0 && $netLineTotal > 0.0) {
            return round($netUnitPrice * (1 + ($taxAmount / $netLineTotal)), 2);
        }

        return $netUnitPrice;
    }

    private function grossProductLineTotal(OrderItem $item): float
    {
        return round(
            max(0.0, (float) $item->line_total) + max(0.0, (float) $item->tax_amount),
            2
        );
    }

    /**
     * @return array<string, string>
     */
    private function extraLine(string $itemCode, float $amount): array
    {
        return [
            'IDROBA' => trim($itemCode),
            'KOLICINA' => '1',
            'CIJENA' => $this->formatAmount($amount),
            'RABAT' => $this->formatAmount(0),
            'IZNOS' => $this->formatAmount($amount),
        ];
    }

    private function resolveShippingLineAmount(Order $order, float $fallbackAmount): float
    {
        $recordedGross = data_get($order->payload, 'pricing.shipping_gross');
        if (is_numeric($recordedGross)) {
            return round(max(0.0, (float) $recordedGross), 2);
        }

        $shippingMethodCode = trim((string) ($order->shipping_method_code ?? ''));
        if ($shippingMethodCode === '') {
            return $fallbackAmount;
        }

        $adminPrice = ShippingMethod::query()
            ->where('code', $shippingMethodCode)
            ->value('price');

        if (! is_numeric($adminPrice)) {
            return $fallbackAmount;
        }

        return round(max(0.0, (float) $adminPrice), 2);
    }

    /**
     * Balidoo sends delivery as a Kipos service item. Keep the admin override,
     * but fall back to the same legacy mapping when it is left empty.
     *
     * @param  array<string, mixed>  $settings
     */
    private function resolveShippingItemCode(float $shippingTotal, array $settings): string
    {
        $configuredCode = strtoupper(trim((string) ($settings['kipos_order_shipping_item_code'] ?? '')));
        if ($configuredCode !== '') {
            return $configuredCode;
        }

        return abs($shippingTotal - 10.0) < 0.01
            ? self::BALIDOO_SHIPPING_ITEM_CODE_FOR_TEN_EUR
            : self::BALIDOO_SHIPPING_ITEM_CODE_DEFAULT;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function buildOrderHeader(Order $order, array $settings, float $lineTotal): array
    {
        $billingFirst = trim((string) ($order->billing_first_name ?? ''));
        $billingLast = trim((string) ($order->billing_last_name ?? ''));
        $shippingFirst = trim((string) ($order->shipping_first_name ?? ''));
        $shippingLast = trim((string) ($order->shipping_last_name ?? ''));
        $billingCountry = trim((string) ($order->billing_country_code ?: $order->shipping_country_code ?: 'HR'));
        $vatId = strtoupper(trim((string) ($order->billing_vat_id ?? '')));
        $oib = strtoupper(trim((string) ($order->billing_oib ?? '')));

        if ($oib === '' && str_starts_with($vatId, 'HR')) {
            $oib = substr($vatId, 2);
        }

        $note = trim((string) $order->customer_note);
        $notePrefix = 'WEB narudzba '.$order->order_number;
        $note = $note !== '' ? $notePrefix.' - '.$note : $notePrefix;

        return [
            'CMS_ID' => trim((string) ($settings['kipos_order_prefix'] ?? 'KHR')).$order->order_number,
            'DATUM_VRIJEME' => optional($order->placed_at ?: $order->created_at)->format('d.m.Y H:i:s') ?: now()->format('d.m.Y H:i:s'),
            'NARUCITELJ_CMS_ID' => (string) ($order->user_id ?: ($settings['kipos_order_customer_cms_id'] ?? '1')),
            'NARUCITELJ_EMAIL' => trim((string) ($order->customer_email ?? '')),
            'NARUCITELJ_IME' => $billingFirst !== '' ? $billingFirst : trim((string) $order->customer_name),
            'NARUCITELJ_PREZIME' => $billingLast,
            'NARUCITELJ_NAZIV' => trim((string) ($order->billing_company ?? '')),
            'NARUCITELJ_OIB' => $this->normalizeLocalOib($oib),
            'NARUCITELJ_VAT' => $vatId,
            'NARUCITELJ_ZEMLJA' => $billingCountry !== '' ? $billingCountry : 'HR',
            'NARUCITELJ_ADRESA' => $this->combinedAddress((string) $order->billing_address_line_1, (string) $order->billing_address_line_2),
            'NARUCITELJ_MJESTO' => trim((string) ($order->billing_city ?? '')),
            'NARUCITELJ_POSTA' => trim((string) ($order->billing_postal_code ?? '')),
            'NARUCITELJ_TELEFON' => trim((string) ($order->customer_phone ?? '')),
            'DOSTAVA_IME_PREZIME' => trim($shippingFirst.' '.$shippingLast) !== ''
                ? trim($shippingFirst.' '.$shippingLast)
                : trim((string) $order->customer_name),
            'DOSTAVA_ADRESA' => $this->combinedAddress((string) $order->shipping_address_line_1, (string) $order->shipping_address_line_2),
            'DOSTAVA_MJESTO' => trim((string) ($order->shipping_city ?? '')),
            'DOSTAVA_POSTA' => trim((string) ($order->shipping_postal_code ?? '')),
            'NAPOMENA' => $note,
            'VALUTA' => trim((string) ($settings['kipos_order_valuta'] ?? '978')),
            'IZNOS_UKUPNO' => $this->formatAmount($lineTotal),
            'TECAJ' => $this->formatAmount((float) ($order->currency_rate ?: 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $header
     */
    private function resolvePrivateCompanyId(Order $order, array $settings, array $header): ?int
    {
        $hasBusinessIdentity = trim((string) ($header['NARUCITELJ_OIB'] ?? '')) !== ''
            || trim((string) ($header['NARUCITELJ_VAT'] ?? '')) !== '';

        if ($hasBusinessIdentity) {
            return null;
        }

        $country = strtoupper(trim((string) ($order->billing_country_code ?: $order->shipping_country_code ?: '')));

        return match ($country) {
            'AT', 'AUT' => (int) ($settings['kipos_order_private_at_company_id'] ?? 2),
            'DE', 'DEU' => (int) ($settings['kipos_order_private_de_company_id'] ?? 3),
            default => null,
        };
    }

    private function endpointUrl(?int $idFirma = null): string
    {
        $settings = $this->kipos->getSettings();
        $baseUri = trim((string) ($settings['kipos_api_base_uri'] ?? ''));
        if ($baseUri === '') {
            return 'narudzba/create';
        }

        $endpoint = $baseUri.'narudzba/create';
        $suffix = trim((string) ($settings['kipos_api_query_suffix'] ?? ''));
        $extra = [];
        if ($idFirma && $idFirma > 0) {
            $extra[] = 'idfirma='.$idFirma;
        }
        if ($suffix !== '') {
            $extra[] = ltrim($suffix, '?&');
        }

        if ($extra !== []) {
            $endpoint .= '&'.implode('&', $extra);
        }

        return $endpoint;
    }

    private function normalizeLocalOib(string $oib): string
    {
        $oib = strtoupper(trim($oib));

        return str_starts_with($oib, 'HR') ? substr($oib, 2) : $oib;
    }

    private function combinedAddress(string $line1, string $line2): string
    {
        return trim(collect([$line1, $line2])->map(fn ($row) => trim((string) $row))->filter()->implode(', '));
    }

    private function formatAmount(float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
