<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutMarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Mail::fake();
        Http::preventStrayRequests();
        ShippingMethod::query()->create(['code' => 'standard', 'name' => 'Delivery', 'price' => 5, 'is_active' => true]);
        PaymentMethod::query()->create(['code' => 'bank', 'name' => 'Bank', 'is_active' => true]);
        $product = Product::query()->create(['code' => 'consent-test', 'is_active' => true, 'base_price' => 40, 'stock_qty' => 10]);
        ProductTranslation::query()->create(['product_id' => $product->id, 'locale' => 'hr', 'name' => 'Consent test', 'slug' => 'consent-test']);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
    }

    public function test_checkout_accepts_browser_written_cmp_cookie_and_stores_minimal_evidence(): void
    {
        $cookie = [
            'categories' => ['necessary', 'marketing'],
            'revision' => 0,
            'consentId' => 'private-client-id-that-must-not-be-saved',
            'consentTimestamp' => now()->subDay()->toIso8601String(),
            'lastConsentTimestamp' => now()->subMinute()->toIso8601String(),
            'expirationTime' => now()->subMinute()->addDays(182)->getTimestampMs(),
        ];
        $this->withUnencryptedCookie('cc_cookie', json_encode($cookie))
            ->post('/checkout', $this->checkoutPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame([
            'marketing_consent' => true,
            'marketing_consent_at' => now()->subMinute()->toIso8601String(),
        ], $order->payload['privacy']);
        $this->assertStringNotContainsString($cookie['consentId'], json_encode($order->payload));
    }

    public function test_checkout_works_without_marketing_consent_and_cannot_opt_in_through_post_fields(): void
    {
        $this->post('/checkout', [
            ...$this->checkoutPayload(),
            'marketing_consent' => true,
            'privacy' => ['marketing_consent' => true, 'marketing_consent_at' => now()->toIso8601String()],
            'cc_cookie' => json_encode(['categories' => ['necessary', 'marketing']]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(['marketing_consent' => false, 'marketing_consent_at' => null], $order->payload['privacy']);
        $this->assertSame('bank', $order->payment_method_code);
        Http::assertNothingSent();
    }

    private function checkoutPayload(): array
    {
        return [
            'customer_email' => 'consent@example.test',
            'customer_phone' => '+38591000002',
            'billing_first_name' => 'Test',
            'billing_last_name' => 'Customer',
            'billing_address_line_1' => 'Main Street 1',
            'billing_postal_code' => '10000',
            'billing_city' => 'Zagreb',
            'billing_state' => 'Grad Zagreb',
            'billing_country_code' => 'HR',
            'use_billing_for_shipping' => '1',
            'shipping_method_code' => 'standard',
            'payment_method_code' => 'bank',
            'accept_terms' => '1',
        ];
    }
}
