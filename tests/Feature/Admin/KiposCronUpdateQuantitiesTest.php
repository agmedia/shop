<?php

namespace Tests\Feature\Admin;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KiposCronUpdateQuantitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kipos_quantity_cron_endpoint_requires_configured_token(): void
    {
        config(['services.kipos.cron_token' => null]);

        $this->getJson('/cron/kipos/update-quantities?token=anything')
            ->assertNotFound();
    }

    public function test_kipos_quantity_cron_endpoint_rejects_invalid_token(): void
    {
        config(['services.kipos.cron_token' => 'valid-token']);

        $this->getJson('/cron/kipos/update-quantities?token=wrong-token')
            ->assertForbidden();
    }

    public function test_kipos_quantity_cron_endpoint_runs_quantity_update(): void
    {
        $this->enableKiposCronSync([
            'kipos_api_query_suffix' => 'webshop=1',
        ]);

        $admin = User::factory()->create();
        $product = Product::query()->create([
            'code' => 'W7030',
            'sku' => 'W7030',
            'is_active' => true,
            'base_price' => 10,
            'stock_qty' => 0,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        Http::fake([
            '*getitemsextended*' => Http::response([
                [
                    'IDROBA' => 'W7030',
                    'IDODJEL' => 'W7030',
                ],
            ], 200),
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => 'W7030',
                    'IDODJEL' => 'W7030',
                    'ZALIHAK' => 8,
                    'IDSKL' => '200',
                ],
            ], 200),
        ]);

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.updated_products', 1);

        $this->assertSame(8, (int) $product->fresh()?->stock_qty);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'getitemsextended')
            && str_contains((string) $request->url(), 'webshop=2'));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'getZalihaK')
            && str_contains((string) $request->url(), 'webshop=2'));
    }

    public function test_kipos_quantity_cron_disables_managed_product_when_none_of_its_skus_are_in_stock_feed(): void
    {
        $this->enableKiposCronSync();

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066', 100);
        $variants = $this->createSizeVariants($admin, $product, [
            ['sku' => 'M7066.S', 'stock_qty' => 40],
            ['sku' => 'M7066.M', 'stock_qty' => 60],
            ['sku' => '', 'stock_qty' => 33],
        ]);

        $this->fakeKiposFeeds(
            catalogRows: [
                ['IDROBA' => 'M7066.S', 'IDODJEL' => 'M7066'],
                ['IDROBA' => 'M7066.M', 'IDODJEL' => 'M7066'],
                ['IDROBA' => 'W9999.S', 'IDODJEL' => 'W9999'],
            ],
            stockRows: [
                ['IDROBA' => 'W9999.S', 'IDODJEL' => 'W9999', 'ZALIHAK' => 4, 'IDSKL' => '200'],
            ],
        );

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'success');

        $freshProduct = $product->fresh();
        $freshSmall = $variants['M7066.S']->fresh();
        $freshMedium = $variants['M7066.M']->fresh();
        $freshWithoutSku = $variants['']->fresh();

        $this->assertSame(0, (int) $freshProduct->stock_qty);
        $this->assertFalse($freshProduct->is_active);
        $this->assertSame(0, (int) $freshSmall->stock_qty);
        $this->assertFalse($freshSmall->is_active);
        $this->assertSame(0, (int) $freshMedium->stock_qty);
        $this->assertFalse($freshMedium->is_active);
        $this->assertSame(33, (int) $freshWithoutSku->stock_qty);
        $this->assertTrue($freshWithoutSku->is_active);
    }

    public function test_kipos_quantity_cron_disables_missing_sibling_sku_in_present_group(): void
    {
        $this->enableKiposCronSync();

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7100', 41);
        $variants = $this->createSizeVariants($admin, $product, [
            ['sku' => 'M7100.S', 'stock_qty' => 22],
            ['sku' => 'M7100.M', 'stock_qty' => 19],
        ]);

        $this->fakeKiposFeeds(
            catalogRows: [
                ['IDROBA' => 'M7100.S', 'IDODJEL' => 'M7100'],
                ['IDROBA' => 'M7100.M', 'IDODJEL' => 'M7100'],
            ],
            stockRows: [
                ['IDROBA' => 'M7100.S', 'IDODJEL' => 'M7100', 'ZALIHAK' => 6, 'IDSKL' => '200'],
            ],
        );

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'success');

        $freshProduct = $product->fresh();
        $freshSmall = $variants['M7100.S']->fresh();
        $freshMedium = $variants['M7100.M']->fresh();

        $this->assertSame(6, (int) $freshProduct->stock_qty);
        $this->assertTrue($freshProduct->is_active);
        $this->assertSame(6, (int) $freshSmall->stock_qty);
        $this->assertTrue($freshSmall->is_active);
        $this->assertSame(0, (int) $freshMedium->stock_qty);
        $this->assertFalse($freshMedium->is_active);
    }

    public function test_kipos_quantity_cron_keeps_present_zero_and_negative_skus_active(): void
    {
        $this->enableKiposCronSync();

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7010', 10);
        $variants = $this->createSizeVariants($admin, $product, [
            ['sku' => 'M7010.S', 'stock_qty' => 5],
            ['sku' => 'M7010.M', 'stock_qty' => 5],
        ]);

        $this->fakeKiposFeeds(
            catalogRows: [
                ['IDROBA' => 'M7010.S', 'IDODJEL' => 'M7010'],
                ['IDROBA' => 'M7010.M', 'IDODJEL' => 'M7010'],
            ],
            stockRows: [
                ['IDROBA' => 'M7010.S', 'IDODJEL' => 'M7010', 'ZALIHAK' => 0, 'IDSKL' => '200'],
                ['IDROBA' => 'M7010.M', 'IDODJEL' => 'M7010', 'ZALIHAK' => -4, 'IDSKL' => '200'],
            ],
        );

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'success');

        $freshProduct = $product->fresh();
        $freshSmall = $variants['M7010.S']->fresh();
        $freshMedium = $variants['M7010.M']->fresh();

        $this->assertSame(0, (int) $freshProduct->stock_qty);
        $this->assertTrue($freshProduct->is_active);
        $this->assertSame(0, (int) $freshSmall->stock_qty);
        $this->assertTrue($freshSmall->is_active);
        $this->assertSame(0, (int) $freshMedium->stock_qty);
        $this->assertTrue($freshMedium->is_active);
    }

    public function test_kipos_quantity_cron_fails_without_mutation_when_stock_feed_is_empty(): void
    {
        $this->enableKiposCronSync();

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066', 100);
        $variants = $this->createSizeVariants($admin, $product, [
            ['sku' => 'M7066.S', 'stock_qty' => 40],
            ['sku' => 'M7066.M', 'stock_qty' => 60],
        ]);

        $this->fakeKiposFeeds(
            catalogRows: [
                ['IDROBA' => 'M7066.S', 'IDODJEL' => 'M7066'],
                ['IDROBA' => 'M7066.M', 'IDODJEL' => 'M7066'],
            ],
            stockRows: [],
        );

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'failed');

        $this->assertCatalogStateUnchanged($product, $variants);
    }

    public function test_kipos_quantity_cron_fails_without_mutation_when_catalog_feed_is_empty(): void
    {
        $this->enableKiposCronSync();

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066', 100);
        $variants = $this->createSizeVariants($admin, $product, [
            ['sku' => 'M7066.S', 'stock_qty' => 40],
            ['sku' => 'M7066.M', 'stock_qty' => 60],
        ]);

        $this->fakeKiposFeeds(
            catalogRows: [],
            stockRows: [
                ['IDROBA' => 'M7066.S', 'IDODJEL' => 'M7066', 'ZALIHAK' => 4, 'IDSKL' => '200'],
            ],
        );

        $this->getJson('/cron/kipos/update-quantities?token=valid-token')
            ->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'failed');

        $this->assertCatalogStateUnchanged($product, $variants);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enableKiposCronSync(array $overrides = []): void
    {
        config(['services.kipos.cron_token' => 'valid-token']);

        app(SystemSettingsService::class)->putMany(array_merge([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_query_suffix' => 'webshop=2',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
            'kipos_sync_stock_warehouse_ids' => '200',
        ], $overrides));
    }

    private function createProduct(User $admin, string $code, int $stockQty): Product
    {
        return Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => 10,
            'stock_qty' => $stockQty,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * @param  list<array{sku: string, stock_qty: int}>  $variants
     * @return array<string, ProductOptionValue>
     */
    private function createSizeVariants(User $admin, Product $product, array $variants): array
    {
        $option = Option::query()->create([
            'code' => 'size',
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $product->options()->sync([
            $option->id => [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $rows = [];

        foreach ($variants as $sortOrder => $variant) {
            $label = str($variant['sku'])->afterLast('.')->lower()->toString();
            $label = $label !== '' ? $label : 'value-'.($sortOrder + 1);
            $value = OptionValue::query()->create([
                'option_id' => $option->id,
                'code' => $label,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'payload' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $rows[$variant['sku']] = ProductOptionValue::query()->create([
                'product_id' => $product->id,
                'option_value_id' => $value->id,
                'parent_option_value_id' => null,
                'mode' => 'single',
                'sku' => $variant['sku'],
                'stock_qty' => $variant['stock_qty'],
                'price_override' => 0,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'combination_hash' => hash('sha256', 's:'.$value->id),
                'payload' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $catalogRows
     * @param  list<array<string, mixed>>  $stockRows
     */
    private function fakeKiposFeeds(array $catalogRows, array $stockRows): void
    {
        Http::fake([
            '*getitemsextended*' => Http::response($catalogRows, 200),
            '*getZalihaK*' => Http::response($stockRows, 200),
        ]);
    }

    /**
     * @param  array<string, ProductOptionValue>  $variants
     */
    private function assertCatalogStateUnchanged(Product $product, array $variants): void
    {
        $freshProduct = $product->fresh();
        $freshSmall = $variants['M7066.S']->fresh();
        $freshMedium = $variants['M7066.M']->fresh();

        $this->assertSame(100, (int) $freshProduct->stock_qty);
        $this->assertTrue($freshProduct->is_active);
        $this->assertSame(40, (int) $freshSmall->stock_qty);
        $this->assertTrue($freshSmall->is_active);
        $this->assertSame(60, (int) $freshMedium->stock_qty);
        $this->assertTrue($freshMedium->is_active);
    }
}
