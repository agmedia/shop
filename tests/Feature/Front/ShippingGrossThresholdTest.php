<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Settings\Local\GeoZone;
use App\Models\Settings\Local\GeoZoneCountry;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Services\Front\CartService;
use App\Services\Front\CheckoutService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShippingGrossThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', false);
        TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
        $hr = $this->zone('HR', ['HR']);
        $eu = $this->zone('EU', ['DE', 'AT', 'FR']);
        ShippingMethod::query()->create([
            'code' => 'standard', 'name' => 'DPD Croatia', 'geo_zone_id' => $hr->id,
            'price' => 4.99, 'free_over' => 80, 'is_active' => true,
            'settings' => ['existing_option' => true],
        ]);
        ShippingMethod::query()->create([
            'code' => 'standard_eu', 'name' => 'International delivery', 'geo_zone_id' => $eu->id,
            'price' => 9.99, 'free_over' => 120, 'is_active' => false,
            'settings' => ['existing_option' => 'preserved'],
        ]);
        PaymentMethod::query()->create([
            'code' => 'bank', 'name' => 'Bank', 'fee_type' => 'fixed', 'fee_value' => 0,
            'is_active' => true,
        ]);
    }

    #[DataProvider('boundaryOrders')]
    public function test_destination_gross_boundary_agrees_in_preview_and_saved_order(
        string $country,
        float $netPrice,
        bool $addCent,
        float $goodsGross,
        float $shippingGross
    ): void {
        $this->artisan('storefront:activate-de-at-shipping')->assertSuccessful();
        $this->addProduct($netPrice);
        if ($addCent) {
            $this->addProduct(0.01);
        }
        $summary = app(CartService::class)->summary(countryCode: $country);
        $this->assertSame($goodsGross, $summary['grand_total']);
        $this->assertSame($country === 'DE' ? 19.0 : 20.0, (float) $summary['tax_rate']);

        $preview = $this->preview($country);
        $this->assertSame(round($goodsGross + $shippingGross, 2), (float) $preview['grand_total']);
        $this->assertSame('standard_eu', $preview['shipping_method_code']);
        $this->assertSame($shippingGross === 0.0 ? 0.0 : ($country === 'DE' ? 8.4 : 8.33), (float) $preview['shipping_total']);

        $order = $this->placeOrder($country, $preview);
        $this->assertEquals($shippingGross, data_get($order->payload, 'pricing.shipping_gross'));
        $this->assertEquals($preview['grand_total'], $order->grand_total);
        $this->assertEquals($preview['tax_total'], $order->tax_total);
        $this->assertEquals($preview['shipping_total'], $order->shipping_total);
    }

    public static function boundaryOrders(): array
    {
        return [
            'DE 79.99' => ['DE', 67.22, false, 79.99, 10.0],
            'DE 80.00' => ['DE', 67.23, false, 80.0, 0.0],
            'DE 80.01' => ['DE', 67.23, true, 80.01, 0.0],
            'AT 79.99' => ['AT', 66.66, false, 79.99, 10.0],
            'AT 80.00' => ['AT', 66.67, false, 80.0, 0.0],
            'AT 80.01' => ['AT', 66.67, true, 80.01, 0.0],
        ];
    }

    #[DataProvider('discountedOrders')]
    public function test_threshold_uses_discounted_goods_and_excludes_delivery_and_payment_fees(string $country, string $scope): void
    {
        $this->artisan('storefront:activate-de-at-shipping')->assertSuccessful();
        $this->addProduct(100);
        $this->assertSame(0.0, (float) $this->preview($country)['shipping_total']);

        CatalogAction::query()->create([
            'code' => 'discount', 'scope' => $scope,
            'type' => CatalogAction::TYPE_PERCENTAGE, 'discount_value' => 40,
            'target_type' => CatalogAction::TARGET_ALL, 'audience_type' => CatalogAction::AUDIENCE_ALL,
            'coupon_code' => 'BALI40', 'is_active' => true,
        ]);
        $this->assertTrue(app(CartService::class)->applyCoupon('BALI40'));
        PaymentMethod::query()->where('code', 'bank')->update(['fee_value' => 30]);
        $goodsGross = $country === 'DE' ? 71.4 : 72.0;
        $this->assertSame($goodsGross, app(CartService::class)->summary(countryCode: $country)['grand_total']);

        $preview = $this->preview($country);
        $this->assertSame(round($goodsGross + 10 + 30, 2), (float) $preview['grand_total']);
        $order = $this->placeOrder($country, $preview);
        $this->assertEquals(10, data_get($order->payload, 'pricing.shipping_gross'));
        $this->assertEquals(30, data_get($order->payload, 'pricing.payment_fee_gross'));
        $this->assertEquals($preview['grand_total'], $order->grand_total);
        $this->assertEquals($preview['shipping_total'], $order->shipping_total);
        $this->assertEquals($preview['tax_total'], $order->tax_total);
    }

    public static function discountedOrders(): array
    {
        return [
            'DE cart coupon' => ['DE', CatalogAction::SCOPE_CART],
            'AT cart coupon' => ['AT', CatalogAction::SCOPE_CART],
            'DE product coupon' => ['DE', CatalogAction::SCOPE_PRODUCT],
            'AT product coupon' => ['AT', CatalogAction::SCOPE_PRODUCT],
        ];
    }

    public function test_croatian_method_retains_existing_net_threshold(): void
    {
        $this->artisan('storefront:activate-de-at-shipping')->assertSuccessful();
        $this->addProduct(67.23);
        $this->assertGreaterThan(80, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);

        $preview = $this->preview('HR', 'standard');
        $this->assertSame(4.99, (float) $preview['shipping_total']);
        $order = $this->placeOrder('HR', $preview, 'standard');
        $this->assertEquals(4.99, $order->shipping_total);
        $this->assertEquals($preview['grand_total'], $order->grand_total);
    }

    public function test_activation_is_idempotent_and_preserves_hr_other_methods_and_system_settings(): void
    {
        $hrBefore = ShippingMethod::query()->where('code', 'standard')->firstOrFail()->getAttributes();
        $euBefore = GeoZone::query()->where('code', 'EU')->firstOrFail()->getAttributes();
        $settingsBefore = app(SystemSettingsService::class)->all();
        $this->artisan('storefront:activate-de-at-shipping')->assertSuccessful();
        $activatedBefore = ShippingMethod::query()->where('code', 'standard_eu')->firstOrFail()->getAttributes();
        $this->artisan('storefront:activate-de-at-shipping')->assertSuccessful();

        $this->assertSame($hrBefore, ShippingMethod::query()->where('code', 'standard')->firstOrFail()->getAttributes());
        $this->assertSame($euBefore, GeoZone::query()->where('code', 'EU')->firstOrFail()->getAttributes());
        $this->assertSame($settingsBefore, app(SystemSettingsService::class)->all());
        $method = ShippingMethod::query()->where('code', 'standard_eu')->firstOrFail();
        $this->assertSame($activatedBefore, $method->getAttributes());
        $this->assertSame('International delivery', $method->name);
        $this->assertSame('10.00', $method->price);
        $this->assertSame('80.00', $method->free_over);
        $this->assertTrue($method->is_active);
        $this->assertSame(['existing_option' => 'preserved', 'free_over_basis' => 'gross'], $method->settings);
        $this->assertSame('DE_AT', $method->geoZone->code);
        $this->assertEqualsCanonicalizing(['DE', 'AT'], $method->geoZone->countries->pluck('country_code')->all());
        $this->assertDatabaseCount('geo_zones', 3);
        $this->assertDatabaseCount('geo_zone_countries', 6);
        $this->assertEmpty(app(CheckoutService::class)->availableShippingMethods(100, 'FR'));
        $this->assertSame(['standard_eu'], app(CheckoutService::class)->availableShippingMethods(100, 'DE')->pluck('code')->all());
        $this->assertSame(['standard_eu'], app(CheckoutService::class)->availableShippingMethods(100, 'AT')->pluck('code')->all());
        $this->assertSame(['standard'], app(CheckoutService::class)->availableShippingMethods(100, 'HR')->pluck('code')->all());
    }

    public function test_activation_does_not_create_a_missing_shipping_method_or_change_zones(): void
    {
        ShippingMethod::query()->where('code', 'standard_eu')->delete();
        $this->artisan('storefront:activate-de-at-shipping')->assertFailed();

        $this->assertDatabaseCount('shipping_methods', 1);
        $this->assertDatabaseCount('geo_zones', 2);
        $this->assertDatabaseCount('geo_zone_countries', 4);
        $this->assertDatabaseHas('shipping_methods', ['code' => 'standard', 'price' => 4.99, 'free_over' => 80]);
    }

    public function test_activation_rejects_an_existing_destination_zone_with_other_countries(): void
    {
        $this->zone('DE_AT', ['DE', 'FR']);
        $methodBefore = ShippingMethod::query()->where('code', 'standard_eu')->firstOrFail()->getAttributes();
        $this->artisan('storefront:activate-de-at-shipping')->assertFailed();

        $this->assertSame($methodBefore, ShippingMethod::query()->where('code', 'standard_eu')->firstOrFail()->getAttributes());
        $this->assertDatabaseCount('geo_zone_countries', 6);
        $this->assertDatabaseMissing('geo_zone_countries', [
            'geo_zone_id' => GeoZone::query()->where('code', 'DE_AT')->value('id'), 'country_code' => 'AT',
        ]);
    }

    private function zone(string $code, array $countries): GeoZone
    {
        $zone = GeoZone::query()->create(['code' => $code, 'name' => $code, 'is_active' => true]);
        foreach ($countries as $country) {
            GeoZoneCountry::query()->create(['geo_zone_id' => $zone->id, 'country_code' => $country]);
        }

        return $zone;
    }

    private function addProduct(float $netPrice): void
    {
        $code = 'threshold-'.(Product::query()->count() + 1);
        $product = Product::query()->create([
            'code' => $code, 'sku' => $code, 'base_price' => $netPrice,
            'stock_qty' => 5, 'is_active' => true, 'tax_rate_id' => TaxRate::query()->where('code', 'VAT25')->value('id'),
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => 'en', 'name' => $code, 'slug' => $code,
        ]);
        app(CartService::class)->add($product);
    }

    private function preview(string $country, string $shippingMethod = 'standard_eu'): array
    {
        return $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => $country, 'shipping_country_code' => $country,
            'shipping_method_code' => $shippingMethod, 'payment_method_code' => 'bank',
        ]))->assertOk()->json('totals');
    }

    private function placeOrder(string $country, array $preview, string $shippingMethod = 'standard_eu'): \App\Models\Sales\Order\Order
    {
        return app(CheckoutService::class)->placeOrder([
            'customer_email' => 'threshold@example.test',
            'billing_country_code' => $country, 'shipping_country_code' => $country,
            'shipping_method_code' => $shippingMethod, 'payment_method_code' => 'bank',
            'quoted_total' => $preview['grand_total'],
        ]);
    }
}
