<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Settings\Local\TaxRate;
use App\Services\Front\CartService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutDeliveryEstimateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('destinations')]
    public function test_delivery_estimate_follows_the_effective_shipping_destination(
        string $device,
        string $locale,
        string $billingCountry,
        string $shippingCountry,
        bool $separateShipping,
        bool $visible
    ): void {
        config(['app.locale' => $locale, 'app.fallback_locale' => 'en']);
        app()->setLocale($locale);
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);
        $rate = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'DELIVERY', 'sku' => 'DELIVERY-1', 'base_price' => 25,
            'stock_qty' => 10, 'is_active' => true, 'tax_rate_id' => $rate->id,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => $locale, 'name' => 'Delivery product', 'slug' => 'delivery-product',
        ]);
        app(CartService::class)->add($product);

        $response = $this->withSession(['_old_input' => [
            'billing_country_code' => $billingCountry,
            'shipping_country_code' => $shippingCountry,
            'ship_to_different_address' => $separateShipping ? '1' : '0',
            'use_billing_for_shipping' => $separateShipping ? '0' : '1',
        ]])->withHeader('User-Agent', $device === 'mobile'
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)')->get('/checkout')->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $notes = $xpath->query('//*[@data-de-at-delivery-note]');
        $this->assertCount(1, $notes);
        $this->assertSame(! $visible, $notes->item(0)->hasAttribute('hidden'));
        $expected = match ($locale) {
            'hr' => 'Rok dostave: 5–7 radnih dana od evidentiranja uplate.',
            'en' => 'Delivery time: 5–7 working days after payment is received.',
            default => 'Lieferzeit: 5–7 Werktage ab Zahlungseingang',
        };
        $this->assertSame($expected, $notes->item(0)->textContent);
        if ($locale === 'de' && $device === 'desktop') {
            $response->assertSee('Kostenloser Versand innerhalb Kroatiens ab 49,99 €')
                ->assertDontSee('Kostenloser Versand ab 49,99 €');
        }
    }

    public static function destinations(): array
    {
        $cases = [];
        foreach (['desktop', 'mobile'] as $device) {
            foreach ([
                'German billing used for delivery' => ['de', 'DE', 'HR', false, true],
                'Austrian billing used for delivery' => ['de', 'AT', 'HR', false, true],
                'Separate German delivery' => ['de', 'HR', 'DE', true, true],
                'Separate Austrian delivery' => ['de', 'HR', 'AT', true, true],
                'Croatian delivery with German billing' => ['de', 'DE', 'HR', true, false],
                'Croatian delivery' => ['de', 'HR', 'DE', false, false],
                'Croatian language for German delivery' => ['hr', 'DE', 'HR', false, true],
                'English language for Austrian delivery' => ['en', 'HR', 'AT', true, true],
            ] as $name => $case) {
                $cases[$device.' '.$name] = [$device, ...$case];
            }
        }

        return $cases;
    }
}
