<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Media\Manager as MediaManager;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\User;
use App\Services\Integrations\Kipos\KiposSyncService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KiposSyncImagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_update_images_skips_html_responses_and_preserves_existing_media(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7001');

        $existingImage = UploadedFile::fake()->image('existing.jpg', 40, 40);
        $product->addMedia($existingImage->getPathname())
            ->usingFileName('existing.jpg')
            ->toMediaCollection('product_gallery');

        $this->enableKiposImageSync();

        Http::fake([
            '*getOdjelSlike*' => Http::response([
                [
                    'IDODJEL' => 'M7001',
                    'URL' => 'M7001',
                    'NAZIV' => 'M7001',
                    'GLAVNA' => 'D',
                    'TIP' => 'SLIKA',
                ],
            ], 200),
            '*slike/M7001*' => Http::response('<html>not-an-image</html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $run = app(KiposSyncService::class)->run('update_images', $admin->id);

        $fresh = $product->fresh()->load('media');

        $this->assertSame('success', $run->status);
        $this->assertSame(1, $fresh->getMedia('product_gallery')->count());
        $this->assertSame('existing.jpg', (string) $fresh->getFirstMedia('product_gallery')?->file_name);
        $this->assertSame(1, (int) (($run->stats ?? [])['download_failures'] ?? 0));
        $this->assertSame(0, (int) (($run->stats ?? [])['updated_products'] ?? 0));
    }

    public function test_import_images_adds_extension_when_kipos_file_name_has_none(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7002');

        $this->enableKiposImageSync();

        Http::fake([
            '*getOdjelSlike*' => Http::response([
                [
                    'IDODJEL' => 'M7002',
                    'URL' => 'M7002',
                    'NAZIV' => 'M7002',
                    'GLAVNA' => 'D',
                    'TIP' => 'SLIKA',
                ],
            ], 200),
            '*slike/M7002*' => Http::response($this->tinyPng(), 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $run = app(KiposSyncService::class)->run('import_images', $admin->id);

        $media = $product->fresh()?->getFirstMedia('product_main');

        $this->assertSame('success', $run->status);
        $this->assertNotNull($media);
        $this->assertSame('M7002.png', $media?->file_name);
        $this->assertSame('image/png', $media?->mime_type);
    }

    public function test_media_manager_can_replace_kipos_images_for_single_product(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7003');

        $this->enableKiposImageSync();

        Http::fake(array_merge(
            $this->singleProductRouteMisses('M7003'),
            [
                '*getOdjelSlike*' => Http::response([
                    [
                        'IDODJEL' => 'M7003',
                        'URL' => 'M7003',
                        'NAZIV' => 'M7003',
                        'GLAVNA' => 'D',
                        'TIP' => 'SLIKA',
                    ],
                ], 200),
                '*slike/M7003*' => Http::response($this->tinyPng(), 200, [
                    'Content-Type' => 'image/png',
                ]),
            ]
        ));

        Livewire::actingAs($admin)
            ->test(MediaManager::class, [
                'modelClass' => Product::class,
                'modelId' => $product->id,
                'locale' => 'hr',
            ])
            ->call('replaceProductImagesFromKipos')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $media = $product->fresh()?->getFirstMedia('product_main');

        $this->assertNotNull($media);
        $this->assertSame('M7003.png', $media?->file_name);
        $this->assertSame('image/png', $media?->mime_type);
    }

    public function test_single_product_image_sync_reports_http_404_failures_without_marking_remote_as_missing(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7032');

        $this->enableKiposImageSync();

        Http::fake(array_merge(
            $this->singleProductRouteMisses('M7032'),
            [
                '*getOdjelSlike*' => Http::response([
                    [
                        'IDODJEL' => 'M7032',
                        'URL' => 'http://example.test/kipos/M7032_SNY05027.jpg',
                        'NAZIV' => 'M7032_SNY05027.jpg',
                        'GLAVNA' => false,
                        'TIP' => 'SLIKA',
                    ],
                    [
                        'IDODJEL' => 'M7032',
                        'URL' => 'http://example.test/kipos/M7032_SNY05028.jpg',
                        'NAZIV' => 'M7032_SNY05028.jpg',
                        'GLAVNA' => false,
                        'TIP' => 'SLIKA',
                    ],
                ], 200),
                'http://example.test/kipos/*' => Http::response('not found', 404, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]),
            ]
        ));

        $result = app(KiposSyncService::class)->syncProductImages($product, true, 'hr');

        $this->assertSame(2, (int) ($result['download_failures'] ?? 0));
        $this->assertSame(0, (int) ($result['skipped_without_remote'] ?? 0));
        $this->assertCount(2, $result['download_failure_details'] ?? []);
        $this->assertSame(404, (int) (($result['download_failure_details'][0]['status'] ?? 0)));
    }

    public function test_single_product_sync_uses_documented_specific_kipos_routes_for_department_images(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7034');
        $remoteImage = UploadedFile::fake()->image('remote.jpg', 40, 40);
        $remoteImageContents = file_get_contents($remoteImage->getPathname());

        $this->enableKiposImageSync();

        Http::fake([
            '*getSlike/M7034*' => Http::response([], 404),
            '*getItemSlike/M7034*' => Http::response([], 404),
            '*getItemOdjelSlike/M7034*' => Http::response([], 404),
            '*getOdjelItemsSlike/M7034*' => Http::response([
                [
                    'IDROBA' => 'M7034.L',
                    'IDODJEL' => 'M7034',
                    'URL' => 'http://example.test/kipos/ODJELI/M7034/ODJEL_M7034.jpg',
                    'NAZIV' => 'ODJEL_M7034.jpg',
                    'GLAVNA' => true,
                    'TIP' => 'SLIKA',
                    'VRSTA' => 'ODJEL',
                ],
                [
                    'IDROBA' => 'M7034.L',
                    'IDODJEL' => 'M7034',
                    'URL' => 'http://example.test/kipos/ODJELI/M7034/M7034_Kozounderwear-105.jpg',
                    'NAZIV' => 'M7034_Kozounderwear-105.jpg',
                    'GLAVNA' => false,
                    'TIP' => 'SLIKA',
                    'VRSTA' => 'ODJEL',
                ],
            ], 200),
            '*getOdjelSlike/M7034*' => Http::response([], 404),
            '*getOdjelSlike&webshop=1' => Http::response([], 200),
            'http://example.test/kipos/*' => Http::sequence()
                ->push($remoteImageContents, 200, ['Content-Type' => 'image/jpeg'])
                ->push($remoteImageContents, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $result = app(KiposSyncService::class)->syncProductImages($product, true, 'hr');

        $fresh = $product->fresh();

        $this->assertSame(1, (int) ($result['updated_products'] ?? 0));
        $this->assertSame(1, (int) ($result['main_images_attached'] ?? 0));
        $this->assertSame(1, (int) ($result['gallery_images_attached'] ?? 0));
        $this->assertSame('ODJEL_M7034.jpg', $fresh?->getFirstMedia('product_main')?->file_name);
        $this->assertSame(1, $fresh?->getMedia('product_gallery')->count());
    }

    public function test_update_images_falls_back_to_specific_product_routes_when_bulk_feed_misses_product(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);

        $admin = User::factory()->create();
        $product = $this->createProduct($admin, 'M7035');
        $remoteImage = UploadedFile::fake()->image('remote.jpg', 40, 40);
        $remoteImageContents = file_get_contents($remoteImage->getPathname());

        $this->enableKiposImageSync();

        Http::fake([
            '*getOdjelSlike&webshop=1' => Http::response([], 200),
            '*getSlike&webshop=1' => Http::response([], 200),
            '*getSlike/M7035*' => Http::response([], 404),
            '*getItemSlike/M7035*' => Http::response([], 404),
            '*getItemOdjelSlike/M7035*' => Http::response([], 404),
            '*getOdjelItemsSlike/M7035*' => Http::response([
                [
                    'IDROBA' => 'M7035.L',
                    'IDODJEL' => 'M7035',
                    'URL' => 'http://example.test/kipos/ODJELI/M7035/ODJEL_M7035.jpg',
                    'NAZIV' => 'ODJEL_M7035.jpg',
                    'GLAVNA' => true,
                    'TIP' => 'SLIKA',
                    'VRSTA' => 'ODJEL',
                ],
                [
                    'IDROBA' => 'M7035.L',
                    'IDODJEL' => 'M7035',
                    'URL' => 'http://example.test/kipos/ODJELI/M7035/M7035_gallery.jpg',
                    'NAZIV' => 'M7035_gallery.jpg',
                    'GLAVNA' => false,
                    'TIP' => 'SLIKA',
                    'VRSTA' => 'ODJEL',
                ],
            ], 200),
            '*getOdjelSlike/M7035*' => Http::response([], 404),
            'http://example.test/kipos/*' => Http::sequence()
                ->push($remoteImageContents, 200, ['Content-Type' => 'image/jpeg'])
                ->push($remoteImageContents, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $run = app(KiposSyncService::class)->run('update_images', $admin->id);

        $fresh = $product->fresh();

        $this->assertSame('success', $run->status);
        $this->assertSame(1, (int) (($run->stats ?? [])['updated_products'] ?? 0));
        $this->assertSame(1, (int) (($run->stats ?? [])['matched_products'] ?? 0));
        $this->assertSame(1, (int) (($run->stats ?? [])['fallback_product_lookups'] ?? 0));
        $this->assertSame('ODJEL_M7035.jpg', $fresh?->getFirstMedia('product_main')?->file_name);
        $this->assertSame(1, $fresh?->getMedia('product_gallery')->count());
    }

    private function createProduct(User $admin, string $code): Product
    {
        $product = Product::query()->create([
            'code' => $code,
            'sku' => $code,
            'is_active' => true,
            'base_price' => 10,
            'stock_qty' => 5,
            'payload' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'hr',
            'name' => 'Test '.$code,
            'slug' => 'test-'.$code,
            'excerpt' => null,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'payload' => null,
        ]);

        return $product;
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

    private function tinyPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5Wk1cAAAAASUVORK5CYII=',
            true
        );
    }

    /**
     * @return array<string, \Illuminate\Http\Client\ResponseSequence|\Illuminate\Http\Client\Response>
     */
    private function singleProductRouteMisses(string $code): array
    {
        return [
            '*getSlike/'.$code.'*' => Http::response([], 404),
            '*getItemSlike/'.$code.'*' => Http::response([], 404),
            '*getItemOdjelSlike/'.$code.'*' => Http::response([], 404),
            '*getOdjelItemsSlike/'.$code.'*' => Http::response([], 404),
            '*getOdjelSlike/'.$code.'*' => Http::response([], 404),
        ];
    }
}
