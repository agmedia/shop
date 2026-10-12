<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Services\Front\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutPickupPaymentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        ShippingMethod::query()->create([
            'code' => 'standard',
            'name' => 'DPD delivery',
            'price' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ShippingMethod::query()->create([
            'code' => 'pickup',
            'name' => 'Store pickup',
            'price' => 0,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        foreach (['cod', 'pickup', 'bank', 'corvuspay', 'keks'] as $index => $code) {
            PaymentMethod::query()->create([
                'code' => $code,
                'name' => $code,
                // The store's CorvusPay method has no provider configured.
                'provider' => $code === 'corvuspay' ? null : $code,
                'fee_type' => 'fixed',
                'fee_value' => $code === 'cod' ? 3 : 0,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }

    public function test_pickup_options_offer_card_and_bank_and_replace_selected_cash_payment(): void
    {
        $this->addProductToCart();

        $this->getJson(route('checkout.options', [
            'shipping_method_code' => 'pickup',
            'payment_method_code' => 'cod',
        ]))
            ->assertOk()
            ->assertJsonPath('payment_methods.*.code', ['bank', 'corvuspay'])
            ->assertJsonPath('totals.shipping_method_code', 'pickup')
            ->assertJsonPath('totals.payment_method_code', 'bank')
            ->assertJsonPath('totals.shipping_total', 0)
            ->assertJsonPath('totals.payment_fee_total', 0);
    }

    public function test_standard_delivery_still_offers_cash_payment(): void
    {
        $this->getJson(route('checkout.options', [
            'shipping_method_code' => 'standard',
            'payment_method_code' => 'cod',
        ]))
            ->assertOk()
            ->assertJsonPath('payment_methods.0.code', 'cod')
            ->assertJsonPath('totals.payment_method_code', 'cod')
            ->assertJsonPath('totals.shipping_total', 5)
            ->assertJsonPath('totals.payment_fee_total', 3);
    }

    public function test_options_filter_payments_for_fallback_shipping_method(): void
    {
        ShippingMethod::query()->where('code', 'standard')->update(['is_active' => false]);

        $this->getJson(route('checkout.options', [
            'shipping_method_code' => 'unavailable',
            'payment_method_code' => 'cod',
        ]))
            ->assertOk()
            ->assertJsonPath('totals.shipping_method_code', 'pickup')
            ->assertJsonPath('payment_methods.*.code', ['bank', 'corvuspay'])
            ->assertJsonPath('totals.payment_method_code', 'bank');
    }

    #[DataProvider('cashPaymentCodes')]
    public function test_pickup_rejects_cash_payment_when_order_is_submitted_directly(string $paymentCode): void
    {
        $this->addProductToCart();

        $this->postJson('/checkout', $this->checkoutPayload($paymentCode))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method_code');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public static function cashPaymentCodes(): array
    {
        return [['cod'], ['pickup']];
    }

    #[DataProvider('prepaidPaymentCodes')]
    public function test_pickup_accepts_bank_and_card_orders(string $paymentCode): void
    {
        $this->addProductToCart();

        $response = $this->post('/checkout', $this->checkoutPayload($paymentCode));
        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route(
            $paymentCode === 'bank' ? 'checkout.success' : 'checkout.corvus.start',
            ['orderNumber' => $order->order_number]
        ));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_method_code' => 'pickup',
            'payment_method_code' => $paymentCode,
            'shipping_total' => 0,
            'payment_fee_total' => 0,
        ]);
    }

    public static function prepaidPaymentCodes(): array
    {
        return [['bank'], ['corvuspay']];
    }

    #[DataProvider('storefrontUserAgents')]
    public function test_checkout_rerender_preserves_pickup_and_uses_an_available_payment(string $userAgent): void
    {
        $this->addProductToCart();

        $this->withHeaders(['User-Agent' => $userAgent])
            ->withSession(['_old_input' => [
                'shipping_method_code' => 'pickup',
                'payment_method_code' => 'cod',
            ]])
            ->get('/checkout')
            ->assertOk()
            ->assertViewHas('paymentMethods', fn ($methods) => $methods->pluck('code')->all() === ['bank', 'corvuspay'])
            ->assertViewHas('checkoutTotals', fn ($totals) => $totals['shipping_method_code'] === 'pickup' && $totals['payment_method_code'] === 'bank')
            ->assertDontSee('name="payment_method_code" value="cod"', false)
            ->assertSee('name="payment_method_code" value="bank" checked', false);
    }

    public static function storefrontUserAgents(): array
    {
        return [
            ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15'],
            ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'],
        ];
    }

    public function test_pickup_supports_existing_bank_and_card_aliases_and_preserves_subtotal_rules(): void
    {
        foreach (['bank_transfer', 'wspay', 'ws_pay', 'corvus', 'corvus_pay'] as $code) {
            PaymentMethod::query()->create([
                'code' => $code,
                'name' => $code,
                'is_active' => true,
            ]);
        }
        PaymentMethod::query()->where('code', 'bank_transfer')->update(['min_subtotal' => 100]);
        PaymentMethod::query()->where('code', 'wspay')->update(['is_active' => false]);

        $codes = app(CheckoutService::class)->availablePaymentMethods(50, 'HR', null, null, 'pickup')->pluck('code')->all();

        $this->assertEqualsCanonicalizing(['bank', 'corvuspay', 'ws_pay', 'corvus', 'corvus_pay'], $codes);
    }

    private function addProductToCart(): void
    {
        $product = Product::query()->create([
            'code' => 'pickup-test-product',
            'sku' => 'PICKUP-TEST',
            'is_active' => true,
            'base_price' => 40,
            'stock_qty' => 10,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'hr',
            'name' => 'Pickup test product',
            'slug' => 'pickup-test-product',
        ]);

        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
    }

    private function checkoutPayload(string $paymentCode): array
    {
        return [
            'customer_email' => 'pickup@example.test',
            'customer_phone' => '+38591000002',
            'billing_first_name' => 'Test',
            'billing_last_name' => 'Customer',
            'billing_address_line_1' => 'Main Street 1',
            'billing_postal_code' => '10000',
            'billing_city' => 'Zagreb',
            'billing_state' => 'Grad Zagreb',
            'billing_country_code' => 'HR',
            'use_billing_for_shipping' => '1',
            'shipping_method_code' => 'pickup',
            'payment_method_code' => $paymentCode,
            'accept_terms' => '1',
        ];
    }
}
