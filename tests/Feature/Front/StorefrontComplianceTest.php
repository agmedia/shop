<?php

namespace Tests\Feature\Front;

use App\Jobs\SendWithdrawalReceipt;
use App\Mail\WithdrawalReceipt;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Sales\ContractWithdrawal;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Services\Front\CartService;
use App\Services\Front\CheckoutService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StorefrontComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'de', 'app.fallback_locale' => 'en']);
        app()->setLocale('de');
    }

    public function test_guest_withdrawal_requires_review_and_records_one_declaration_and_receipt(): void
    {
        Queue::fake();
        $this->get('/rucksendungen-und-reklamationen?withdrawal=1')->assertOk()->assertSee('Vertrag widerrufen')->assertDontSee('<script src="https://www.google.com/recaptcha', false);
        $draft = ['customer_name' => 'Test Customer', 'email' => 'customer@example.test', 'order_number' => 'DE-123', 'items' => 'SKU-1, Größe M, 1 Stück'];
        $response = $this->post('/vertrag-widerrufen/preview', $draft)->assertOk()->assertSee('Widerruf bestätigen');
        $this->assertDatabaseCount('contract_withdrawals', 0);
        Queue::assertNothingPushed();
        $token = $response->viewData('token');
        $this->post('/vertrag-widerrufen', ['token' => $token, 'email' => 'tampered@example.test'])->assertOk()->assertSee('Ihr Widerruf ist eingegangen');
        $withdrawal = ContractWithdrawal::query()->sole();
        $this->assertSame($draft['email'], $withdrawal->email);
        $this->assertSame($draft['items'], $withdrawal->items);
        $this->assertNotNull($withdrawal->received_at);
        $this->assertStringContainsString('DE-123', $withdrawal->statement);
        $this->assertDatabaseHas('contact_messages', ['email' => $draft['email']]);
        Queue::assertPushed(SendWithdrawalReceipt::class, 1);
        $this->post('/vertrag-widerrufen', ['token' => $token])->assertOk();
        $this->assertDatabaseCount('contract_withdrawals', 1);
        Queue::assertPushed(SendWithdrawalReceipt::class, 1);
        $this->post('/vertrag-widerrufen', ['token' => 'unknown'])->assertSessionHasErrors('token');
    }

    public function test_full_withdrawal_needs_no_items_reason_or_account_and_mobile_has_accessible_form(): void
    {
        Queue::fake();
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')->get('/rucksendungen-und-reklamationen?withdrawal=1')->assertOk()
            ->assertSee('id="main-content"', false)->assertSee('for="withdrawal-email"', false);
        $response = $this->post('/vertrag-widerrufen/preview', ['customer_name' => 'Guest', 'email' => 'guest@example.test', 'order_number' => 'AT-1'])->assertOk()->assertSee('Gesamte Bestellung');
        $this->post('/vertrag-widerrufen', ['token' => $response->viewData('token')])->assertOk();
        $this->assertDatabaseHas('contract_withdrawals', ['order_number' => 'AT-1', 'items' => '']);
    }

    public function test_receipt_contains_exact_declaration_and_time_and_is_not_resent(): void
    {
        Mail::fake();
        $withdrawal = ContractWithdrawal::query()->create([
            'id' => (string) str()->uuid(), 'customer_name' => 'Guest', 'email' => 'guest@example.test',
            'order_number' => 'AT-1', 'items' => 'SKU-123 <script>alert(1)</script>',
            'statement' => 'Hiermit widerrufe ich die Bestellung AT-1.', 'locale' => 'de', 'received_at' => '2026-10-20 12:34:56',
        ]);
        (new SendWithdrawalReceipt($withdrawal->id))->handle();
        Mail::assertSent(WithdrawalReceipt::class, fn ($mail) => $mail->hasTo('guest@example.test'));
        $body = (new WithdrawalReceipt($withdrawal))->render();
        $this->assertStringContainsString($withdrawal->statement, $body);
        $this->assertStringContainsString($withdrawal->items, $body);
        $this->assertStringContainsString('20.10.2026 14:34:56', $body);
        $this->assertNotNull($withdrawal->fresh()->receipt_sent_at);
        (new SendWithdrawalReceipt($withdrawal->id))->handle();
        Mail::assertSentCount(1);
    }

    public function test_destination_tax_uses_shipping_country_and_preserves_source_net_from_tax_inclusive_prices(): void
    {
        $product = $this->seedCart();
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', true);
        $cart = app(CartService::class);
        $this->assertSame(100.0, $cart->summary(countryCode: 'DE')['subtotal']);
        $this->assertSame(119.0, $cart->summary(countryCode: 'DE')['grand_total']);
        $this->assertSame(120.0, $cart->summary(countryCode: 'AT')['grand_total']);
        $this->getJson('/checkout/options?billing_country_code=AT&shipping_country_code=DE&ship_to_different_address=1&shipping_method_code=standard&payment_method_code=bank')
            ->assertOk()->assertJsonPath('totals.tax_total', 19.8)->assertJsonPath('totals.grand_total', 124)
            ->assertJsonPath('totals.shipping_total', 4.2);
        $this->getJson('/checkout/options?billing_country_code=AT&shipping_country_code=DE&ship_to_different_address=0&shipping_method_code=standard&payment_method_code=bank')
            ->assertOk()->assertJsonPath('totals.grand_total', 125);
        $order = app(CheckoutService::class)->placeOrder([
            'customer_email' => 'guest@example.test', 'billing_country_code' => 'AT', 'shipping_country_code' => 'DE',
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank',
        ]);
        $this->assertEquals(124, $order->grand_total);
        $this->assertEquals(19.8, $order->tax_total);
        $this->assertEquals(19, $order->items->first()->tax_rate);
        $this->assertEquals(25, $product->taxRate->rate);
    }

    public function test_checkout_has_official_notice_immediately_before_payment_button_on_both_templates(): void
    {
        $this->seedCart();
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);
        foreach (['desktop', 'mobile'] as $variant) {
            $agent = $variant === 'mobile' ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)' : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)';
            $this->withHeader('User-Agent', $agent)->get('/checkout')->assertOk()
                ->assertSeeInOrder(['data-legal-guarantee', 'legal-guarantee-de.jpg', 'Zahlungspflichtig bestellen']);
        }
    }

    public function test_no_google_network_script_before_consent_and_klaviyo_remains_gated_when_cmp_is_off(): void
    {
        app(SystemSettingsService::class)->putMany(['store_analytics_enabled' => true, 'store_analytics_ga4_measurement_id' => 'G-DT810P4TK9']);
        $settings = app(\App\Services\Front\StoreSettingsService::class)->all();
        $settings['analytics'] = ['enabled' => true, 'ga4_measurement_id' => 'G-DT810P4TK9'];
        $html = view('front.partials.analytics', ['storeSettings' => $settings])->render();
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html);
        $this->assertStringContainsString('if (!analyticsGranted || window.__googleAnalyticsLoaded) return', $html);
        app(SystemSettingsService::class)->put('store_cookie_consent_enabled', false);
        $settings['cookies']['enabled'] = false;
        $html = view('front.partials.analytics', ['storeSettings' => $settings])->render();
        $this->assertStringNotContainsString('window.cookieMarketingAllowed = true;', $html);
    }

    public function test_dispatch_confirmation_is_german_and_sent_once(): void
    {
        Mail::fake();
        $this->seedCart();
        $order = app(CheckoutService::class)->placeOrder([
            'customer_email' => 'guest@example.test', 'billing_country_code' => 'DE', 'shipping_country_code' => 'DE',
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank',
        ]);
        (new \App\Jobs\SendOrderDispatchConfirmation($order->id))->handle();
        (new \App\Jobs\SendOrderDispatchConfirmation($order->id))->handle();
        Mail::assertSent(\App\Mail\OrderDispatchConfirmation::class, 1);
        $body = (new \App\Mail\OrderDispatchConfirmation($order))->render();
        $this->assertStringContainsString('Ihre Bestellung wurde versendet', $body);
        $this->assertStringContainsString('Testprodukt', $body);
        $this->assertNotNull(data_get($order->fresh()->payload, 'dispatch_confirmation.sent_at'));
    }

    public function test_confirmation_and_mail_task_are_persisted_together_without_running_external_mail(): void
    {
        Mail::fake();
        $preview = $this->post('/vertrag-widerrufen/preview', [
            'customer_name' => 'Guest', 'email' => 'guest@example.test', 'order_number' => 'AT-2',
        ])->assertOk();
        $this->post('/vertrag-widerrufen', ['token' => $preview->viewData('token')])->assertOk();
        $this->assertDatabaseCount('contract_withdrawals', 1);
        $this->assertDatabaseCount('jobs', 1);
        Mail::assertNothingSent();
    }

    public function test_catalog_export_matches_order_product_ids_and_rejects_non_https_origin(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $product = $this->seedCart();
        $product->addMedia(\Illuminate\Http\UploadedFile::fake()->image('catalog.jpg'))->toMediaCollection('product_main');
        $output = storage_path('framework/testing/catalog-'.str()->uuid().'.json');
        $this->artisan('klaviyo:export-catalog', ['--base-url' => 'http://example.test', '--output' => $output])->assertFailed();
        $this->artisan('klaviyo:export-catalog', ['--base-url' => 'https://shop.example.test', '--output' => $output])->assertSuccessful();
        $items = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame((string) $product->id, $items[0]['id']);
        $this->assertSame('https://shop.example.test/product/testprodukt?lang=de', $items[0]['link']);
        $this->assertStringStartsWith('https://', $items[0]['image_link']);
        $this->assertSame(148.75, $items[0]['price']);
        unlink($output);
    }

    public function test_checkout_rejects_an_outdated_total_before_creating_an_order(): void
    {
        $this->seedCart();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(CheckoutService::class)->placeOrder([
                'customer_email' => 'guest@example.test', 'billing_country_code' => 'DE', 'shipping_country_code' => 'DE',
                'shipping_method_code' => 'standard', 'payment_method_code' => 'bank', 'quoted_total' => 1,
            ]);
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    private function seedCart(): Product
    {
        $rate = TaxRate::query()->create(['code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25, 'rate_type' => 'percent', 'is_default' => true, 'is_active' => true]);
        $product = Product::query()->create(['code' => 'TEST', 'sku' => 'SKU-1', 'base_price' => 125, 'stock_qty' => 10, 'is_active' => true, 'tax_rate_id' => $rate->id]);
        ProductTranslation::query()->create(['product_id' => $product->id, 'locale' => 'de', 'name' => 'Testprodukt', 'slug' => 'testprodukt']);
        ShippingMethod::query()->create(['code' => 'standard', 'name' => 'Standard', 'price' => 5, 'is_active' => true]);
        PaymentMethod::query()->create(['code' => 'bank', 'name' => 'Bank', 'is_active' => true]);
        app(CartService::class)->add($product);

        return $product;
    }
}
