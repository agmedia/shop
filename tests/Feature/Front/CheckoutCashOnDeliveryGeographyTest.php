<?php

namespace Tests\Feature\Front;

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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutCashOnDeliveryGeographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', true);

        $hr = GeoZone::query()->create(['code' => 'hr', 'name' => 'Croatia', 'is_active' => true]);
        $eu = GeoZone::query()->create(['code' => 'de-at', 'name' => 'Germany and Austria', 'is_active' => true]);
        foreach (['HR' => $hr, 'DE' => $eu, 'AT' => $eu] as $country => $zone) {
            GeoZoneCountry::query()->create(['geo_zone_id' => $zone->id, 'country_code' => $country]);
        }
        foreach (['standard' => [$hr, 5], 'standard_eu' => [$eu, 10], 'pickup' => [$hr, 0]] as $code => [$zone, $price]) {
            ShippingMethod::query()->create([
                'code' => $code, 'name' => $code, 'geo_zone_id' => $zone->id,
                'price' => $price, 'is_active' => true,
            ]);
        }
        foreach (['cod', 'cash_on_delivery', 'bank', 'corvuspay'] as $sort => $code) {
            PaymentMethod::query()->create([
                'code' => $code, 'name' => $code,
                'geo_zone_id' => $sort < 2 ? $hr->id : null,
                'is_active' => true, 'sort_order' => $sort,
            ]);
        }
        $tax = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25, 'rate_type' => 'percent',
            'is_default' => true, 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'COD-GEO', 'sku' => 'COD-GEO', 'base_price' => 25, 'stock_qty' => 10,
            'is_active' => true, 'tax_rate_id' => $tax->id,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => 'hr', 'name' => 'Test product', 'slug' => 'cod-geo',
        ]);
        app(CartService::class)->add($product);
    }

    #[DataProvider('destinations')]
    public function test_croatian_billing_cannot_offer_cash_for_german_or_austrian_delivery(string $country): void
    {
        $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'ship_to_different_address' => 1, 'shipping_method_code' => 'standard_eu',
            'payment_method_code' => 'cod',
        ]))->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['bank', 'corvuspay'])
            ->assertJsonPath('totals.shipping_method_code', 'standard_eu')
            ->assertJsonPath('totals.payment_method_code', 'bank');
    }

    #[DataProvider('cashSubmissions')]
    public function test_forged_checkout_submission_rejects_cash_outside_its_delivery_zone(string $country, string $payment): void
    {
        $this->postJson('/checkout', $this->payload($country, $payment))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method_code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    #[DataProvider('destinations')]
    public function test_direct_order_service_also_rejects_cash_outside_its_delivery_zone(string $country): void
    {
        try {
            app(CheckoutService::class)->placeOrder($this->payload($country, 'cod'));
            $this->fail('Cash on delivery must respect the delivery address geo zone.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method_code', $exception->errors());
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_croatian_delivery_keeps_cash_in_options_and_accepts_it_when_ordered(): void
    {
        $this->getJson('/checkout/options?billing_country_code=HR&shipping_method_code=standard&payment_method_code=cod')
            ->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['cod', 'cash_on_delivery', 'bank', 'corvuspay'])
            ->assertJsonPath('totals.payment_method_code', 'cod');

        $order = app(CheckoutService::class)->placeOrder($this->payload('HR', 'cod'));
        $this->assertSame('cod', $order->payment_method_code);
        $this->assertSame('HR', $order->shipping_country_code);
    }

    public function test_pickup_keeps_its_existing_prepaid_payment_filter(): void
    {
        $this->getJson('/checkout/options?billing_country_code=HR&shipping_method_code=pickup&payment_method_code=cod')
            ->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['bank', 'corvuspay'])
            ->assertJsonPath('totals.payment_method_code', 'bank');
    }

    public function test_cash_provider_alias_uses_delivery_zone_while_card_restrictions_still_use_billing_zone(): void
    {
        PaymentMethod::query()->where('code', 'cash_on_delivery')->update(['code' => 'custom_cash', 'provider' => 'cash_on_delivery']);
        PaymentMethod::query()->where('code', 'corvuspay')->update(['geo_zone_id' => GeoZone::query()->where('code', 'de-at')->value('id')]);

        $this->getJson('/checkout/options?billing_country_code=HR&shipping_country_code=DE&ship_to_different_address=1&shipping_method_code=standard_eu')
            ->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['bank']);

        $this->getJson('/checkout/options?billing_country_code=DE&shipping_country_code=HR&ship_to_different_address=1&shipping_method_code=standard')
            ->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['cod', 'custom_cash', 'bank', 'corvuspay']);
    }

    #[DataProvider('destinations')]
    public function test_checkout_rerender_uses_the_separate_delivery_address_for_cash_availability(string $country): void
    {
        $this->withSession(['_old_input' => [
            'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'ship_to_different_address' => '1', 'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'cod',
        ]])->get('/checkout')->assertOk()
            ->assertViewHas('paymentMethods', fn ($methods) => $methods->pluck('code')->all() === ['bank', 'corvuspay'])
            ->assertViewHas('checkoutTotals', fn ($totals) => $totals['payment_method_code'] === 'bank');
    }

    public static function destinations(): array
    {
        return [['DE'], ['AT']];
    }

    public static function cashSubmissions(): array
    {
        return [['DE', 'cod'], ['AT', 'cod'], ['DE', 'cash_on_delivery'], ['AT', 'cash_on_delivery']];
    }

    private function payload(string $country, string $payment): array
    {
        return [
            'customer_email' => 'cod-geo@example.test', 'customer_phone' => '+38591000002',
            'billing_first_name' => 'Test', 'billing_last_name' => 'Customer',
            'billing_address_line_1' => 'Test address', 'billing_postal_code' => '10000',
            'billing_city' => 'Zagreb', 'billing_state' => 'Grad Zagreb', 'billing_country_code' => 'HR',
            'ship_to_different_address' => '1', 'use_billing_for_shipping' => '0',
            'shipping_first_name' => 'Test', 'shipping_last_name' => 'Customer',
            'shipping_address_line_1' => 'Test address', 'shipping_postal_code' => $country === 'DE' ? '10115' : '1010',
            'shipping_city' => $country === 'DE' ? 'Berlin' : 'Wien', 'shipping_country_code' => $country,
            'shipping_method_code' => $country === 'HR' ? 'standard' : 'standard_eu',
            'payment_method_code' => $payment, 'accept_terms' => '1',
        ];
    }
}
