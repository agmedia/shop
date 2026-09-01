<?php

namespace Tests\Feature\Admin;

use App\Jobs\RunKiposSyncActionJob;
use App\Livewire\Admin\Settings\Api\KiposSyncManager;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Integrations\KiposSyncRun;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\OrderStatus;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class KiposSyncManagerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_queues_kipos_sync_action_in_background(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'import_images')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'import_images')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('queued', $run?->status);
        Queue::assertPushedOn(config('queue.kipos_queue', 'kipos'), RunKiposSyncActionJob::class);
        Queue::assertPushed(RunKiposSyncActionJob::class, 1);
    }

    public function test_admin_runs_kipos_price_update_immediately(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'W7030');

        $this->enableKiposPriceSync();
        $this->fakeKiposPrice('W7030', '42,50');

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_prices')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'update_prices')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('success', $run?->status);
        $this->assertEqualsWithDelta(42.50, (float) $product->fresh()?->base_price, 0.001);
        Queue::assertNothingPushed();
    }

    public function test_admin_runs_kipos_quantity_update_immediately(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'W7030');

        $this->enableKiposQuantitySync();
        $this->fakeKiposQuantity('W7030', 8);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_quantities')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'update_quantities')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('success', $run?->status);
        $this->assertSame(8, (int) $product->fresh()?->stock_qty);
        Queue::assertNothingPushed();
    }

    public function test_admin_imports_kipos_products_only_for_entered_codes(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        $this->enableKiposImportSync();
        $this->fakeKiposProducts([
            ['IDROBA' => 'W5003', 'IDODJEL' => 'W5003', 'NAZIV' => 'Product W5003', 'CIJENA_MPC' => '12,30', 'ZALIHAK' => 2],
            ['IDROBA' => 'W5030', 'IDODJEL' => 'W5030', 'NAZIV' => 'Product W5030', 'CIJENA_MPC' => '18,00', 'ZALIHAK' => 3],
            ['IDROBA' => 'W9999', 'IDODJEL' => 'W9999', 'NAZIV' => 'Product W9999', 'CIJENA_MPC' => '99,00', 'ZALIHAK' => 9],
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->set('importProductCodes', 'w5003, W5030')
            ->call('runAction', 'import_products')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'import_products')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('success', $run?->status, (string) $run?->error_message);
        $this->assertSame(['W5003', 'W5030'], data_get($run?->stats, 'requested_codes', []));
        $this->assertSame(2, (int) (($run?->stats ?? [])['created'] ?? 0));
        $this->assertDatabaseHas('products', ['code' => 'W5003']);
        $this->assertDatabaseHas('products', ['code' => 'W5030']);
        $this->assertDatabaseMissing('products', ['code' => 'W9999']);
        Queue::assertNothingPushed();
    }

    public function test_product_import_requires_at_least_one_code(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->set('importProductCodes', ' ')
            ->call('runAction', 'import_products')
            ->assertHasErrors(['importProductCodes'])
            ->assertDispatched('notify');

        $this->assertSame(0, KiposSyncRun::query()->where('action_key', 'import_products')->count());
        Queue::assertNothingPushed();
    }

    public function test_product_import_fails_when_entered_code_is_not_found_in_kipos(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        $this->enableKiposImportSync();
        $this->fakeKiposProducts([
            ['IDROBA' => 'W5030', 'IDODJEL' => 'W5030', 'NAZIV' => 'Product W5030', 'CIJENA_MPC' => '18,00', 'ZALIHAK' => 3],
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->set('importProductCodes', 'W5003')
            ->call('runAction', 'import_products')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'import_products')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('failed', $run?->status);
        $this->assertSame('Kipos product code was not found: W5003', $run?->error_message);
        $this->assertDatabaseMissing('products', ['code' => 'W5003']);
        Queue::assertNothingPushed();
    }

    public function test_admin_runs_kipos_order_status_update_immediately(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $newStatus = OrderStatus::query()->create([
            'code' => 'new',
            'name' => 'New',
            'color' => 'blue',
            'is_default' => true,
            'is_paid' => false,
            'is_cancelled' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $paidStatus = OrderStatus::query()->create([
            'code' => 'paid',
            'name' => 'Paid',
            'color' => 'emerald',
            'is_default' => false,
            'is_paid' => true,
            'is_cancelled' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $order = Order::query()->create([
            'order_number' => '123',
            'status_id' => $newStatus->id,
            'customer_name' => 'Kipos Customer',
            'customer_email' => 'kipos@example.test',
            'grand_total' => 25,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->enableKiposOrderStatusSync();
        Http::fake([
            '*narudzba/statusi*' => Http::response([
                [
                    'CMS_ID' => 'KHR123',
                    'STATUS' => 'PLACENO',
                    'NAZIV' => 'Plaćeno',
                ],
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_order_statuses')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'update_order_statuses')->latest('id')->first();
        $order->refresh();

        $this->assertNotNull($run);
        $this->assertSame('success', $run?->status, (string) $run?->error_message);
        $this->assertSame(1, (int) (($run?->stats ?? [])['updated'] ?? 0));
        $this->assertSame((int) $paidStatus->id, (int) $order->status_id);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('order_history', [
            'order_id' => $order->id,
            'from_status_id' => $newStatus->id,
            'to_status_id' => $paidStatus->id,
            'comment' => 'Kipos sync status update.',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_admin_runs_kipos_image_update_immediately(): void
    {
        Queue::fake();
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
            'media-library.temporary_directory_path' => sys_get_temp_dir(),
        ]);

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'M7031');
        $this->createProductTranslation($product);

        $this->enableKiposImageSync();
        $this->fakeKiposImage('M7031');

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_images')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run = KiposSyncRun::query()->where('action_key', 'update_images')->latest('id')->first();
        $media = $product->fresh()?->getFirstMedia('product_main');

        $this->assertNotNull($run);
        $this->assertSame('success', $run?->status);
        $this->assertSame(1, (int) (($run?->stats ?? [])['updated_products'] ?? 0));
        $this->assertNotNull($media);
        $this->assertSame('M7031.png', $media?->file_name);
        Queue::assertNothingPushed();
    }

    public function test_admin_executes_existing_queued_price_update_immediately(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'W7030');

        $this->enableKiposPriceSync();
        $this->fakeKiposPrice('W7030', '43,50');

        $run = KiposSyncRun::query()->create([
            'action_key' => 'update_prices',
            'action_label' => 'Update Prices',
            'status' => 'queued',
            'summary' => 'Queued from admin. Waiting for background worker.',
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_prices')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run->refresh();

        $this->assertSame('success', $run->status);
        $this->assertSame(1, KiposSyncRun::query()->where('action_key', 'update_prices')->count());
        $this->assertEqualsWithDelta(43.50, (float) $product->fresh()?->base_price, 0.001);
        Queue::assertNothingPushed();
    }

    public function test_admin_executes_existing_queued_image_update_immediately(): void
    {
        Queue::fake();
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
            'media-library.temporary_directory_path' => sys_get_temp_dir(),
        ]);

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'M7032');
        $this->createProductTranslation($product);

        $this->enableKiposImageSync();
        $this->fakeKiposImage('M7032');

        $run = KiposSyncRun::query()->create([
            'action_key' => 'update_images',
            'action_label' => 'Update Images',
            'status' => 'queued',
            'summary' => 'Queued from admin. Waiting for background worker.',
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_images')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run->refresh();

        $this->assertSame('success', $run->status);
        $this->assertSame(1, KiposSyncRun::query()->where('action_key', 'update_images')->count());
        $this->assertSame('M7032.png', $product->fresh()?->getFirstMedia('product_main')?->file_name);
        Queue::assertNothingPushed();
    }

    public function test_update_images_runs_in_batches_of_ten_and_shows_progress(): void
    {
        Queue::fake();
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
            'media-library.temporary_directory_path' => sys_get_temp_dir(),
        ]);

        $admin = $this->makeUserWithRole('superadmin');
        $codes = collect(range(1, 12))
            ->map(fn (int $index): string => 'M80'.str_pad((string) $index, 2, '0', STR_PAD_LEFT))
            ->all();

        foreach ($codes as $code) {
            $product = $this->createKiposProduct($admin, $code);
            $this->createProductTranslation($product);
        }

        $this->enableKiposImageSync();
        $this->fakeKiposImages($codes);

        $component = Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_images')
            ->assertHasNoErrors()
            ->assertSee('10 / 12');

        $run = KiposSyncRun::query()->where('action_key', 'update_images')->latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame('started', $run?->status);
        $this->assertSame(10, (int) (($run?->stats ?? [])['processed_products'] ?? 0));
        $this->assertSame(12, (int) (($run?->stats ?? [])['total_products'] ?? 0));
        $this->assertSame(10, (int) (($run?->stats ?? [])['batch_size'] ?? 0));

        $component
            ->call('processActiveBrowserBatch')
            ->assertHasNoErrors()
            ->assertSee('12 / 12');

        $run->refresh();

        $this->assertSame('success', $run->status);
        $this->assertSame(12, (int) (($run->stats ?? [])['processed_products'] ?? 0));
        $this->assertSame(12, (int) (($run->stats ?? [])['updated_products'] ?? 0));
        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_queue_same_kipos_action_twice_while_it_is_active(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        KiposSyncRun::query()->create([
            'action_key' => 'import_images',
            'action_label' => 'Import Images',
            'status' => 'queued',
            'summary' => 'Queued from admin. Waiting for background worker.',
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'import_images')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertSame(1, KiposSyncRun::query()->where('action_key', 'import_images')->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_can_stop_active_kipos_sync_run(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        $run = KiposSyncRun::query()->create([
            'action_key' => 'import_products',
            'action_label' => 'Import Products',
            'status' => 'started',
            'summary' => 'Execution started.',
            'started_at' => now(),
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('cancelRun', $run->id)
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame('Execution stopped from admin.', $run->summary);
        $this->assertSame('This Kipos sync run was manually stopped from the admin screen.', $run->error_message);
        $this->assertNotNull($run->finished_at);
        Queue::assertNothingPushed();
    }

    public function test_stale_started_run_is_failed_before_retry_is_queued(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');

        $staleRun = KiposSyncRun::query()->create([
            'action_key' => 'import_images',
            'action_label' => 'Import Images',
            'status' => 'started',
            'summary' => 'Execution started.',
            'started_at' => now()->subMinutes(46),
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'import_images')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $staleRun->refresh();
        $replacementRun = KiposSyncRun::query()->where('action_key', 'import_images')->latest('id')->first();

        $this->assertSame('failed', $staleRun->status);
        $this->assertSame(
            'Execution marked as failed because the previous run became stale.',
            $staleRun->summary
        );
        $this->assertNotNull($replacementRun);
        $this->assertNotSame($staleRun->id, $replacementRun?->id);
        $this->assertSame('queued', $replacementRun?->status);
        Queue::assertPushedOn(config('queue.kipos_queue', 'kipos'), RunKiposSyncActionJob::class);
        Queue::assertPushed(RunKiposSyncActionJob::class, 1);
    }

    public function test_stale_started_quantity_update_is_failed_before_retry_runs(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $product = $this->createKiposProduct($admin, 'W7030');

        $this->enableKiposQuantitySync();
        $this->fakeKiposQuantity('W7030', 8);

        $staleRun = KiposSyncRun::query()->create([
            'action_key' => 'update_quantities',
            'action_label' => 'Update Quantities',
            'status' => 'started',
            'summary' => 'Execution started.',
            'started_at' => now()->subMinutes(6),
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->call('runAction', 'update_quantities')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $staleRun->refresh();
        $replacementRun = KiposSyncRun::query()->where('action_key', 'update_quantities')->latest('id')->first();

        $this->assertSame('failed', $staleRun->status);
        $this->assertNotNull($replacementRun);
        $this->assertNotSame($staleRun->id, $replacementRun?->id);
        $this->assertSame('success', $replacementRun?->status);
        $this->assertSame(8, (int) $product->fresh()?->stock_qty);
        Queue::assertNothingPushed();
    }

    public function test_component_polls_only_while_kipos_run_is_active(): void
    {
        $admin = $this->makeUserWithRole('superadmin');

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->assertDontSeeHtml('wire:poll.2s="processActiveBrowserBatch"');

        KiposSyncRun::query()->create([
            'action_key' => 'update_images',
            'action_label' => 'Update Images',
            'status' => 'queued',
            'summary' => 'Queued from admin. Waiting for background worker.',
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->assertSeeHtml('wire:poll.2s="processActiveBrowserBatch"');
    }

    public function test_component_marks_stale_started_runs_failed_and_stops_polling(): void
    {
        $admin = $this->makeUserWithRole('superadmin');

        $staleRun = KiposSyncRun::query()->create([
            'action_key' => 'update_images',
            'action_label' => 'Update Images',
            'status' => 'started',
            'summary' => 'Execution started.',
            'started_at' => now()->subMinutes(46),
            'initiated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KiposSyncManager::class)
            ->assertDontSeeHtml('wire:poll.2s="processActiveBrowserBatch"');

        $staleRun->refresh();

        $this->assertSame('failed', $staleRun->status);
        $this->assertSame(
            'Execution marked as failed because the previous run became stale.',
            $staleRun->summary
        );
    }

    private function makeUserWithRole(string $role): User
    {
        Bouncer::role()->firstOrCreate(['name' => 'superadmin']);
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::role()->firstOrCreate(['name' => 'editor']);
        Bouncer::role()->firstOrCreate(['name' => 'customer']);

        $user = User::factory()->create();
        Bouncer::assign($role)->to($user);

        return $user;
    }

    private function createKiposProduct(User $admin, string $code): Product
    {
        return Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => 10,
            'stock_qty' => 0,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function createProductTranslation(Product $product): void
    {
        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'hr',
            'name' => 'Test '.$product->code,
            'slug' => 'test-'.$product->code,
            'excerpt' => null,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'payload' => null,
        ]);
    }

    private function enableKiposPriceSync(): void
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

    private function enableKiposImportSync(): void
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

    private function enableKiposImageSync(): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_image_base_uri' => 'http://balidd.dyndns.org:8080/slike/',
            'kipos_api_query_suffix' => 'webshop=1',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
        ]);
    }

    private function enableKiposQuantitySync(): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_query_suffix' => 'webshop=2',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
        ]);
    }

    private function enableKiposOrderStatusSync(): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_kipos_api' => true,
            'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_query_suffix' => 'webshop=2',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
            'kipos_order_status_endpoint' => 'narudzba/statusi',
            'kipos_order_status_map' => '{"placeno":"paid"}',
        ]);
    }

    private function fakeKiposPrice(string $code, string $price): void
    {
        Http::fake([
            '*getitemsextended*' => Http::response([
                ['IDROBA' => $code, 'IDODJEL' => $code, 'CIJENA_MPC' => $price],
            ], 200),
            '*getitems*' => Http::response([], 200),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fakeKiposProducts(array $rows): void
    {
        Http::fake([
            '*getitemsextended*' => Http::response([], 200),
            '*getitems*' => Http::response($rows, 200),
        ]);
    }

    private function fakeKiposQuantity(string $code, int $quantity): void
    {
        Http::fake([
            '*getitemsextended*' => Http::response([
                [
                    'IDROBA' => $code,
                    'IDODJEL' => $code,
                ],
            ], 200),
            '*getZalihaK*' => Http::response([
                [
                    'IDROBA' => $code,
                    'IDODJEL' => $code,
                    'ZALIHAK' => $quantity,
                    'IDSKL' => '200',
                ],
            ], 200),
        ]);
    }

    private function fakeKiposImage(string $code): void
    {
        $this->fakeKiposImages([$code]);
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function fakeKiposImages(array $codes): void
    {
        $remoteImage = UploadedFile::fake()->image('remote.png', 40, 40);

        Http::fake([
            '*getOdjelSlike*' => Http::response(collect($codes)
                ->map(fn (string $code): array => [
                    'IDODJEL' => $code,
                    'URL' => $code,
                    'NAZIV' => $code,
                    'GLAVNA' => 'D',
                    'TIP' => 'SLIKA',
                ])
                ->values()
                ->all(), 200),
            '*slike/*' => Http::response(file_get_contents($remoteImage->getPathname()), 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);
    }
}
