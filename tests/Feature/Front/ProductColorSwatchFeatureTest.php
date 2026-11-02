<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Settings\Local\Language;
use App\Services\Front\ProductColorVariantService;
use App\Services\Settings\SystemSettingsService;
use App\Support\ProductColorSwatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductColorSwatchFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Product $nudeProduct;

    private Option $color;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en', 'app.fallback_locale' => 'en']);
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);
        foreach (['hr', 'en', 'de'] as $index => $locale) {
            Language::query()->updateOrCreate(['code' => $locale], ['name' => $locale, 'is_active' => true, 'is_default' => $index === 0]);
        }
        $category = Category::query()->create(['code' => 'swatch-category', 'scope' => Category::SCOPE_CATALOG, 'is_active' => true]);
        foreach (['hr', 'en', 'de'] as $locale) {
            $category->translations()->create(['scope' => Category::SCOPE_CATALOG, 'locale' => $locale, 'name' => 'Category', 'slug' => 'swatch-category-'.$locale]);
        }
        $this->color = Option::query()->create(['code' => 'color', 'type' => Option::TYPE_SELECT, 'is_active' => true, 'payload' => [Option::PAYLOAD_SHOW_ON_PRODUCT_PAGE => false]]);
        foreach (['hr' => 'Boja', 'en' => 'Color', 'de' => 'Farbe'] as $locale => $name) {
            $this->color->translations()->create(['locale' => $locale, 'name' => $name, 'slug' => 'color-'.$locale]);
        }

        foreach ([
            ['boja-koze', ['hr' => 'Boja kože', 'en' => 'Nude', 'de' => 'Hautfarbe'], null],
            ['crna', ['hr' => 'Crna', 'en' => 'Black', 'de' => 'Schwarz'], null],
            ['bijela', ['hr' => 'Bijela', 'en' => 'White', 'de' => 'Weiß'], null],
            ['special-500', ['hr' => 'Poseban uzorak', 'en' => 'Special pattern', 'de' => 'Sondermuster'], null],
            ['crvena', ['hr' => 'Crvena', 'en' => 'Red', 'de' => 'Rot'], 'catalog/option-values/swatch/real-upload.png'],
        ] as $index => [$code, $names, $swatch]) {
            $product = Product::query()->create([
                'code' => 'swatch-product-'.$index, 'sku' => 'SWATCH-'.$index,
                'is_active' => true, 'base_price' => 20, 'stock_qty' => 10,
                'payload' => ['color_variant_group' => 'swatch-regression-group'],
            ]);
            foreach ($names as $locale => $name) {
                $product->translations()->create(['locale' => $locale, 'name' => 'Slip - '.$name, 'slug' => 'swatch-product-'.$index.'-'.$locale]);
            }
            $product->categories()->attach($category->id, ['is_primary' => true]);
            $this->color->products()->attach($product->id);
            $value = $this->color->values()->create(['code' => $code, 'is_active' => true, 'sort_order' => $index, 'payload' => $swatch ? ['swatch_image_path' => $swatch] : null]);
            foreach ($names as $locale => $name) {
                $value->translations()->create(['locale' => $locale, 'name' => $name, 'slug' => $code.'-'.$locale]);
            }
            $product->optionValues()->create(['option_value_id' => $value->id, 'mode' => 'single', 'combination_hash' => sha1('single:'.$value->id), 'is_active' => true, 'stock_qty' => 10]);
            if ($index === 0) {
                $this->nudeProduct = $product;
            }
        }
    }

    #[DataProvider('localizedDevices')]
    public function test_pdp_colors_stay_true_to_source_on_desktop_and_mobile(string $locale, string $userAgent): void
    {
        $names = $locale === 'de' ? ['Hautfarbe', 'Schwarz', 'Weiß', 'Sondermuster', 'Rot'] : ['Boja kože', 'Crna', 'Bijela', 'Poseban uzorak', 'Crvena'];
        $response = $this->withHeaders(['User-Agent' => $userAgent])->get('/product/swatch-product-0-'.$locale.'?lang='.$locale)->assertOk();
        $response->assertViewIs(str_contains($userAgent, 'iPhone') ? 'front.mobile.products.show' : 'front.desktop.products.show');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['background:#d2a181;', 'background:#050505;', 'background:#fcfcfc;'] as $index => $expectedStyle) {
            $node = $xpath->query('//*[@data-color-variant-label="'.$names[$index].'"]/*[@data-color-variant-swatch]')->item(0);
            $this->assertNotNull($node);
            $this->assertSame($expectedStyle, $node->getAttribute('style'));
        }
        $unknown = $xpath->query('//*[@data-color-variant-label="'.$names[3].'"]/*[@data-color-variant-swatch]')->item(0);
        $this->assertSame('?', trim($unknown->textContent));
        $this->assertSame('', $unknown->getAttribute('style'));
        $uploaded = $xpath->query('//*[@data-color-variant-label="'.$names[4].'"]/*[@data-color-variant-swatch]')->item(0);
        $this->assertStringContainsString('real-upload.png', $uploaded->getAttribute('style'));
    }

    public function test_variant_identity_and_style_are_the_same_across_locales(): void
    {
        $expected = null;
        foreach (['hr', 'en', 'de'] as $locale) {
            $variants = app(ProductColorVariantService::class)->variantsFor($this->nudeProduct->fresh(), $locale, 'en');
            $actual = $variants->map(fn ($variant) => [$variant['color_key'], $variant['swatch_style'], $variant['swatch_image_url']])->all();
            $expected ??= $actual;
            $this->assertSame($expected, $actual);
        }
    }

    public function test_catalog_filters_use_the_same_source_styles_as_the_pdp(): void
    {
        app(SystemSettingsService::class)->put('store_product_filter_option_ids', [$this->color->id]);
        foreach (['/shop?lang=de', '/category/swatch-category-de?lang=de'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertSee('data-filter-swatch-style="background:#d2a181;"', false)
                ->assertSee('data-filter-swatch-style="background:#050505;"', false)
                ->assertSee('data-filter-swatch-style="background:#fcfcfc;"', false)
                ->assertSee('real-upload.png', false);
        }
    }

    public function test_canonical_code_precedes_a_mistranslated_label(): void
    {
        $value = OptionValue::query()->where('code', 'bijela')->firstOrFail();
        $value->translations()->where('locale', 'de')->update(['name' => 'Schwarz']);
        $this->assertSame('background:#fcfcfc;', ProductColorSwatch::style($value->fresh()));
    }

    public static function localizedDevices(): array
    {
        return [
            ['de', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'],
            ['de', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'],
            ['hr', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'],
            ['hr', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'],
        ];
    }
}
