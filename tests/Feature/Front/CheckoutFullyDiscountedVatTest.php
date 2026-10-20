<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
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

class CheckoutFullyDiscountedVatTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('fullyDiscountedOrders')]
    public function test_full_discount_keeps_destination_vat_on_paid_delivery_and_fees(
        string $country,
        string $discountScope,
        float $expectedShippingNet,
        float $expectedPaymentFeeNet,
        float $expectedTax
    ): void {
        Queue::fake();
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', true);
        $rate = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'GIFT', 'sku' => 'GIFT-1', 'base_price' => 125,
            'stock_qty' => 5, 'is_active' => true, 'tax_rate_id' => $rate->id,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => 'en', 'name' => 'Gift', 'slug' => 'gift',
        ]);
        ShippingMethod::query()->create([
            'code' => 'standard', 'name' => 'Standard', 'price' => 5, 'is_active' => true,
        ]);
        PaymentMethod::query()->create([
            'code' => 'bank', 'name' => 'Bank', 'fee_type' => 'fixed', 'fee_value' => 2, 'is_active' => true,
        ]);
        CatalogAction::query()->create([
            'code' => 'full-discount', 'scope' => $discountScope,
            'type' => CatalogAction::TYPE_PERCENTAGE, 'discount_value' => 100,
            'target_type' => CatalogAction::TARGET_ALL, 'audience_type' => CatalogAction::AUDIENCE_ALL,
            'coupon_code' => 'FREE100', 'is_active' => true,
        ]);
        $cart = app(CartService::class);
        $cart->add($product);
        $this->assertTrue($cart->applyCoupon('FREE100'));
        $this->assertSame(0.0, $cart->summary(countryCode: $country)['subtotal_after_discount']);

        $response = $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => $country, 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank',
        ]))->assertOk()
            ->assertJsonPath('totals.shipping_total', fn ($value): bool => (float) $value === $expectedShippingNet)
            ->assertJsonPath('totals.payment_fee_total', fn ($value): bool => (float) $value === $expectedPaymentFeeNet)
            ->assertJsonPath('totals.tax_total', fn ($value): bool => (float) $value === $expectedTax)
            ->assertJsonPath('totals.grand_total', 7);

        $order = app(CheckoutService::class)->placeOrder([
            'customer_email' => 'gift@example.test',
            'billing_country_code' => $country, 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank',
            'quoted_total' => $response->json('totals.grand_total'),
        ]);
        $this->assertEquals($expectedShippingNet, $order->shipping_total);
        $this->assertEquals($expectedPaymentFeeNet, $order->payment_fee_total);
        $this->assertEquals($expectedTax, $order->tax_total);
        $this->assertEquals(7, $order->grand_total);
        $this->assertEquals(5, data_get($order->payload, 'pricing.shipping_gross'));
        $this->assertEquals(2, data_get($order->payload, 'pricing.payment_fee_gross'));
    }

    public static function fullyDiscountedOrders(): array
    {
        return [
            'German cart coupon' => ['DE', CatalogAction::SCOPE_CART, 4.2, 1.68, 1.12],
            'Austrian cart coupon' => ['AT', CatalogAction::SCOPE_CART, 4.17, 1.67, 1.16],
            'Croatian behavior unchanged' => ['HR', CatalogAction::SCOPE_CART, 5.0, 2.0, 0.0],
            'German product coupon' => ['DE', CatalogAction::SCOPE_PRODUCT, 4.2, 1.68, 1.12],
        ];
    }
}
