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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KiposNightlyCatalogSyncFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_nightly_sync_imports_webshop_products_reconciles_and_sorts_sizes_and_preserves_curated_content(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $size = $this->createOption($admin, 'size', true);
        $color = $this->createOption($admin, 'color', false);
        $small = $this->createOptionValue($admin, $size, 'S', 'S', 2);
        $fourExtraLarge = $this->createOptionValue($admin, $size, '4XL', '4XL', 8);
        $white = $this->createOptionValue($admin, $color, 'bijela', 'Bijela', 1);
        $navy = $this->createOptionValue($admin, $color, 'tamno-plava', 'Tamno plava', 2);

        $whiteProduct = $this->createProduct(
            $admin,
            'W7002',
            'Ženske gaće Bikini - Bijele',
            'bikini-bijele',
            ['source' => ['mpn' => 'ŽBIK']]
        );
        $navyProduct = $this->createProduct(
            $admin,
            'W7042',
            'Ručno uređen W7042 naziv bez boje',
            'bikini-tamno-plave'
        );

        $this->attachOptionRow($whiteProduct, $small, 'W7002.S', 4, 0);
        $this->attachFilterRow($whiteProduct, $color, $white);
        $this->attachOptionRow($navyProduct, $fourExtraLarge, 'W7042.4XL', 1, 0);

        $baseRows = [
            $this->itemRow('W7002.S', 'W7002', 'S', 'Kipos overwrite attempt', 4),
            $this->itemRow('W7042.4XL', 'W7042', '4XL', 'Kipos overwrite attempt', 5),
            $this->itemRow('W7042.L', 'W7042', 'L', 'Kipos overwrite attempt', 6),
            $this->itemRow('W7042.M', 'W7042', 'M', 'Kipos overwrite attempt', 7),
            $this->itemRow('W7042R.M', 'W7042', 'M', 'Duplicate non-canonical size', 99),
            $this->itemRow('W7042.S', 'W7042', 'S', 'Kipos overwrite attempt', 8),
            $this->itemRow('W7042.XL', 'W7042', 'XL', 'Kipos overwrite attempt', 9),
            $this->itemRow('W7042.XXL', 'W7042', 'XXL', 'Kipos overwrite attempt', 10),
            $this->itemRow('W7042.XXXL', 'W7042', 'XXXL', 'Kipos overwrite attempt', 11),
            $this->itemRow('W8000', 'W8000', '', 'New webshop product', 3),
        ];
        $extendedRows = array_map(
            static fn (array $row): array => array_merge($row, ['CIJENA_MPC' => '19,90']),
            $baseRows
        );
        $extendedRows[] = $this->itemRow('W9999', 'W9999', '', 'Extended-only product', 99);

        $stockRows = array_map(
            static fn (array $row): array => array_merge($row, ['IDSKL' => '200']),
            $baseRows
        );

        $this->enableKiposSync($size);
        Http::fake([
            '*getZalihaK*' => Http::response($stockRows, 200),
            '*getitemsextended*' => Http::response($extendedRows, 200),
            '*getitems*' => Http::response($baseRows, 200),
            '*getOdjelSlike*' => Http::response([
                $this->imageRow('W8000', 'http://images.test/W8000'),
            ], 200),
            'http://images.test/W8000' => Http::response($this->tinyPng(), 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $run = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'getitemsextended'));

        $this->assertSame('success', $run->status, (string) $run->error_message);
        $this->assertDatabaseHas('products', ['code' => 'W8000']);
        $this->assertDatabaseMissing('products', ['code' => 'W9999']);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $navyProduct->id,
            'locale' => 'hr',
            'name' => 'Ručno uređen W7042 naziv bez boje',
            'slug' => 'bikini-tamno-plave',
            'description' => 'Kurirani zajednički opis.',
        ]);

        $orderedSizes = ProductOptionValue::query()
            ->where('product_id', $navyProduct->id)
            ->where('mode', 'single')
            ->with('optionValue')
            ->orderBy('sort_order')
            ->get()
            ->pluck('optionValue.code')
            ->all();

        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL'], $orderedSizes);
        $this->assertSame(56, (int) $navyProduct->fresh()->stock_qty);
        $this->assertDatabaseHas('catalog_product_option_values', [
            'product_id' => $navyProduct->id,
            'sku' => 'W7042.M',
        ]);
        $this->assertDatabaseMissing('catalog_product_option_values', [
            'product_id' => $navyProduct->id,
            'sku' => 'W7042R.M',
        ]);
        $this->assertDatabaseHas('catalog_product_option_values', [
            'product_id' => $whiteProduct->id,
            'option_value_id' => $white->id,
            'mode' => 'filter',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('catalog_product_option_values', [
            'product_id' => $navyProduct->id,
            'option_value_id' => $navy->id,
            'mode' => 'filter',
            'is_active' => true,
        ]);
        $this->assertSame(1, (int) data_get($run->stats, 'inferred_color_values'));
        $this->assertSame(1, (int) data_get($run->stats, 'created'));
        $this->assertSame(1, (int) data_get($run->stats, 'images.updated_products'));
        $this->assertSame(0, (int) data_get($run->stats, 'images.still_pending'));

        $imported = Product::query()->where('code', 'W8000')->firstOrFail();
        $this->assertFalse($imported->is_active);
        $this->assertNotNull($imported->getFirstMedia('product_main'));
        $this->assertFalse((bool) data_get($imported->payload, 'kipos.image_activation_pending', false));
        $this->assertNotNull(data_get($imported->payload, 'kipos.nightly_imported_at'));
    }

    public function test_nightly_sync_keeps_new_products_without_remote_images_hidden_and_retries_without_specific_lookups(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $size = $this->createOption($admin, 'size', true);
        $rows = [$this->itemRow('W8001', 'W8001', '', 'New product awaiting image', 5)];
        $stockRows = [array_merge($rows[0], ['IDSKL' => '200'])];

        $this->enableKiposSync($size);
        Http::fake([
            '*getZalihaK*' => Http::response($stockRows, 200),
            '*getitemsextended*' => Http::response($rows, 200),
            '*getitems*' => Http::response($rows, 200),
            '*getOdjelSlike*' => Http::sequence()
                ->push([], 200)
                ->push([$this->imageRow('W8001', 'http://images.test/W8001')], 200),
            '*getSlike&*' => Http::response([], 200),
            'http://images.test/W8001' => Http::response($this->tinyPng(), 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $firstRun = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);
        $pending = Product::query()->where('code', 'W8001')->firstOrFail();

        $this->assertSame('success', $firstRun->status, (string) $firstRun->error_message);
        $this->assertFalse($pending->is_active);
        $this->assertTrue((bool) data_get($pending->payload, 'kipos.image_activation_pending'));
        $this->assertSame(1, (int) data_get($firstRun->stats, 'images.still_pending'));
        $this->assertSame(0, (int) data_get($firstRun->stats, 'images.fallback_product_lookups'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/W8001'));

        $secondRun = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);
        $imported = $pending->fresh();

        $this->assertSame('success', $secondRun->status, (string) $secondRun->error_message);
        $this->assertSame(0, (int) data_get($secondRun->stats, 'created'));
        $this->assertSame(1, (int) data_get($secondRun->stats, 'images.updated_products'));
        $this->assertFalse((bool) $imported?->is_active);
        $this->assertNotNull($imported?->getFirstMedia('product_main'));
        $this->assertFalse((bool) data_get($imported?->payload, 'kipos.image_activation_pending', false));
    }

    public function test_nightly_sync_does_not_publish_a_new_product_when_the_remote_image_is_invalid(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $size = $this->createOption($admin, 'size', true);
        $rows = [$this->itemRow('W8002', 'W8002', '', 'New product with broken image', 2)];

        $this->enableKiposSync($size);
        Http::fake([
            '*getZalihaK*' => Http::response([
                array_merge($rows[0], ['IDSKL' => '200']),
            ], 200),
            '*getitemsextended*' => Http::response($rows, 200),
            '*getitems*' => Http::response($rows, 200),
            '*getOdjelSlike*' => Http::response([
                $this->imageRow('W8002', 'http://images.test/W8002'),
            ], 200),
            'http://images.test/W8002' => Http::response('<html>missing image</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $run = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);
        $product = Product::query()->where('code', 'W8002')->firstOrFail();

        $this->assertSame('success', $run->status, (string) $run->error_message);
        $this->assertFalse($product->is_active);
        $this->assertNull($product->getFirstMedia('product_main'));
        $this->assertTrue((bool) data_get($product->payload, 'kipos.image_activation_pending'));
        $this->assertSame(1, (int) data_get($run->stats, 'images.download_failures'));
        $this->assertSame(1, (int) data_get($run->stats, 'images.still_pending'));
    }

    public function test_nightly_imported_product_stays_inactive_after_image_and_stock_return(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $size = $this->createOption($admin, 'size', true);
        $rows = [$this->itemRow('W8003', 'W8003', '', 'New product awaiting stock', 0)];
        $unrelatedStock = array_merge(
            $this->itemRow('OTHER', 'OTHER', '', 'Unrelated stock row', 1),
            ['IDSKL' => '200']
        );
        $matchingStock = array_merge($rows[0], ['IDSKL' => '200', 'ZALIHAK' => 4]);

        $this->enableKiposSync($size);
        Http::fake([
            '*getZalihaK*' => Http::sequence()
                ->push([$unrelatedStock], 200)
                ->push([$matchingStock], 200),
            '*getitemsextended*' => Http::response($rows, 200),
            '*getitems*' => Http::response($rows, 200),
            '*getOdjelSlike*' => Http::response([
                $this->imageRow('W8003', 'http://images.test/W8003'),
            ], 200),
            'http://images.test/W8003' => Http::response($this->tinyPng(), 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $firstRun = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);
        $waitingForStock = Product::query()->where('code', 'W8003')->firstOrFail();

        $this->assertSame('success', $firstRun->status, (string) $firstRun->error_message);
        $this->assertFalse($waitingForStock->is_active);
        $this->assertNotNull($waitingForStock->getFirstMedia('product_main'));
        $this->assertFalse((bool) data_get($waitingForStock->payload, 'kipos.image_activation_pending', false));
        $this->assertTrue((bool) data_get($waitingForStock->payload, 'kipos.stock_feed_missing'));
        $this->assertFalse((bool) data_get($waitingForStock->payload, 'kipos.stock_feed_restore_active'));

        $secondRun = app(KiposSyncService::class)->run('nightly_catalog_sync', $admin->id);
        $inactive = $waitingForStock->fresh();

        $this->assertSame('success', $secondRun->status, (string) $secondRun->error_message);
        $this->assertFalse((bool) $inactive?->is_active);
        $this->assertSame(4, (int) $inactive?->stock_qty);
        $this->assertFalse((bool) data_get($inactive?->payload, 'kipos.stock_feed_missing', false));
        $this->assertSame(0, (int) data_get($secondRun->stats, 'images.pending_products'));
    }

    private function createOption(User $admin, string $code, bool $showOnProductPage): Option
    {
        $option = Option::query()->create([
            'code' => $code,
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'payload' => [Option::PAYLOAD_SHOW_ON_PRODUCT_PAGE => $showOnProductPage],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $option->translations()->create([
            'locale' => 'hr',
            'name' => $code === 'size' ? 'Veličina' : 'Boja',
            'slug' => $code,
        ]);

        return $option;
    }

    private function createOptionValue(User $admin, Option $option, string $code, string $name, int $sortOrder): OptionValue
    {
        $value = OptionValue::query()->create([
            'option_id' => $option->id,
            'code' => $code,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $value->translations()->create([
            'locale' => 'hr',
            'name' => $name,
            'slug' => str($code)->slug()->value(),
        ]);

        return $value;
    }

    private function createProduct(User $admin, string $code, string $name, string $slug, ?array $payload = null): Product
    {
        $product = Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => 15,
            'stock_qty' => 1,
            'payload' => $payload,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $product->translations()->create([
            'locale' => 'hr',
            'name' => $name,
            'slug' => $slug,
            'description' => 'Kurirani zajednički opis.',
        ]);

        return $product;
    }

    private function attachOptionRow(Product $product, OptionValue $value, string $sku, int $stock, int $sortOrder): void
    {
        $product->options()->syncWithoutDetaching([
            $value->option_id => ['is_required' => true, 'sort_order' => 0],
        ]);
        ProductOptionValue::query()->create([
            'product_id' => $product->id,
            'option_value_id' => $value->id,
            'mode' => 'single',
            'sku' => $sku,
            'stock_qty' => $stock,
            'price_override' => 15,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'combination_hash' => hash('sha256', 's:'.$value->id),
        ]);
    }

    private function attachFilterRow(Product $product, Option $option, OptionValue $value): void
    {
        $product->options()->syncWithoutDetaching([
            $option->id => ['is_required' => false, 'sort_order' => 1],
        ]);
        ProductOptionValue::query()->create([
            'product_id' => $product->id,
            'option_value_id' => $value->id,
            'mode' => 'filter',
            'stock_qty' => 0,
            'sort_order' => 0,
            'is_active' => true,
            'combination_hash' => hash('sha256', 'filter:'.$option->id.':'.$value->id),
        ]);
    }

    /** @return array<string, mixed> */
    private function itemRow(string $sku, string $group, string $size, string $name, int $stock): array
    {
        return [
            'IDROBA' => $sku,
            'IDODJEL' => $group,
            'IDVELICINA' => $size,
            'NAZIV' => $name,
            'OPIS_ODJEL' => 'Kipos description',
            'CIJENA_MPC' => '18,50',
            'ZALIHAK' => $stock,
        ];
    }

    /** @return array<string, mixed> */
    private function imageRow(string $group, string $url): array
    {
        return [
            'IDODJEL' => $group,
            'URL' => $url,
            'NAZIV' => $group,
            'GLAVNA' => 'D',
            'TIP' => 'SLIKA',
        ];
    }

    private function tinyPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5Wk1cAAAAASUVORK5CYII=',
            true
        );
    }

    private function enableKiposSync(Option $size): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_options' => true,
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://kipos.test/?route=',
            'kipos_api_query_suffix' => 'webshop=1',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
            'kipos_sync_default_locale' => 'hr',
            'kipos_sync_size_option_id' => $size->id,
            'kipos_sync_stock_warehouse_ids' => '200',
            'kipos_sync_price_field' => 'CIJENA_MPC',
        ]);
    }
}
