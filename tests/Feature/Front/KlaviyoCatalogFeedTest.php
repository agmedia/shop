<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Settings\Local\Language;
use App\Models\Settings\Local\TaxRate;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KlaviyoCatalogFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
        Cache::flush();
        config([
            'app.url' => 'http://shop.test',
            'storefront_compliance.catalog_base_url' => 'https://bali.hr',
            'storefront_compliance.catalog_refresh_hours' => 3,
        ]);
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', true);
        TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25,
            'rate_type' => 'percent', 'is_default' => true, 'is_active' => true,
        ]);
    }

    public function test_guest_can_read_german_and_austrian_catalogs_with_destination_prices_and_public_links(): void
    {
        $product = $this->product();

        foreach (['de' => 119, 'at' => 120] as $country => $price) {
            $response = $this->getJson('/feeds/klaviyo-katalog-'.$country.'.json')
                ->assertOk()
                ->assertJsonCount(1)
                ->assertJsonPath('0.id', (string) $product->id)
                ->assertJsonPath('0.title', 'Testprodukt '.$product->id)
                ->assertJsonPath('0.sku', $product->sku)
                ->assertJsonPath('0.currency', 'EUR')
                ->assertJsonPath('0.inventory_quantity', 10)
                ->assertJsonPath('0.inventory_policy', 1)
                ->assertJsonPath('0.link', 'https://bali.hr/product/testprodukt-'.$product->id.'?lang=de');

            $this->assertEquals($price, $response->json('0.price'));
            $this->assertStringStartsWith('https://bali.hr/', $response->json('0.image_link'));
            $this->assertSame('Beschreibung & Pflege', $response->json('0.description'));
        }
    }

    public function test_guest_following_german_catalog_link_opens_german_product_with_a_croatian_session(): void
    {
        config(['app.locale' => 'hr', 'app.fallback_locale' => 'hr']);
        Language::query()->update(['is_default' => false]);
        foreach (['hr' => 'Hrvatski', 'de' => 'Deutsch'] as $code => $name) {
            Language::query()->updateOrCreate(['code' => $code], [
                'locale' => $code === 'hr' ? 'hr_HR' : 'de_DE',
                'name' => $name,
                'native_name' => $name,
                'is_default' => $code === 'hr',
                'is_active' => true,
            ]);
        }
        $product = $this->product();
        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'hr',
            'name' => 'Hrvatski proizvod',
            'slug' => 'hrvatski-proizvod',
        ]);

        $feed = $this->withSession(['front_locale' => 'hr'])
            ->getJson('/feeds/klaviyo-katalog-de.json')->assertOk();
        $link = $feed->json('0.link');
        $this->assertStringEndsWith('?lang=de', $link);

        $this->get($link)->assertOk()
            ->assertSee('Testprodukt '.$product->id)
            ->assertSee('lang="de"', false)
            ->assertSessionHas('front_locale', 'de');

        $this->get('/contact')->assertOk()
            ->assertSee('lang="de"', false)
            ->assertSessionHas('front_locale', 'de');
    }

    public function test_catalog_omits_inactive_products_and_products_without_requested_translation_slug_or_image(): void
    {
        $included = $this->product();
        $this->product(['is_active' => false]);
        $this->product(translation: ['locale' => 'hr']);
        $this->product(translation: ['slug' => '']);
        $this->product(withImage: false);

        $this->getJson('/feeds/klaviyo-katalog-de.json')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $included->id);
    }

    public function test_catalog_keeps_cached_stock_and_price_until_three_hours_then_reads_current_values(): void
    {
        $this->travelTo(now()->startOfSecond());
        $product = $this->product();
        $this->getJson('/feeds/klaviyo-katalog-de.json')->assertOk()
            ->assertJsonPath('0.inventory_quantity', 10);

        $product->update(['base_price' => 250, 'stock_qty' => 3]);
        $this->travel(179)->minutes();
        $cached = $this->getJson('/feeds/klaviyo-katalog-de.json')->assertOk()
            ->assertJsonPath('0.inventory_quantity', 10);
        $this->assertEquals(119, $cached->json('0.price'));

        $this->travel(2)->minutes();
        $refreshed = $this->getJson('/feeds/klaviyo-katalog-de.json')->assertOk()
            ->assertJsonPath('0.inventory_quantity', 3);
        $this->assertEquals(238, $refreshed->json('0.price'));
    }

    public function test_refresh_command_immediately_updates_both_country_feeds_before_cache_expiry(): void
    {
        $product = $this->product();
        foreach (['de', 'at'] as $country) {
            $this->getJson('/feeds/klaviyo-katalog-'.$country.'.json')->assertOk()
                ->assertJsonPath('0.inventory_quantity', 10);
        }

        $product->update(['base_price' => 250, 'stock_qty' => 6]);
        $this->artisan('klaviyo:refresh-catalogs')->assertSuccessful();

        foreach (['de' => 238, 'at' => 240] as $country => $price) {
            $response = $this->getJson('/feeds/klaviyo-katalog-'.$country.'.json')->assertOk()
                ->assertJsonPath('0.inventory_quantity', 6);
            $this->assertEquals($price, $response->json('0.price'));
        }
    }

    public function test_catalog_inventory_uses_active_variant_stock_including_sold_out_variants(): void
    {
        $product = $this->product(['stock_qty' => 99]);
        $soldOut = $this->product(['stock_qty' => 99]);
        $withoutVariants = $this->product(['stock_qty' => 9]);
        $option = Option::query()->create(['code' => 'size', 'type' => 'select', 'is_active' => true]);

        foreach ([[$product, 3, true], [$product, 4, true], [$product, 50, false], [$soldOut, 0, true]] as $index => [$variantProduct, $stock, $active]) {
            $value = OptionValue::query()->create([
                'option_id' => $option->id, 'code' => 'size-'.$index, 'is_active' => true,
            ]);
            $variantProduct->optionValues()->create([
                'option_value_id' => $value->id,
                'stock_qty' => $stock,
                'is_active' => $active,
                'combination_hash' => hash('sha256', 's:'.$value->id),
            ]);
        }

        $items = collect($this->getJson('/feeds/klaviyo-katalog-de.json')->assertOk()->json())->keyBy('id');
        $this->assertSame(7, $items[(string) $product->id]['inventory_quantity']);
        $this->assertSame(0, $items[(string) $soldOut->id]['inventory_quantity']);
        $this->assertSame(9, $items[(string) $withoutVariants->id]['inventory_quantity']);
    }

    private function product(array $attributes = [], array $translation = [], bool $withImage = true): Product
    {
        $suffix = (string) str()->uuid();
        $product = Product::query()->create(array_replace([
            'code' => 'CAT-'.$suffix,
            'sku' => 'SKU-'.$suffix,
            'base_price' => 125,
            'stock_qty' => 10,
            'is_active' => true,
            'tax_rate_id' => TaxRate::query()->where('code', 'VAT25')->value('id'),
        ], $attributes));
        ProductTranslation::query()->create(array_replace([
            'product_id' => $product->id,
            'locale' => 'de',
            'name' => 'Testprodukt '.$product->id,
            'slug' => 'testprodukt-'.$product->id,
            'description' => '<p>Beschreibung &amp; Pflege</p>',
        ], $translation));

        if ($withImage) {
            $product->addMedia(UploadedFile::fake()->image('catalog-'.$product->id.'.jpg'))
                ->toMediaCollection('product_main');
        }

        return $product;
    }
}
