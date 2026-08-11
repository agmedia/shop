<?php

namespace Tests\Feature\Admin;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\User;
use App\Services\Integrations\Kipos\KiposSyncService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KiposSyncPricesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_kipos_price_update_uses_complete_extended_feed_and_updates_full_variant_prices_in_bulk(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'W7030', 99);
        $plainProduct = $this->createProduct($admin, 'W8000', 99);
        $extendedOnlyProduct = $this->createProduct($admin, 'M7066', 15.26);

        $size = $this->createOption($admin, 'size');
        $small = $this->createOptionValue($admin, $size, 's', 1);
        $medium = $this->createOptionValue($admin, $size, 'm', 2);

        $this->createProductOptionRow($admin, $product, $small, 'W7030.S', 99, 0);
        $this->createProductOptionRow($admin, $product, $medium, 'W7030.M', 99, 1);
        $this->createProductOptionRow($admin, $extendedOnlyProduct, $small, 'M7066.S', 15.26, 0);
        $this->createProductOptionRow($admin, $extendedOnlyProduct, $medium, 'M7066.XXL', 17.65, 1);

        $this->enableKiposSync();

        Http::fake([
            '*getitemsextended*' => Http::response([
                ['IDROBA' => 'W7030.S', 'IDODJEL' => 'W7030', 'IDVELICINA' => 'S', 'CIJENA_MPC' => '10,00', 'CIJENA_NAJNIZA_30DANA' => '9,50'],
                ['IDROBA' => 'W7030.M', 'IDODJEL' => 'W7030', 'IDVELICINA' => 'M', 'CIJENA_MPC' => '15,50', 'CIJENA_NAJNIZA_30DANA' => '14,00'],
                ['IDROBA' => 'W8000', 'IDODJEL' => 'W8000', 'CIJENA_MPC' => '1.234,56', 'CIJENA_NAJNIZA_30DANA' => '1.100,00'],
                ['IDROBA' => 'M7066.S', 'IDODJEL' => 'M7066', 'IDVELICINA' => 'S', 'CIJENA_MPC' => '15,99'],
                ['IDROBA' => 'M7066.XXL', 'IDODJEL' => 'M7066', 'IDVELICINA' => 'XXL', 'CIJENA_MPC' => '17,99'],
                ['IDROBA' => 'UNKNOWN', 'IDODJEL' => 'UNKNOWN', 'CIJENA_MPC' => '20,00'],
            ], 200),
            '*getitems*' => Http::response([
                ['IDROBA' => 'W7030.S', 'IDODJEL' => 'W7030', 'IDVELICINA' => 'S', 'CIJENA_MPC' => '9,00'],
            ], 200),
        ]);

        $run = app(KiposSyncService::class)->run('update_prices', $admin->id);

        $fresh = $product->fresh()->load('optionValues');
        $freshPlain = $plainProduct->fresh();
        $freshExtendedOnly = $extendedOnlyProduct->fresh()->load('optionValues');
        $rows = $fresh->optionValues->keyBy('sku');
        $extendedOnlyRows = $freshExtendedOnly->optionValues->keyBy('sku');

        $this->assertSame('success', $run->status);
        $this->assertEqualsWithDelta(10.00, (float) $fresh->base_price, 0.001);
        $this->assertEqualsWithDelta(10.00, (float) $rows->get('W7030.S')?->price_override, 0.001);
        $this->assertEqualsWithDelta(15.50, (float) $rows->get('W7030.M')?->price_override, 0.001);
        $this->assertEqualsWithDelta(1234.56, (float) $freshPlain->base_price, 0.001);
        $this->assertEqualsWithDelta(15.99, (float) $freshExtendedOnly->base_price, 0.001);
        $this->assertEqualsWithDelta(15.99, (float) $extendedOnlyRows->get('M7066.S')?->price_override, 0.001);
        $this->assertEqualsWithDelta(17.99, (float) $extendedOnlyRows->get('M7066.XXL')?->price_override, 0.001);
        $this->assertEqualsWithDelta(9.50, (float) data_get($fresh->payload, 'kipos.lowest_30_days_price'), 0.001);
        $this->assertSame(3, (int) (($run->stats ?? [])['updated_products'] ?? 0));
        $this->assertSame(4, (int) (($run->stats ?? [])['updated_variants'] ?? 0));
        $this->assertSame(1, (int) (($run->stats ?? [])['unmatched_products'] ?? 0));
        Http::assertNotSent(
            fn ($request): bool => str_contains($request->url(), 'getitems')
                && ! str_contains($request->url(), 'getitemsextended')
        );
        Http::assertSent(
            fn ($request): bool => str_contains($request->url(), 'getitemsextended')
        );
    }

    private function createProduct(User $admin, string $code, float $basePrice): Product
    {
        return Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => $basePrice,
            'stock_qty' => 0,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function createOption(User $admin, string $code): Option
    {
        return Option::query()->create([
            'code' => $code,
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function createOptionValue(User $admin, Option $option, string $code, int $sortOrder): OptionValue
    {
        return OptionValue::query()->create([
            'option_id' => $option->id,
            'code' => $code,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function createProductOptionRow(User $admin, Product $product, OptionValue $value, string $sku, float $price, int $sortOrder): void
    {
        ProductOptionValue::query()->create([
            'product_id' => $product->id,
            'option_value_id' => $value->id,
            'parent_option_value_id' => null,
            'mode' => 'single',
            'sku' => $sku,
            'stock_qty' => 0,
            'price_override' => $price,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'combination_hash' => hash('sha256', 's:'.$value->id),
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function enableKiposSync(): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_query_suffix' => 'webshop=2',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
            'kipos_sync_price_field' => 'CIJENA_MPC',
        ]);
    }
}
