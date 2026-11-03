<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Services\Front\CartService;
use App\Services\Front\CheckoutService;
use App\Services\Payments\CorvusPayFormService;
use App\Services\Pricing\ProductPricePresentationService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DestinationGrossPriceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.locale' => 'de', 'app.fallback_locale' => 'en']);
        app()->setLocale('de');
        app(SystemSettingsService::class)->putMany([
            'store_pricing_prices_include_tax' => true, 'catalog_use_mobile_view' => true,
        ]);
        $tax = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
        $this->product = Product::query()->create([
            'code' => 'CENT-GROSS', 'sku' => 'CENT-GROSS', 'base_price' => 9.99,
            'stock_qty' => 30, 'is_active' => true, 'tax_rate_id' => $tax->id,
        ]);
        foreach (['hr', 'en', 'de'] as $locale) {
            $this->product->translations()->create(['locale' => $locale, 'name' => 'Cent-priced product', 'slug' => 'cent-priced-product-'.$locale]);
        }
        ShippingMethod::query()->create([
            'code' => 'standard_eu', 'name' => 'Delivery', 'price' => 10, 'free_over' => 80,
            'settings' => ['free_over_basis' => 'gross'], 'is_active' => true,
        ]);
        PaymentMethod::query()->create([
            'code' => 'corvuspay', 'name' => 'Card', 'is_active' => true,
            'settings' => ['corvus_store_id' => 'test-store', 'corvus_secret_key' => 'test-key-only', 'corvus_mode' => 'test'],
        ]);
    }

    #[DataProvider('destinationQuantities')]
    public function test_inclusive_cent_price_is_consistent_through_quote_order_and_card_form(
        string $country, int $quantity, float $netUnit, float $unitTax, float $shippingNet, float $shippingTax
    ): void {
        $cart = app(CartService::class);
        $cart->add($this->product, $quantity);
        $goodsGross = round(9.99 * $quantity, 2);
        $grandTotal = round($goodsGross + 10, 2);
        $taxTotal = round($unitTax * $quantity + $shippingTax, 2);
        $line = $cart->lines(countryCode: $country)->first();
        $this->assertSame(9.99, app(ProductPricePresentationService::class)->forProduct($this->product)['current_gross']);
        $this->assertSame(9.99, $line['display_unit_price']);
        $this->assertSame($netUnit, $line['unit_price']);
        $this->assertSame(round($unitTax * $quantity, 2), $line['line_tax_total']);
        $this->assertSame($goodsGross, $line['display_line_total']);
        $this->assertSame($goodsGross, $cart->summary(countryCode: $country)['grand_total']);

        $quote = $this->quote($country)->assertJsonPath('display_pricing.subtotal', $goodsGross)
            ->assertJsonPath('display_pricing.shipping_total', 10)
            ->assertJsonPath('display_pricing.grand_total', $grandTotal)
            ->assertJsonPath('totals.tax_total', fn ($value): bool => (float) $value === $taxTotal);
        $this->assertSame($shippingNet, (float) $quote->json('totals.shipping_total'));
        $order = $this->order($country, $grandTotal);
        $this->assertEquals($grandTotal, $order->grand_total);
        $this->assertEquals($taxTotal, $order->tax_total);
        $this->assertEquals($shippingNet, $order->shipping_total);
        $this->assertEquals($netUnit, $order->items->first()->unit_price);
        $this->assertEquals(round($unitTax * $quantity, 2), $order->items->first()->tax_amount);
        $this->assertEquals(9.99, data_get($order->items->first()->payload, 'pricing.unit_gross'));
        $this->assertEquals($goodsGross, data_get($order->items->first()->payload, 'pricing.line_gross'));
        $form = app(CorvusPayFormService::class)->buildFormData($order);
        $this->assertSame(number_format($grandTotal, 2, '.', ''), $form['payload']['amount']);
        $this->assertSame('EUR', $form['payload']['currency']);
        if ($quantity === 1) {
            $this->assertSame('19.99', $form['payload']['amount']);
        }
    }

    #[DataProvider('devices')]
    public function test_pdp_and_initial_german_checkout_both_show_the_same_9_99_price(string $userAgent): void
    {
        app(CartService::class)->add($this->product);
        $pdp = $this->withHeader('User-Agent', $userAgent)->get('/product/cent-priced-product-de')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$pdp->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(\App\Support\Currency::format(9.99), trim($xpath->evaluate('string(//*[@data-product-price-current])')));

        $this->get('/checkout')->assertOk()
            ->assertViewHas('displayPricing', fn ($pricing) => $pricing['subtotal'] === 9.99 && $pricing['grand_total'] === 19.99);
    }

    #[DataProvider('grossThresholds')]
    public function test_gross_storage_prices_cross_the_free_shipping_threshold_without_repricing(string $country, float $goods, float $shipping): void
    {
        $this->product->update(['base_price' => $goods]);
        app(CartService::class)->add($this->product);
        $total = round($goods + $shipping, 2);
        $quote = $this->quote($country)->assertJsonPath('display_pricing.subtotal', fn ($value): bool => (float) $value === $goods)
            ->assertJsonPath('display_pricing.shipping_total', fn ($value): bool => (float) $value === $shipping)
            ->assertJsonPath('display_pricing.grand_total', fn ($value): bool => (float) $value === $total);
        $order = $this->order($country, $total);
        $this->assertEquals($total, $order->grand_total);
        $this->assertEquals($shipping, data_get($order->payload, 'pricing.shipping_gross'));
        $this->assertEquals($quote->json('totals.tax_total'), $order->tax_total);
    }

    public static function destinationQuantities(): array
    {
        $cases = [['HR', 1, 7.99, 2.0, 10.0, 0.0]];
        foreach ([1, 2, 3] as $quantity) {
            $cases[] = ['DE', $quantity, 8.39, 1.6, 8.4, 1.6];
            $cases[] = ['AT', $quantity, 8.33, 1.66, 8.33, 1.67];
        }

        return $cases;
    }

    public static function devices(): array
    {
        return [['Mozilla/5.0 (Macintosh; Intel Mac OS X)'], ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)']];
    }

    public static function grossThresholds(): array
    {
        $cases = [];
        foreach (['DE', 'AT'] as $country) {
            $cases[] = [$country, 79.99, 10.0];
            $cases[] = [$country, 80.0, 0.0];
            $cases[] = [$country, 80.01, 0.0];
        }

        return $cases;
    }

    private function quote(string $country): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'ship_to_different_address' => 1, 'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'corvuspay',
        ]))->assertOk();
    }

    private function order(string $country, float $total): \App\Models\Sales\Order\Order
    {
        return app(CheckoutService::class)->placeOrder([
            'customer_email' => 'cent-price@example.test', 'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'corvuspay', 'quoted_total' => $total,
        ]);
    }
}
