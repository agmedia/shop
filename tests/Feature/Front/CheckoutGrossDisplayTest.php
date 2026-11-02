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
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutGrossDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.locale' => 'de', 'app.fallback_locale' => 'en']);
        app()->setLocale('de');
        app(SystemSettingsService::class)->putMany([
            'store_pricing_prices_include_tax' => true, 'catalog_use_mobile_view' => true,
        ]);
        $rate = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'DISPLAY', 'sku' => 'DISPLAY', 'base_price' => 125,
            'stock_qty' => 20, 'is_active' => true, 'tax_rate_id' => $rate->id,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => 'de', 'name' => 'Display item', 'slug' => 'display-item',
        ]);
        ShippingMethod::query()->create([
            'code' => 'standard_eu', 'name' => 'Delivery', 'price' => 10, 'is_active' => true,
        ]);
        PaymentMethod::query()->create([
            'code' => 'bank', 'name' => 'Bank', 'fee_type' => 'fixed', 'fee_value' => 2, 'is_active' => true,
        ]);
        app(CartService::class)->add($product);
    }

    #[DataProvider('checkoutDisplays')]
    public function test_checkout_displays_gross_prices_and_included_tax_without_changing_saved_net_totals(
        string $country,
        string $device,
        ?string $discountScope
    ): void {
        if ($discountScope !== null) {
            CatalogAction::query()->create([
                'code' => 'display-discount', 'scope' => $discountScope,
                'type' => CatalogAction::TYPE_PERCENTAGE, 'discount_value' => 10,
                'target_type' => CatalogAction::TARGET_ALL, 'audience_type' => CatalogAction::AUDIENCE_ALL,
                'coupon_code' => 'BALI10', 'is_active' => true,
            ]);
            $this->assertTrue(app(CartService::class)->applyCoupon('BALI10'));
        }
        $this->withHeader('User-Agent', $device === 'mobile'
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)');
        $response = $this->withSession(['_old_input' => [
            'billing_country_code' => $country, 'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'bank',
        ]])->get('/checkout')->assertOk();
        $grossSubtotal = $country === 'DE' ? 119.0 : 120.0;
        $grossDiscount = $discountScope === null ? 0.0 : ($country === 'DE' ? 11.9 : 12.0);
        $grandTotal = round($grossSubtotal - $grossDiscount + 12, 2);
        $xpath = $this->xpath($response->getContent());
        $pricing = $response->viewData('displayPricing');
        $this->assertSame($grossSubtotal, $pricing['subtotal']);
        $this->assertSame($grossDiscount, $pricing['discount_total']);
        $this->assertSame(10.0, $pricing['shipping_total']);
        $this->assertSame(2.0, $pricing['payment_fee_total']);
        $this->assertSame($grandTotal, $pricing['grand_total']);
        foreach (['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'payment_fee_total', 'grand_total'] as $key) {
            $this->assertSame($pricing[$key.'_formatted'], trim($xpath->evaluate('string(//*[@data-checkout-price="'.$key.'"])')));
        }
        $this->assertSame('Enthaltene MwSt. ('.($country === 'DE' ? '19' : '20').' %)', $pricing['tax_label']);
        $this->assertSame($pricing['tax_label'], trim($xpath->evaluate('string(//*[@data-checkout-tax-label])')));
        $expectedLineTotal = $discountScope === CatalogAction::SCOPE_PRODUCT ? round($grossSubtotal * 0.9, 2) : $grossSubtotal;
        $this->assertSame(\App\Support\Currency::format($expectedLineTotal), trim($xpath->evaluate('string(//*[@data-checkout-line-total])')));

        $quote = $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'ship_to_different_address' => 1, 'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'bank',
        ]))->assertOk();
        $this->assertEquals($pricing, $quote->json('display_pricing'));
        $this->assertSame($grossSubtotal === 119.0 ? 8.4 : 8.33, (float) $quote->json('totals.shipping_total'));
        $order = app(CheckoutService::class)->placeOrder([
            'customer_email' => 'display@example.test', 'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard_eu', 'payment_method_code' => 'bank', 'quoted_total' => $grandTotal,
        ]);
        $this->assertEquals($grandTotal, $order->grand_total);
        $this->assertEquals($quote->json('totals.shipping_total'), $order->shipping_total);
        $this->assertEquals($quote->json('totals.payment_fee_total'), $order->payment_fee_total);
        $this->assertEquals($quote->json('totals.tax_total'), $order->tax_total);
        $this->assertEquals(100, $order->items->first()->unit_price);
        $this->assertEquals($discountScope === CatalogAction::SCOPE_PRODUCT ? 90 : 100, $order->items->first()->line_total);
        $this->assertEquals(10, data_get($order->payload, 'pricing.shipping_gross'));
    }

    public static function checkoutDisplays(): array
    {
        $cases = [];
        foreach (['DE', 'AT'] as $country) {
            foreach (['desktop', 'mobile'] as $device) {
                foreach ([null, CatalogAction::SCOPE_CART, CatalogAction::SCOPE_PRODUCT] as $scope) {
                    $cases[$country.' '.$device.' '.($scope ?? 'no discount')] = [$country, $device, $scope];
                }
            }
        }

        return $cases;
    }

    public function test_guarantee_is_a_visible_link_to_unchanged_official_image_before_order_button(): void
    {
        foreach (['Mozilla/5.0 (Macintosh; Intel Mac OS X)', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'] as $agent) {
            $response = $this->withHeader('User-Agent', $agent)->get('/checkout')->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame('Gesetzliche Gewährleistung – Informationen anzeigen', trim($xpath->evaluate('string(//*[@data-legal-guarantee]/a)')));
            $this->assertStringEndsWith('/front-theme/images/legal/legal-guarantee-de.jpg', $xpath->evaluate('string(//*[@data-legal-guarantee]/a/@href)'));
            $this->assertSame(0, $xpath->query('//*[@data-legal-guarantee]//img')->length);
            $this->assertSame('Zahlungspflichtig bestellen', trim($xpath->evaluate('string(//*[@data-legal-guarantee]/following::button[@type="submit"][1])')));
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
