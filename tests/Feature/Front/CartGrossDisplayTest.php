<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\Product;
use App\Models\Settings\Local\TaxRate;
use App\Services\Front\CartService;
use App\Services\Settings\SystemSettingsService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartGrossDisplayTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('cartDisplays')]
    public function test_cart_renders_consumer_gross_prices_with_included_vat_and_preserves_internal_net_totals(
        string $device, bool $pricesIncludeTax, string $promotion, float $grossDiscount, float $includedTax, float $grossTotal
    ): void {
        config(['app.locale' => 'hr', 'app.fallback_locale' => 'en']);
        app()->setLocale('hr');
        app(SystemSettingsService::class)->putMany([
            'store_pricing_prices_include_tax' => $pricesIncludeTax, 'catalog_use_mobile_view' => true,
        ]);
        $tax = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25, 'rate_type' => 'percent',
            'is_active' => true, 'is_default' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'GROSS-CART', 'sku' => 'GROSS-CART', 'is_active' => true, 'stock_qty' => 30,
            'base_price' => $pricesIncludeTax ? 10.99 : 8.79, 'tax_rate_id' => $tax->id,
        ]);
        $product->translations()->create(['locale' => 'hr', 'name' => 'Artikl', 'slug' => 'gross-cart']);
        if ($promotion !== 'none') {
            CatalogAction::query()->create([
                'code' => 'CART-PROMOTION',
                'scope' => $promotion === 'cart_coupon' ? CatalogAction::SCOPE_CART : CatalogAction::SCOPE_PRODUCT,
                'type' => $promotion === 'buy_five' ? CatalogAction::TYPE_BUY_X_GET_Y : CatalogAction::TYPE_PERCENTAGE,
                'discount_value' => $promotion === 'buy_five' ? null : 10,
                'buy_qty' => $promotion === 'buy_five' ? 5 : null,
                'get_qty' => $promotion === 'buy_five' ? 1 : null,
                'coupon_code' => str_ends_with($promotion, '_coupon') ? 'BALI10' : null,
                'target_type' => CatalogAction::TARGET_ALL, 'audience_type' => CatalogAction::AUDIENCE_ALL,
                'is_exclusive' => $promotion === 'buy_five', 'is_active' => true,
            ]);
        }
        $cart = app(CartService::class);
        $this->assertTrue($cart->add($product, 5));
        if (str_ends_with($promotion, '_coupon')) {
            $this->assertTrue($cart->applyCoupon('BALI10'));
        }
        $before = $cart->summary();
        $lineBefore = $cart->lines()->first();
        $this->assertSame(43.95, $before['subtotal']);
        $this->assertSame($includedTax, $before['tax_total']);
        $this->assertSame($grossTotal, $before['grand_total']);

        $response = $this->withHeader('User-Agent', $device === 'mobile'
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)')->get('/cart')->assertOk();
        $response->assertViewIs('front.'.$device.'.cart.index');
        $this->assertSame($before, $response->viewData('summary'));
        $this->assertSame($before, $cart->summary());
        $display = $response->viewData('displayPricing');
        $this->assertSame(54.95, $display['subtotal']);
        $this->assertSame($grossDiscount, $display['discount_total']);
        $this->assertSame($includedTax, $display['tax_total']);
        $this->assertSame($grossTotal, $display['grand_total']);
        $this->assertSame(round($display['subtotal'] - $display['discount_total'], 2), $display['grand_total']);
        $this->assertSame(0.0, $display['shipping_total']);
        $this->assertSame(0.0, $display['payment_fee_total']);

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        foreach (['subtotal', 'discount_total', 'grand_total'] as $key) {
            $nodes = $xpath->query('//*[@data-cart-price="'.$key.'"]');
            if ($key === 'discount_total' && $grossDiscount === 0.0 && $nodes->length === 0) {
                continue;
            }
            $this->assertGreaterThan(0, $nodes->length, $device.' has no visible '.$key.' price');
            foreach ($nodes as $node) {
                $actual = trim($node->textContent);
                if ($key === 'discount_total') {
                    $actual = ltrim($actual, '-− ');
                }
                $this->assertSame($display[$key.'_formatted'], $actual);
            }
        }
        $taxLabel = __('compliance.tax_included');
        $this->assertSame($taxLabel, trim($xpath->evaluate('string(//*[@data-cart-tax-label])')));
        $this->assertSame(0, $xpath->query('//*[@data-cart-price="tax_total"]')->length);
        foreach (['data-cart-unit-price' => $lineBefore['display_unit_price'], 'data-cart-line-total' => $lineBefore['display_line_total']] as $attribute => $amount) {
            $nodes = $xpath->query('//*[@'.$attribute.']');
            $this->assertGreaterThan(0, $nodes->length);
            foreach ($nodes as $node) {
                $label = trim($node->textContent);
                $this->assertMatchesRegularExpression('/(?:EUR|€)/u', $label);
                $this->assertSame(1, preg_match('/\d+[.,]\d{2}/u', $label, $number));
                $this->assertSame($amount, (float) str_replace(',', '.', $number[0]));
            }
        }
    }

    public static function cartDisplays(): array
    {
        $cases = [];
        foreach (['desktop', 'mobile'] as $device) {
            foreach ([true, false] as $inclusive) {
                foreach ([
                    'none' => [0.0, 11.0, 54.95],
                    'buy_five' => [10.99, 8.8, 43.96],
                    'product_coupon' => [5.5, 9.9, 49.45],
                    'cart_coupon' => [5.5, 9.9, 49.45],
                ] as $promotion => [$discount, $tax, $total]) {
                    $cases[$device.' '.($inclusive ? 'gross storage ' : 'net storage ').$promotion] = [$device, $inclusive, $promotion, $discount, $tax, $total];
                }
            }
        }

        return $cases;
    }
}
