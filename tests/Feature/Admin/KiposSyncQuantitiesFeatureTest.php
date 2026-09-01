<?php

namespace Tests\Feature\Admin;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Integrations\KiposSyncRun;
use App\Models\User;
use App\Services\Integrations\Kipos\KiposSyncService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class KiposSyncQuantitiesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_kipos_quantity_update_syncs_parent_stock_and_matching_size_rows(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'W7030');

        $size = $this->createOption($admin, 'size', 'Size', 'size');
        $small = $this->createOptionValue($admin, $size, 's', 'S', 's', 1);
        $medium = $this->createOptionValue($admin, $size, 'm', 'M', 'm', 2);
        $large = $this->createOptionValue($admin, $size, 'l', 'L', 'l', 3);

        $product->options()->sync([
            $size->id => [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createProductOptionRow($admin, $product, $small, 'W7030.S', 99, 0);
        $this->createProductOptionRow($admin, $product, $medium, 'W7030.M', 99, 1);
        $this->createProductOptionRow($admin, $product, $large, 'W7030.L', 99, 2);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
            'kipos_api_query_suffix' => 'webshop=1',
        ]);
        Cache::put('front:catalog:last-modified-ts', 123, now()->addMinutes(2));
        Cache::put('front:product:last-modified:'.$product->id, 123, now()->addMinutes(2));

        Http::fake([
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => 'W7030.S',
                    'IDODJEL' => 'W7030',
                    'ZALIHAK' => 2,
                    'IDSKL' => '100',
                ],
                [
                    'IDROBA' => 'W7030.M',
                    'IDODJEL' => 'W7030',
                    'ZALIHAK' => 3,
                    'IDSKL' => '100',
                ],
                [
                    'IDROBA' => 'W7030.L',
                    'IDODJEL' => 'W7030',
                    'ZALIHAK' => 0,
                    'IDSKL' => '100',
                ],
            ], 200),
        ]);

        $run = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $fresh = $product->fresh()->load('optionValues');
        $rows = $fresh->optionValues->keyBy('sku');

        $this->assertSame('success', $run->status);
        $this->assertSame(5, (int) $fresh->stock_qty);
        $this->assertSame(2, (int) $rows->get('W7030.S')?->stock_qty);
        $this->assertSame(3, (int) $rows->get('W7030.M')?->stock_qty);
        $this->assertSame(0, (int) $rows->get('W7030.L')?->stock_qty);
        $this->assertSame(1, (int) (($run->stats ?? [])['updated_products'] ?? 0));
        $this->assertSame(3, (int) (($run->stats ?? [])['updated_variants'] ?? 0));
        $this->assertFalse(Cache::has('front:catalog:last-modified-ts'));
        $this->assertFalse(Cache::has('front:product:last-modified:'.$product->id));
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'getZalihaK')
            && str_contains((string) $request->url(), 'idskl=100')
            && str_contains((string) $request->url(), 'webshop=2'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'getitemsextended'));
    }

    public function test_kipos_quantity_update_uses_extended_stock_when_no_warehouse_filter_is_set(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'W7037');

        $size = $this->createOption($admin, 'size', 'Size', 'size');
        $fourXl = $this->createOptionValue($admin, $size, '4xl', '4XL', '4xl', 1);

        $product->options()->sync([
            $size->id => [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createProductOptionRow($admin, $product, $fourXl, 'W7037.4XL', 0, 0);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '',
        ]);

        Http::fake([
            '*getitemsextended*' => Http::response([
                [
                    'IDROBA' => 'W7037.4XL',
                    'IDODJEL' => 'W7037',
                    'ZALIHAK' => 20,
                    'IDVELICINA' => '4XL',
                ],
            ], 200),
            '*getitems*' => Http::response([
                [
                    'IDROBA' => 'W7037.4XL',
                    'IDODJEL' => 'W7037',
                    'ZALIHAK' => 0,
                    'IDVELICINA' => '4XL',
                ],
            ], 200),
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => 'W7037.4XL',
                    'ZALIHAK' => 0,
                    'IDSKL' => '100',
                ],
            ], 200),
        ]);

        $run = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $fresh = $product->fresh()->load('optionValues');

        $this->assertSame('success', $run->status);
        $this->assertSame(20, (int) $fresh->stock_qty);
        $this->assertSame(20, (int) $fresh->optionValues->firstWhere('sku', 'W7037.4XL')?->stock_qty);
    }

    public function test_kipos_quantity_update_never_fetches_the_catalog_and_deactivates_missing_products_and_skus(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066');
        $product->update([
            'stock_qty' => 100,
            'payload' => [
                'kipos' => [
                    'department_code' => 'M7066',
                ],
            ],
        ]);

        $this->createSizeRows($admin, $product, [
            ['label' => 'S', 'sku' => 'M7066.S', 'stock_qty' => 100],
            ['label' => 'M', 'sku' => 'M7066.M', 'stock_qty' => 75],
            ['label' => 'No SKU', 'sku' => '', 'stock_qty' => 33],
        ]);

        $partiallyPresentProduct = $this->createProduct($admin, 'M7010');
        $partiallyPresentProduct->update([
            'stock_qty' => 80,
            'payload' => [
                'kipos' => [
                    'department_code' => 'M7010',
                ],
            ],
        ]);

        $size = Option::query()->where('code', 'size')->firstOrFail();
        $partialSmall = $this->createOptionValue($admin, $size, 'partial-s', 'S', 'partial-s', 10);
        $partialMedium = $this->createOptionValue($admin, $size, 'partial-m', 'M', 'partial-m', 11);

        $partiallyPresentProduct->options()->sync([
            $size->id => [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createProductOptionRow($admin, $partiallyPresentProduct, $partialSmall, 'M7010.S', 40, 0);
        $this->createProductOptionRow($admin, $partiallyPresentProduct, $partialMedium, 'M7010.M', 40, 1);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => 'M7010.S',
                    'ZALIHAK' => 6,
                    'IDSKL' => '100',
                ],
            ], 200),
        ]);

        $run = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $fresh = $product->fresh()->load('optionValues');
        $rows = $fresh->optionValues->keyBy('sku');
        $freshPartiallyPresent = $partiallyPresentProduct->fresh()->load('optionValues');
        $partiallyPresentRows = $freshPartiallyPresent->optionValues->keyBy('sku');

        $this->assertSame('success', $run->status);
        $this->assertSame(0, (int) $fresh->stock_qty);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(0, (int) $rows->get('M7066.S')?->stock_qty);
        $this->assertFalse($rows->get('M7066.S')?->is_active);
        $this->assertSame(0, (int) $rows->get('M7066.M')?->stock_qty);
        $this->assertFalse($rows->get('M7066.M')?->is_active);
        $this->assertSame(33, (int) $rows->get('')?->stock_qty);
        $this->assertTrue($rows->get('')?->is_active);
        $this->assertSame(6, (int) $freshPartiallyPresent->stock_qty);
        $this->assertTrue($freshPartiallyPresent->is_active);
        $this->assertSame(6, (int) $partiallyPresentRows->get('M7010.S')?->stock_qty);
        $this->assertTrue($partiallyPresentRows->get('M7010.S')?->is_active);
        $this->assertSame(0, (int) $partiallyPresentRows->get('M7010.M')?->stock_qty);
        $this->assertFalse($partiallyPresentRows->get('M7010.M')?->is_active);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'getZalihaK')
            && str_contains((string) $request->url(), 'webshop=2'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'getitemsextended'));
    }

    public function test_kipos_quantity_update_deactivates_only_missing_sibling_and_keeps_present_zero_or_negative_skus_active(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7010');
        $product->update(['stock_qty' => 99]);

        $this->createSizeRows($admin, $product, [
            ['label' => 'S', 'sku' => 'M7010.S', 'stock_qty' => 40],
            ['label' => 'M', 'sku' => 'M7010.M', 'stock_qty' => 40],
            ['label' => 'L', 'sku' => 'M7010.L', 'stock_qty' => 40],
            ['label' => 'X', 'sku' => 'M7010.X', 'stock_qty' => 40],
        ]);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
        ]);

        Http::fake([
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => 'M7010.S',
                    'IDODJEL' => 'M7010',
                    'ZALIHAK' => 6,
                    'IDSKL' => '100',
                ],
                [
                    'IDROBA' => 'M7010.M',
                    'IDODJEL' => 'M7010',
                    'ZALIHAK' => 0,
                    'IDSKL' => '100',
                ],
                [
                    'IDROBA' => 'M7010.L',
                    'IDODJEL' => 'M7010',
                    'ZALIHAK' => -4,
                    'IDSKL' => '100',
                ],
            ], 200),
        ]);

        $run = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $fresh = $product->fresh()->load('optionValues');
        $rows = $fresh->optionValues->keyBy('sku');

        $this->assertSame('success', $run->status);
        $this->assertSame(6, (int) $fresh->stock_qty);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(6, (int) $rows->get('M7010.S')?->stock_qty);
        $this->assertTrue($rows->get('M7010.S')?->is_active);
        $this->assertSame(0, (int) $rows->get('M7010.M')?->stock_qty);
        $this->assertTrue($rows->get('M7010.M')?->is_active);
        $this->assertSame(0, (int) $rows->get('M7010.L')?->stock_qty);
        $this->assertTrue($rows->get('M7010.L')?->is_active);
        $this->assertSame(0, (int) $rows->get('M7010.X')?->stock_qty);
        $this->assertFalse($rows->get('M7010.X')?->is_active);
    }

    public function test_kipos_quantity_update_reactivates_only_stock_feed_disabled_records_when_their_skus_return(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066');
        $product->update(['stock_qty' => 100]);

        $this->createSizeRows($admin, $product, [
            ['label' => 'S', 'sku' => 'M7066.S', 'stock_qty' => 100],
            ['label' => 'M', 'sku' => 'M7066.M', 'stock_qty' => 75],
        ]);

        $manualInactive = $product->fresh()->optionValues->firstWhere('sku', 'M7066.M');
        $manualInactive?->update(['is_active' => false]);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
        ]);

        Http::fake([
            '*getZalihaK*' => Http::sequence()
                ->push([
                    [
                        'IDROBA' => 'W7030.S',
                        'IDODJEL' => 'W7030',
                        'ZALIHAK' => 8,
                        'IDSKL' => '100',
                    ],
                ], 200)
                ->push([
                    [
                        'IDROBA' => 'M7066.S',
                        'IDODJEL' => 'M7066',
                        'ZALIHAK' => 7,
                        'IDSKL' => '100',
                    ],
                    [
                        'IDROBA' => 'M7066.M',
                        'IDODJEL' => 'M7066',
                        'ZALIHAK' => 5,
                        'IDSKL' => '100',
                    ],
                ], 200),
        ]);

        $missingRun = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $missingProduct = $product->fresh()->load('optionValues');
        $missingRows = $missingProduct->optionValues->keyBy('sku');
        $missingSmall = $missingRows->get('M7066.S');
        $missingManualInactive = $missingRows->get('M7066.M');

        $this->assertFalse($missingProduct->is_active);
        $this->assertTrue((bool) data_get($missingProduct->payload, 'kipos.stock_feed_missing'));
        $this->assertTrue((bool) data_get($missingProduct->payload, 'kipos.stock_feed_restore_active'));
        $this->assertNotNull(data_get($missingProduct->payload, 'kipos.stock_feed_missing_since'));
        $this->assertFalse($missingSmall?->is_active);
        $this->assertTrue((bool) data_get($missingSmall?->payload, 'kipos.stock_feed_missing'));
        $this->assertTrue((bool) data_get($missingSmall?->payload, 'kipos.stock_feed_restore_active'));
        $this->assertFalse($missingManualInactive?->is_active);
        $this->assertTrue((bool) data_get($missingManualInactive?->payload, 'kipos.stock_feed_missing'));
        $this->assertFalse((bool) data_get($missingManualInactive?->payload, 'kipos.stock_feed_restore_active'));
        $this->assertSame(1, (int) (($missingRun->stats ?? [])['disabled_products'] ?? 0));
        $this->assertSame(1, (int) (($missingRun->stats ?? [])['disabled_variants'] ?? 0));

        $restoredRun = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $restoredProduct = $product->fresh()->load('optionValues');
        $restoredRows = $restoredProduct->optionValues->keyBy('sku');
        $restoredSmall = $restoredRows->get('M7066.S');
        $restoredManualInactive = $restoredRows->get('M7066.M');

        $this->assertSame(12, (int) $restoredProduct->stock_qty);
        $this->assertTrue($restoredProduct->is_active);
        $this->assertTrue($restoredSmall?->is_active);
        $this->assertSame(7, (int) $restoredSmall?->stock_qty);
        $this->assertFalse($restoredManualInactive?->is_active);
        $this->assertSame(5, (int) $restoredManualInactive?->stock_qty);

        foreach ([$restoredProduct, $restoredSmall, $restoredManualInactive] as $restoredRecord) {
            $kiposPayload = (array) data_get($restoredRecord?->payload, 'kipos', []);

            $this->assertArrayNotHasKey('stock_feed_missing', $kiposPayload);
            $this->assertArrayNotHasKey('stock_feed_missing_since', $kiposPayload);
            $this->assertArrayNotHasKey('stock_feed_restore_active', $kiposPayload);
        }

        $this->assertSame(1, (int) (($restoredRun->stats ?? [])['reactivated_products'] ?? 0));
        $this->assertSame(1, (int) (($restoredRun->stats ?? [])['reactivated_variants'] ?? 0));
    }

    public function test_kipos_quantity_update_fails_without_mutation_when_stock_feed_is_empty(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7066');
        $product->update(['stock_qty' => 100]);

        $this->createSizeRows($admin, $product, [
            ['label' => 'S', 'sku' => 'M7066.S', 'stock_qty' => 100],
        ]);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
        ]);

        Http::fake([
            '*getZalihaK*' => Http::response([], 200),
        ]);

        $caughtException = null;

        try {
            app(KiposSyncService::class)->run('update_quantities', $admin->id);
        } catch (RuntimeException $exception) {
            $caughtException = $exception;
        }

        $this->assertNotNull($caughtException, 'Empty Kipos stock feed must fail the quantity sync.');
        $this->assertNotSame('', $caughtException->getMessage());

        $fresh = $product->fresh()->load('optionValues');
        $row = $fresh->optionValues->firstWhere('sku', 'M7066.S');

        $this->assertSame(100, (int) $fresh->stock_qty);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(100, (int) $row?->stock_qty);
        $this->assertTrue($row?->is_active);
    }

    public function test_kipos_quantity_update_rejects_a_nonempty_feed_below_seventy_percent_of_previous_successful_source_skus_without_mutation(): void
    {
        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'W9001');
        $product->update(['stock_qty' => 50]);

        $this->createSizeRows($admin, $product, [
            ['label' => 'S', 'sku' => 'W9001.S', 'stock_qty' => 50],
        ]);

        $this->enableKiposSync([
            'kipos_sync_stock_warehouse_ids' => '100',
        ]);

        $completeStockRows = [];

        foreach (range(1, 10) as $index) {
            $department = 'W'.(9000 + $index);
            $sku = $department.'.S';

            $completeStockRows[] = [
                'IDROBA' => $sku,
                'IDODJEL' => $department,
                'ZALIHAK' => $index === 1 ? 12 : 1,
                'IDSKL' => '100',
            ];
        }

        $truncatedStockRows = array_slice($completeStockRows, 0, 6);
        $truncatedStockRows[0]['ZALIHAK'] = 2;

        Http::fake([
            '*getZalihaK*' => Http::sequence()
                ->push($completeStockRows, 200)
                ->push($truncatedStockRows, 200),
        ]);

        $successfulRun = app(KiposSyncService::class)->run('update_quantities', $admin->id);

        $this->assertSame('success', $successfulRun->status);
        $this->assertSame(10, (int) (($successfulRun->stats ?? [])['source_skus'] ?? 0));

        $expectedProductState = $product->fresh()->only([
            'stock_qty',
            'is_active',
            'payload',
            'updated_by',
        ]);
        $expectedVariantState = $product->fresh()
            ->optionValues()
            ->firstWhere('sku', 'W9001.S')
            ?->only([
                'stock_qty',
                'is_active',
                'payload',
                'updated_by',
            ]);

        $caughtException = null;

        try {
            app(KiposSyncService::class)->run('update_quantities', $admin->id);
        } catch (RuntimeException $exception) {
            $caughtException = $exception;
        }

        $this->assertNotNull(
            $caughtException,
            'A stock feed below 70% of the previous successful source_skus baseline must fail.'
        );
        $this->assertStringContainsString('dropped from 10 to 6 SKUs', $caughtException->getMessage());

        $failedRun = KiposSyncRun::query()
            ->where('action_key', 'update_quantities')
            ->latest('id')
            ->first();
        $freshProduct = $product->fresh();
        $freshVariant = $freshProduct->optionValues()->firstWhere('sku', 'W9001.S');

        $this->assertSame('failed', $failedRun?->status);
        $this->assertSame($expectedProductState, $freshProduct->only(array_keys($expectedProductState)));
        $this->assertSame($expectedVariantState, $freshVariant?->only(array_keys($expectedVariantState ?? [])));
    }

    private function createProduct(User $admin, string $code): Product
    {
        $product = Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => 10,
            'stock_qty' => 0,
            'payload' => [
                'kipos' => [
                    'department_code' => $code,
                ],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'hr',
            'name' => 'Test '.$code,
            'slug' => 'test-'.strtolower($code),
            'excerpt' => null,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'payload' => null,
        ]);

        return $product;
    }

    private function createOption(User $admin, string $code, string $name, string $slug): Option
    {
        $option = Option::query()->create([
            'code' => $code,
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $option->translations()->create([
            'locale' => 'hr',
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'payload' => null,
        ]);

        return $option;
    }

    private function createOptionValue(User $admin, Option $option, string $code, string $name, string $slug, int $sortOrder): OptionValue
    {
        $value = OptionValue::query()->create([
            'option_id' => $option->id,
            'code' => $code,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $value->translations()->create([
            'locale' => 'hr',
            'name' => $name,
            'slug' => $slug,
            'payload' => null,
        ]);

        return $value;
    }

    private function createProductOptionRow(User $admin, Product $product, OptionValue $value, string $sku, int $stockQty, int $sortOrder): void
    {
        ProductOptionValue::query()->create([
            'product_id' => $product->id,
            'option_value_id' => $value->id,
            'parent_option_value_id' => null,
            'mode' => 'single',
            'sku' => $sku,
            'stock_qty' => $stockQty,
            'price_override' => 0,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'combination_hash' => hash('sha256', 's:'.$value->id),
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * @param  list<array{label:string, sku:string, stock_qty:int}>  $rows
     */
    private function createSizeRows(User $admin, Product $product, array $rows): void
    {
        $size = $this->createOption($admin, 'size', 'Size', 'size');

        $product->options()->sync([
            $size->id => [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ($rows as $index => $row) {
            $value = $this->createOptionValue(
                $admin,
                $size,
                'value-'.($index + 1),
                $row['label'],
                'value-'.($index + 1),
                $index + 1
            );

            $this->createProductOptionRow(
                $admin,
                $product,
                $value,
                $row['sku'],
                $row['stock_qty'],
                $index
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enableKiposSync(array $overrides = []): void
    {
        app(SystemSettingsService::class)->putMany(array_merge([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_query_suffix' => 'webshop=2',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
        ], $overrides));
    }
}
