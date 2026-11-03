<?php

namespace Tests\Feature\Front;

use App\Livewire\Admin\Catalog\Action\Form as ActionForm;
use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Product\Product;
use App\Models\Settings\Local\Language;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Models\User;
use App\Services\Front\CartService;
use App\Services\Front\CheckoutService;
use App\Services\Payments\CorvusPayFormService;
use App\Services\Pricing\ProductPricePresentationService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class BuyXGetYPromotionTest extends TestCase
{
    use RefreshDatabase;

    private int $productSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['app.locale' => 'en', 'app.fallback_locale' => 'en']);
        app()->setLocale('en');
        app(SystemSettingsService::class)->putMany(['store_pricing_prices_include_tax' => true, 'catalog_use_mobile_view' => true]);
        foreach (['hr', 'en', 'de'] as $locale) {
            Language::query()->updateOrCreate(['code' => $locale], ['name' => $locale, 'is_active' => true, 'is_default' => $locale === 'hr']);
        }
        TaxRate::query()->create(['code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25, 'rate_type' => 'percent', 'is_active' => true, 'is_default' => true]);
        ShippingMethod::query()->create([
            'code' => 'standard', 'name' => 'Delivery', 'price' => 10, 'free_over' => 80,
            'settings' => ['free_over_basis' => 'gross'], 'is_active' => true,
        ]);
        PaymentMethod::query()->create([
            'code' => 'corvuspay', 'name' => 'Card', 'is_active' => true,
            'settings' => ['corvus_mode' => 'test', 'corvus_store_id' => 'test-store', 'corvus_secret_key' => 'test-secret-only'],
        ]);
    }

    #[DataProvider('countryTaxes')]
    public function test_five_inclusive_units_give_one_exact_free_unit_with_matching_quote_order_and_card_amount(
        string $country, float $freeUnitNet, float $goodsTax, float $shippingNet, float $orderTax
    ): void {
        $this->promotion();
        $product = $this->product(9.99);
        $cart = app(CartService::class);
        $this->assertTrue($cart->add($product, 5));
        $summary = $cart->summary(countryCode: $country);
        $this->assertSame(5, $summary['item_qty']);
        $this->assertSame($freeUnitNet, $summary['discount_total']);
        $this->assertSame($goodsTax, $summary['tax_total']);
        $this->assertSame(39.96, $summary['grand_total']);

        $quote = $this->quote($country)->assertJsonPath('display_pricing.subtotal', 49.95)
            ->assertJsonPath('display_pricing.discount_total', 9.99)
            ->assertJsonPath('display_pricing.shipping_total', 10)
            ->assertJsonPath('display_pricing.grand_total', 49.96);
        $this->assertSame($orderTax, (float) $quote->json('totals.tax_total'));
        $this->assertSame($shippingNet, (float) $quote->json('totals.shipping_total'));
        $order = app(CheckoutService::class)->placeOrder([
            'customer_email' => 'promotion@example.test', 'billing_country_code' => 'HR', 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard', 'payment_method_code' => 'corvuspay', 'quoted_total' => 49.96,
        ]);
        $this->assertEquals(49.96, $order->grand_total);
        $this->assertEquals($freeUnitNet, $order->discount_total);
        $this->assertEquals($orderTax, $order->tax_total);
        $this->assertEquals($shippingNet, $order->shipping_total);
        $this->assertSame(5, $order->items->sum('quantity'));
        $this->assertEquals($goodsTax, $order->items->sum('tax_amount'));
        $this->assertEquals($freeUnitNet, $order->items->sum('discount_amount'));
        $this->assertEquals(round($freeUnitNet * 4, 2), $order->items->sum('line_total'));
        $this->assertSame(1, data_get($order->items->first()->payload, 'quantity_promotion.free_quantity'));
        $this->assertSame(95, $product->fresh()->stock_qty);
        $this->assertEquals(10, data_get($order->payload, 'pricing.shipping_gross'));
        $form = app(CorvusPayFormService::class)->buildFormData($order);
        $this->assertSame('49.96', $form['payload']['amount']);
        $this->assertSame('EUR', $form['payload']['currency']);
        Http::assertNothingSent();
    }

    #[DataProvider('quantityBoundaries')]
    public function test_buy_quantity_includes_the_free_unit_and_shipping_uses_the_discounted_total(int $quantity, float $paidGoods, float $grossDiscount, float $shipping): void
    {
        $this->promotion();
        app(CartService::class)->add($this->product(9.99), $quantity);
        $this->assertSame($paidGoods, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);
        $quote = $this->quote('HR');
        $this->assertSame($grossDiscount, (float) $quote->json('display_pricing.discount_total'));
        $this->assertSame($shipping, (float) $quote->json('display_pricing.shipping_total'));
        $this->assertSame(round($paidGoods + $shipping, 2), (float) $quote->json('display_pricing.grand_total'));
    }

    public function test_ten_mixed_models_get_the_two_globally_cheapest_units_free(): void
    {
        $this->promotion();
        foreach (range(1, 10) as $price) {
            app(CartService::class)->add($this->product($price));
        }
        $summary = app(CartService::class)->summary(countryCode: 'HR');
        $this->assertSame(10, $summary['item_qty']);
        $this->assertSame(52.0, $summary['grand_total']);
        $this->assertSame(2.4, $summary['discount_total']);
        $this->quote('HR')->assertJsonPath('display_pricing.discount_total', 3);
    }

    public function test_eligible_quantities_combine_different_models_and_selected_sizes(): void
    {
        $this->promotion();
        $first = $this->product(9.99);
        $option = Option::query()->create(['code' => 'promotion-size', 'type' => Option::TYPE_SELECT, 'is_active' => true]);
        $option->translations()->create(['locale' => 'en', 'name' => 'Size', 'slug' => 'promotion-size']);
        $option->products()->attach($first->id, ['is_required' => true]);
        foreach (['S' => [9.99, 1], 'M' => [10.99, 2]] as $size => [$price, $quantity]) {
            $value = $option->values()->create(['code' => strtolower($size), 'is_active' => true]);
            $value->translations()->create(['locale' => 'en', 'name' => $size, 'slug' => strtolower($size)]);
            $row = $first->optionValues()->create([
                'option_value_id' => $value->id, 'mode' => 'single', 'stock_qty' => 20, 'is_active' => true,
                'price_override' => $price, 'combination_hash' => sha1('single:'.$value->id),
            ]);
            $this->assertTrue(app(CartService::class)->add($first, $quantity, $row->id));
        }
        app(CartService::class)->add($this->product(12.99), 2);
        $summary = app(CartService::class)->summary(countryCode: 'HR');
        $this->assertSame(5, $summary['item_qty']);
        $this->assertSame(47.96, $summary['grand_total']);
        $this->quote('HR')->assertJsonPath('display_pricing.discount_total', 9.99);
    }

    public function test_overlapping_category_targets_do_not_double_count_and_unrelated_products_do_not_qualify(): void
    {
        $first = Category::query()->create(['scope' => Category::SCOPE_CATALOG, 'code' => 'first', 'is_active' => true]);
        $second = Category::query()->create(['scope' => Category::SCOPE_CATALOG, 'code' => 'second', 'is_active' => true]);
        $action = $this->promotion(['target_type' => CatalogAction::TARGET_CATEGORY]);
        foreach ([$first, $second] as $category) {
            $action->targets()->create(['target_type' => CatalogAction::TARGET_CATEGORY, 'target_id' => $category->id]);
        }
        $eligible = $this->product(10);
        $eligible->categories()->attach([$first->id, $second->id]);
        $cart = app(CartService::class);
        $cart->add($eligible, 4);
        $cart->add($this->product(1));
        $this->assertSame(0.0, $cart->summary(countryCode: 'HR')['discount_total']);
        $cart->set($eligible, 5);
        $summary = $cart->summary(countryCode: 'HR');
        $this->assertSame(8.0, $summary['discount_total']);
        $this->assertSame(41.0, $summary['grand_total']);
        $cart->set($eligible, 4);
        $this->assertSame(0.0, $cart->summary(countryCode: 'HR')['discount_total']);
        $this->assertSame(41.0, $cart->summary(countryCode: 'HR')['grand_total']);
    }

    #[DataProvider('discountScopes')]
    public function test_exclusive_quantity_offer_does_not_stack_with_a_smaller_product_or_cart_coupon(string $scope): void
    {
        $this->promotion();
        $this->promotion([
            'code' => 'TEN', 'type' => CatalogAction::TYPE_PERCENTAGE, 'scope' => $scope,
            'discount_value' => 10, 'is_exclusive' => false,
            'coupon_code' => $scope === CatalogAction::SCOPE_CART ? 'TEN' : null,
        ]);
        app(CartService::class)->add($this->product(9.99), 5);
        $this->assertSame(39.96, app(CartService::class)->summary(null, 'TEN', 'HR')['grand_total']);
    }

    public function test_an_existing_better_fixed_product_discount_wins_without_stacking_free_units(): void
    {
        $this->promotion();
        $this->promotion(['code' => 'BETTER', 'type' => CatalogAction::TYPE_FIXED, 'discount_value' => 3, 'is_exclusive' => false]);
        app(CartService::class)->add($this->product(9.99), 5);
        $this->assertSame(34.95, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);
    }

    public function test_stock_clamping_happens_before_the_quantity_threshold_is_checked(): void
    {
        $this->promotion();
        $product = $this->product(9.99);
        $product->update(['stock_qty' => 4]);
        app(CartService::class)->add($product, 5);
        $summary = app(CartService::class)->summary(countryCode: 'HR');
        $this->assertSame(4, $summary['item_qty']);
        $this->assertSame(0.0, $summary['discount_total']);
        $this->assertSame(39.96, $summary['grand_total']);
    }

    public function test_quantity_offer_honors_its_existing_net_minimum_subtotal_gate(): void
    {
        $action = $this->promotion(['min_subtotal' => 40]);
        app(CartService::class)->add($this->product(9.99), 5);
        $this->assertSame(49.95, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);
        $action->update(['min_subtotal' => 39.95]);
        $this->assertSame(39.96, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);
    }

    public function test_an_explicitly_nonexclusive_quantity_offer_applies_a_cart_coupon_only_to_paid_units(): void
    {
        $this->promotion(['is_exclusive' => false]);
        $this->promotion([
            'code' => 'TEN', 'scope' => CatalogAction::SCOPE_CART, 'type' => CatalogAction::TYPE_PERCENTAGE,
            'discount_value' => 10, 'coupon_code' => 'TEN', 'is_exclusive' => false,
        ]);
        app(CartService::class)->add($this->product(9.99), 5);
        $this->assertSame(35.96, app(CartService::class)->summary(null, 'TEN', 'HR')['grand_total']);
    }

    #[DataProvider('ineligibleOffers')]
    public function test_unavailable_offers_do_not_discount_the_cart_or_leak_a_public_badge(string $condition): void
    {
        $attributes = match ($condition) {
            'expired' => ['ends_at' => now()->subMinute()],
            'future' => ['starts_at' => now()->addDay()],
            'private audience' => ['audience_type' => CatalogAction::AUDIENCE_USER, 'user_id' => User::factory()->create()->id],
            'coupon only' => ['coupon_code' => 'SECRET'],
            'inactive' => ['is_active' => false],
            default => ['usage_limit' => 1, 'used_count' => 1],
        };
        $this->promotion($attributes);
        $product = $this->product(9.99);
        app(CartService::class)->add($product, 5);
        $this->assertSame(49.95, app(CartService::class)->summary(countryCode: 'HR')['grand_total']);
        $this->assertNull(app(ProductPricePresentationService::class)->forProduct($product)['promotion_badge']);
    }

    #[DataProvider('badgeDevices')]
    public function test_quantity_badges_are_localized_on_the_actual_pdp_without_lowering_the_single_unit_price(string $locale, string $device): void
    {
        $this->promotion();
        $product = $this->product(9.99);
        $price = app(ProductPricePresentationService::class)->forProduct($product, locale: $locale, fallbackLocale: 'en');
        $expected = $locale === 'de' ? '5 nehmen, 4 zahlen' : '4+1';
        $this->assertSame($expected, $price['promotion_badge']);
        $this->assertSame(9.99, $price['current_gross']);
        $this->assertFalse($price['has_discount']);
        $response = $this->withHeader('User-Agent', $device === 'mobile'
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)')->get('/product/'.$product->code.'-'.$locale.'?lang='.$locale)->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame($expected, trim($xpath->evaluate('string(//*[@data-product-promotion-badge])')));
        $this->assertSame(0, $xpath->query('//*[@data-product-promotion-badge][@data-product-price-discount]')->length);
        $cardHtml = $device === 'mobile'
            ? view('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => 'en'])->render()
            : Blade::render('<x-front.desktop.product-card :product="$product" :locale="$locale" fallback-locale="en" />', ['product' => $product, 'locale' => $locale]);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$cardHtml);
        $this->assertSame($expected, trim((new \DOMXPath($document))->evaluate('string(//*[@data-product-promotion-badge])')));
    }

    public function test_an_ordinary_percentage_action_without_a_custom_badge_keeps_its_percentage_label(): void
    {
        $action = $this->promotion(['type' => CatalogAction::TYPE_PERCENTAGE, 'discount_value' => 10]);
        $action->translations()->update(['badge' => null]);
        $product = $this->product(9.99);
        $presentation = app(ProductPricePresentationService::class)->forProduct($product);
        $this->assertNull($presentation['promotion_badge']);
        $this->assertSame(10, $presentation['discount_percent']);
        foreach ([
            Blade::render('<x-front.desktop.product-card :product="$product" />', ['product' => $product]),
            view('front.mobile.partials.product-card', ['product' => $product])->render(),
        ] as $html) {
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $this->assertSame('-10%', trim((new \DOMXPath($document))->evaluate('string(//*[@data-product-promotion-badge])')));
        }
    }

    public function test_missing_german_badge_uses_an_explicit_croatian_fallback(): void
    {
        $action = $this->promotion();
        $action->translations()->where('locale', 'de')->delete();
        $this->assertSame('4+1', app(ProductPricePresentationService::class)->forProduct($this->product(9.99), locale: 'de', fallbackLocale: 'hr')['promotion_badge']);
    }

    #[DataProvider('invalidQuantities')]
    public function test_admin_requires_a_positive_free_quantity_smaller_than_the_total_buy_quantity(int $buy, int $get, string $field): void
    {
        $admin = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($admin);
        Livewire::actingAs($admin)->test(ActionForm::class)
            ->set('form.code', 'INVALID-QUANTITY')->set('form.title', 'Offer')
            ->set('form.locale', 'en')->set('form.type', CatalogAction::TYPE_BUY_X_GET_Y)
            ->set('form.buy_qty', $buy)->set('form.get_qty', $get)
            ->call('save')->assertHasErrors('form.'.$field);
        $this->assertDatabaseMissing('catalog_actions', ['code' => 'INVALID-QUANTITY']);
    }

    public static function countryTaxes(): array
    {
        return [['HR', 7.99, 8.0, 10.0, 8.0], ['DE', 8.39, 6.4, 8.4, 8.0], ['AT', 8.33, 6.64, 8.33, 8.31]];
    }

    public static function quantityBoundaries(): array
    {
        return [[4, 39.96, 0.0, 10.0], [5, 39.96, 9.99, 10.0], [6, 49.95, 9.99, 10.0], [10, 79.92, 19.98, 10.0], [11, 89.91, 19.98, 0.0]];
    }

    public static function discountScopes(): array
    {
        return [[CatalogAction::SCOPE_PRODUCT], [CatalogAction::SCOPE_CART]];
    }

    public static function ineligibleOffers(): array
    {
        return [['expired'], ['future'], ['private audience'], ['coupon only'], ['inactive'], ['used up']];
    }

    public static function badgeDevices(): array
    {
        return [['hr', 'desktop'], ['de', 'desktop'], ['hr', 'mobile'], ['de', 'mobile']];
    }

    public static function invalidQuantities(): array
    {
        return [[5, 5, 'get_qty'], [5, 6, 'get_qty'], [5, 0, 'get_qty'], [0, 1, 'buy_qty']];
    }

    private function product(float $price): Product
    {
        $code = 'PROMO-PRODUCT-'.++$this->productSequence;
        $product = Product::query()->create([
            'code' => $code, 'sku' => $code, 'base_price' => $price, 'stock_qty' => 100, 'is_active' => true,
            'tax_rate_id' => TaxRate::query()->where('code', 'VAT25')->value('id'),
        ]);
        foreach (['hr', 'en', 'de'] as $locale) {
            $product->translations()->create(['locale' => $locale, 'name' => 'Promotion product', 'slug' => $code.'-'.$locale]);
        }

        return $product;
    }

    private function promotion(array $attributes = []): CatalogAction
    {
        $action = CatalogAction::query()->create(array_replace([
            'code' => 'FOUR-PLUS-ONE', 'scope' => CatalogAction::SCOPE_PRODUCT,
            'type' => CatalogAction::TYPE_BUY_X_GET_Y, 'buy_qty' => 5, 'get_qty' => 1,
            'target_type' => CatalogAction::TARGET_ALL, 'audience_type' => CatalogAction::AUDIENCE_ALL,
            'is_active' => true, 'is_exclusive' => true,
        ], $attributes));
        foreach (['hr' => '4+1', 'de' => '5 nehmen, 4 zahlen', 'en' => 'Five for four'] as $locale => $badge) {
            $action->translations()->create(['locale' => $locale, 'title' => 'Offer', 'badge' => $badge]);
        }

        return $action;
    }

    private function quote(string $country): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => 'HR', 'shipping_country_code' => $country, 'ship_to_different_address' => 1,
            'shipping_method_code' => 'standard', 'payment_method_code' => 'corvuspay',
        ]))->assertOk();
    }
}
