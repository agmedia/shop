<?php

namespace App\Services\Integrations\Kipos;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Action\CatalogActionTarget;
use App\Models\Catalog\Action\CatalogActionTranslation;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Option\OptionValueTranslation;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Integrations\KiposSyncRun;
use App\Models\Sales\Order\OrderHistory;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Integrations\Kipos\Concerns\SyncsKiposOrderStatuses;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KiposSyncService
{
    use SyncsKiposOrderStatuses;

    private const STALE_STARTED_RUN_AFTER_MINUTES = 45;

    private const IMMEDIATE_ACTION_STALE_STARTED_RUN_AFTER_MINUTES = 5;

    private const IMAGE_BATCH_CACHE_TTL_MINUTES = 360;

    private ?int $runInitiatedBy = null;

    public function __construct(
        private readonly KiposSdkService $kipos,
        private readonly SystemSettingsService $settings,
        private readonly CatalogFeatureService $catalogFeatures
    ) {}

    /**
     * @return array<string, string>
     */
    public function endpointMap(): array
    {
        return [
            'items' => 'sif_roba/getitems',
            'items_extended' => 'sif_roba/getitemsextended',
            'stock' => 'sif_roba/getZalihaK',
            'images_department' => 'sif_roba/getOdjelSlike',
            'images_all' => 'sif_roba/getSlike',
            'images_department_single' => 'sif_roba/getOdjelSlike/[IDODJEL]',
            'images_department_items' => 'sif_roba/getOdjelItemsSlike/[IDODJEL]',
            'images_item_department' => 'sif_roba/getItemOdjelSlike/[IDODJEL]',
            'images_item_single' => 'sif_roba/getItemSlike/[IDROBA]',
            'images_single' => 'sif_roba/getSlike/[IDROBA]',
            'translations' => 'sif_roba/getPrijevod',
            'order_create' => 'narudzba/create',
            'order_statuses' => 'narudzba/statusi',
        ];
    }

    /**
     * @return array<string, array{title:string,description:string,actions:array<int,array{key:string,label:string,description:string}>}>
     */
    public function actionGroups(): array
    {
        return [
            'catalog' => [
                'title' => 'Catalog Sync',
                'description' => 'Granular Kipos product sync so you can update only the fields you want.',
                'actions' => [
                    ['key' => 'import_products', 'label' => 'Import Products', 'description' => 'Create missing products only for entered Kipos product codes.'],
                    ['key' => 'update_content', 'label' => 'Update Content', 'description' => 'Update names, descriptions, active state, and structural variant mapping without touching prices or quantities.'],
                    ['key' => 'update_prices', 'label' => 'Update Prices', 'description' => 'Bulk refresh product and size prices from the complete Kipos extended item feed.'],
                    ['key' => 'update_quantities', 'label' => 'Update Quantities', 'description' => 'Refresh stock only, with warehouse filtering and quantity override rules.'],
                    ['key' => 'update_actions', 'label' => 'Update Actions', 'description' => 'Create / update Kipos-driven catalog actions from `AKCIJSKA_CIJENA`.'],
                ],
            ],
            'images' => [
                'title' => 'Image Sync',
                'description' => 'Separate image tools so media imports stay independent from catalog content runs.',
                'actions' => [
                    ['key' => 'import_images', 'label' => 'Import Images', 'description' => 'Attach Kipos images only to products without current local media.'],
                    ['key' => 'update_images', 'label' => 'Update Images', 'description' => 'Replace local product images when matching remote Kipos images exist.'],
                ],
            ],
            'orders' => [
                'title' => 'Order Sync',
                'description' => 'Keep local admin order statuses aligned with Kipos ERP order state.',
                'actions' => [
                    ['key' => 'update_order_statuses', 'label' => 'Update Order Statuses', 'description' => 'Fetch Kipos order status rows, match local webshop orders, and update admin order status/history.'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncDefaults(): array
    {
        return [
            'kipos_sync_default_locale' => (string) config('app.locale', 'hr'),
            'kipos_sync_import_category_id' => null,
            'kipos_sync_size_option_id' => null,
            'kipos_sync_price_field' => 'CIJENA_MPC',
            'kipos_sync_action_price_field' => 'AKCIJSKA_CIJENA',
            'kipos_sync_stock_warehouse_ids' => '200',
            'kipos_sync_quantity_overrides' => '',
            'kipos_order_prefix' => 'KHR',
            'kipos_order_valuta' => '978',
            'kipos_order_customer_cms_id' => '1',
            'kipos_order_shipping_item_code' => '',
            'kipos_order_payment_fee_item_code' => '',
            'kipos_order_private_at_company_id' => 2,
            'kipos_order_private_de_company_id' => 3,
            'kipos_order_status_endpoint' => 'narudzba/statusi',
            'kipos_order_status_lookback_days' => 30,
            'kipos_order_status_codes' => '',
            'kipos_order_status_map' => '{"paid":"paid","placeno":"paid","plaćeno":"paid","poslano":"sent","sent":"sent","isporuceno":"sent","isporučeno":"sent","cancelled":"cancelled","canceled":"cancelled","otkazano":"cancelled","storno":"cancelled","stornirano":"cancelled"}',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncSettings(): array
    {
        $defaults = $this->syncDefaults();
        $settings = [];

        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->settings->get($key, $default);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveSyncSettings(array $payload): void
    {
        $this->settings->putMany($payload);
    }

    public function connectorEnabled(): bool
    {
        return $this->catalogFeatures->useKiposApi() && $this->kipos->enabledInSettings();
    }

    /**
     * @return array<string, mixed>
     */
    public function syncProductImages(Product $product, bool $replaceExisting = true, ?string $locale = null): array
    {
        $this->kipos->assertEnabled();

        $locale = $this->normalizeSyncLocale($locale);
        $groupCode = strtoupper(trim((string) $product->code));
        $imageRows = $this->remoteImageRowsForProduct($product);

        $product->load([
            'translations' => fn ($query) => $query->where('locale', $locale),
            'media',
        ]);

        config([
            'media-library.max_file_size' => max((int) config('media-library.max_file_size', 0), 25 * 1024 * 1024),
        ]);

        $stats = $this->syncImageRowsForProduct(
            product: $product,
            imageRows: $imageRows,
            replaceExisting: $replaceExisting,
            locale: $locale
        );

        return array_merge($stats, [
            'summary' => sprintf(
                'Product %s images: %d updated, %d skipped existing, %d skipped without remote, %d download failures.',
                $groupCode,
                (int) ($stats['updated_products'] ?? 0),
                (int) ($stats['skipped_existing'] ?? 0),
                (int) ($stats['skipped_without_remote'] ?? 0),
                (int) ($stats['download_failures'] ?? 0)
            ),
            'matched_products' => 1,
            'unmatched_products' => 0,
            'product_id' => (int) $product->id,
            'group_code' => $groupCode,
            'remote_rows' => count($imageRows),
            'replace_existing' => $replaceExisting,
            'lookup_item_codes' => $this->productImageLookupItemCodes($product),
        ]);
    }

    public function queue(string $actionKey, ?int $initiatedBy = null): KiposSyncRun
    {
        $action = $this->resolveAction($actionKey);

        $activeRun = $this->activeRun($actionKey);

        if ($activeRun) {
            return $activeRun;
        }

        return KiposSyncRun::query()->create([
            'action_key' => $actionKey,
            'action_label' => $action['label'],
            'status' => 'queued',
            'summary' => 'Queued from admin. Waiting for background worker.',
            'started_at' => null,
            'finished_at' => null,
            'initiated_by' => $initiatedBy,
        ]);
    }

    public function activeRun(string $actionKey): ?KiposSyncRun
    {
        $this->markStaleStartedRunsAsFailed($actionKey);

        return KiposSyncRun::query()
            ->where('action_key', $actionKey)
            ->whereIn('status', ['queued', 'started'])
            ->latest('id')
            ->first();
    }

    public function hasActiveRuns(): bool
    {
        $this->markStaleStartedRunsAsFailed();

        return KiposSyncRun::query()
            ->whereIn('status', ['queued', 'started'])
            ->exists();
    }

    public function run(string $actionKey, ?int $initiatedBy = null): KiposSyncRun
    {
        $action = $this->resolveAction($actionKey);

        $run = KiposSyncRun::query()->create([
            'action_key' => $actionKey,
            'action_label' => $action['label'],
            'status' => 'started',
            'started_at' => now(),
            'initiated_by' => $initiatedBy,
        ]);

        return $this->performRun($run);
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function runProductImport(array $codes, ?int $initiatedBy = null): KiposSyncRun
    {
        $codes = $this->normalizeProductCodeFilter($codes);
        if ($codes === []) {
            throw new RuntimeException('Enter at least one Kipos product code before importing products.');
        }

        $action = $this->resolveAction('import_products');

        $run = KiposSyncRun::query()->create([
            'action_key' => 'import_products',
            'action_label' => $action['label'],
            'status' => 'started',
            'started_at' => now(),
            'initiated_by' => $initiatedBy,
        ]);

        return $this->performRun($run, ['product_codes' => $codes]);
    }

    public function executeQueuedRun(KiposSyncRun $run): KiposSyncRun
    {
        $this->resolveAction($run->action_key);

        if (in_array($run->status, ['success', 'failed'], true)) {
            return $run->fresh(['initiator']) ?? $run;
        }

        $run->fill([
            'status' => 'started',
            'summary' => 'Execution started.',
            'started_at' => $run->started_at ?: now(),
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        return $this->performRun($run);
    }

    /**
     * @return array<string, string>
     */
    private function handlerMap(): array
    {
        return [
            'import_products' => 'handleImportProducts',
            'update_content' => 'handleUpdateContent',
            'update_prices' => 'handleUpdatePrices',
            'update_quantities' => 'handleUpdateQuantities',
            'update_actions' => 'handleUpdateActions',
            'import_images' => 'handleImportImages',
            'update_images' => 'handleUpdateImages',
            'update_order_statuses' => 'handleUpdateOrderStatuses',
        ];
    }

    /**
     * @return array<string, array{label:string,description:string}>
     */
    private function flatActionCatalog(): array
    {
        $flat = [];

        foreach ($this->actionGroups() as $group) {
            foreach ($group['actions'] as $action) {
                $flat[$action['key']] = [
                    'label' => $action['label'],
                    'description' => $action['description'],
                ];
            }
        }

        return $flat;
    }

    /**
     * @return array{label:string,description:string}
     */
    private function resolveAction(string $actionKey): array
    {
        $catalog = $this->flatActionCatalog();
        abort_unless(isset($catalog[$actionKey]), 404, 'Unknown Kipos sync action.');

        return $catalog[$actionKey];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function performRun(KiposSyncRun $run, array $context = []): KiposSyncRun
    {
        $this->runInitiatedBy = $run->initiated_by;

        try {
            $this->kipos->assertEnabled();

            $handler = $this->handlerMap()[$run->action_key] ?? null;
            if (! $handler) {
                throw new RuntimeException('No handler configured for action: '.$run->action_key);
            }

            /** @var array<string, mixed> $result */
            $result = $run->action_key === 'import_products'
                ? $this->{$handler}($context)
                : $this->{$handler}();

            $run->fill([
                'status' => 'success',
                'summary' => (string) ($result['summary'] ?? 'Completed.'),
                'stats' => $result,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $run->fill([
                'status' => 'failed',
                'summary' => 'Execution failed.',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        } finally {
            $this->runInitiatedBy = null;
        }

        return $run->fresh(['initiator']) ?? $run;
    }

    private function markStaleStartedRunsAsFailed(?string $actionKey = null): void
    {
        $now = now();

        KiposSyncRun::query()
            ->when($actionKey !== null, fn ($query) => $query->where('action_key', $actionKey))
            ->where('status', 'started')
            ->get()
            ->each(function (KiposSyncRun $run) use ($now): void {
                $threshold = $now->copy()->subMinutes($this->staleStartedRunAfterMinutes($run->action_key));
                $startedAt = $run->started_at;
                $updatedAt = $run->updated_at;

                if ($startedAt && $startedAt->gt($threshold)) {
                    return;
                }

                if (! $startedAt && $updatedAt && $updatedAt->gt($threshold)) {
                    return;
                }

                $run->fill([
                    'status' => 'failed',
                    'summary' => 'Execution marked as failed because the previous run became stale.',
                    'error_message' => 'Previous background worker did not finish this run. Queueing a fresh retry is now allowed.',
                    'finished_at' => now(),
                ])->save();
            });
    }

    private function staleStartedRunAfterMinutes(string $actionKey): int
    {
        return in_array($actionKey, ['import_products', 'update_prices', 'update_quantities', 'update_order_statuses'], true)
            ? self::IMMEDIATE_ACTION_STALE_STARTED_RUN_AFTER_MINUTES
            : self::STALE_STARTED_RUN_AFTER_MINUTES;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportProducts(array $context = []): array
    {
        $codes = $this->normalizeProductCodeFilter($context['product_codes'] ?? []);
        if ($codes === []) {
            throw new RuntimeException('Enter at least one Kipos product code before importing products.');
        }

        $sourceRows = $this->importProductRowsForCodes($codes);
        if ($sourceRows === []) {
            throw new RuntimeException('Kipos product code was not found: '.implode(', ', $codes));
        }

        $result = $this->syncProducts(
            createMissing: true,
            updateExisting: false,
            applyPricing: true,
            applyQuantities: true,
            productCodeFilter: $codes,
            sourceRows: $sourceRows
        );

        if (($result['unmatched_requested_codes'] ?? []) !== []) {
            $result['summary'] .= ' Not found in Kipos: '.implode(', ', $result['unmatched_requested_codes']).'.';
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateContent(): array
    {
        return $this->syncProducts(createMissing: false, updateExisting: true, applyPricing: false, applyQuantities: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdatePrices(): array
    {
        $groups = $this->groupRowsByDepartment(
            $this->kipos->getRows('sif_roba/getitemsextended')
        );
        $products = Product::query()
            ->with('optionValues')
            ->whereIn('code', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => strtoupper((string) $product->code));

        $updatedProducts = 0;
        $updatedVariants = 0;
        $unmatched = 0;
        $now = now();
        $productUpdates = [];
        $variantUpdates = [];

        foreach ($groups as $groupCode => $rows) {
            $product = $products->get($groupCode);
            if (! $product) {
                $unmatched++;

                continue;
            }

            $basePrice = $this->groupBasePrice($rows);
            $payload = (array) ($product->payload ?? []);
            $payload['kipos'] = array_merge((array) ($payload['kipos'] ?? []), [
                'price_synced_at' => $now->toIso8601String(),
                'lowest_30_days_price' => $this->lowest30DaysPrice($rows),
            ]);

            $productUpdates[] = [
                'id' => $product->id,
                'code' => $product->code,
                'base_price' => $basePrice,
                'payload' => $this->encodeJsonColumn($payload),
                'updated_by' => $this->currentUserId(),
                'updated_at' => $now,
            ];

            $updatedProducts++;

            $variantMap = $product->optionValues->keyBy(
                fn (ProductOptionValue $row): string => strtoupper((string) $row->sku)
            );

            foreach ($rows as $row) {
                $variant = $variantMap->get($this->itemCode($row));
                if (! $variant) {
                    continue;
                }

                $variantUpdates[] = [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'option_value_id' => $variant->option_value_id,
                    'combination_hash' => $variant->combination_hash,
                    'price_override' => max(0.0, $this->rowPrice($row)),
                    'updated_by' => $this->currentUserId(),
                    'updated_at' => $now,
                ];

                $updatedVariants++;
            }
        }

        foreach (array_chunk($productUpdates, 500) as $chunk) {
            DB::table('products')->upsert(
                $chunk,
                ['id'],
                ['base_price', 'payload', 'updated_by', 'updated_at']
            );
        }

        foreach (array_chunk($variantUpdates, 1000) as $chunk) {
            DB::table('catalog_product_option_values')->upsert(
                $chunk,
                ['id'],
                ['price_override', 'updated_by', 'updated_at']
            );
        }

        return [
            'summary' => sprintf('Prices: %d products updated, %d variant rows updated, %d unmatched.', $updatedProducts, $updatedVariants, $unmatched),
            'updated_products' => $updatedProducts,
            'updated_variants' => $updatedVariants,
            'unmatched_products' => $unmatched,
            'source_groups' => count($groups),
            'price_field' => $this->priceField(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateQuantities(): array
    {
        $groups = $this->groupRowsByDepartment($this->normalizedStockRows());
        $products = Product::query()
            ->with('optionValues')
            ->whereIn('code', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => strtoupper((string) $product->code));

        $updatedProducts = 0;
        $updatedVariants = 0;
        $unmatched = 0;
        $now = now();
        $userId = $this->currentUserId();
        $productUpdates = [];
        $variantUpdates = [];

        foreach ($groups as $groupCode => $rows) {
            $product = $products->get($groupCode);
            if (! $product) {
                $unmatched++;

                continue;
            }

            $productUpdates[] = [
                'id' => $product->id,
                'code' => $product->code,
                'stock_qty' => $this->groupQuantity($rows),
                'updated_by' => $userId,
                'updated_at' => $now,
            ];
            $updatedProducts++;

            $variantMap = $product->optionValues->keyBy(
                fn (ProductOptionValue $row): string => strtoupper((string) $row->sku)
            );

            foreach ($rows as $row) {
                $variant = $variantMap->get($this->itemCode($row));
                if (! $variant) {
                    continue;
                }

                $variantUpdates[] = [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'option_value_id' => $variant->option_value_id,
                    'combination_hash' => $variant->combination_hash,
                    'stock_qty' => $this->rowQuantity($row),
                    'updated_by' => $userId,
                    'updated_at' => $now,
                ];

                $updatedVariants++;
            }
        }

        foreach (array_chunk($productUpdates, 500) as $chunk) {
            DB::table('products')->upsert(
                $chunk,
                ['id'],
                ['stock_qty', 'updated_by', 'updated_at']
            );
        }

        foreach (array_chunk($variantUpdates, 1000) as $chunk) {
            DB::table('catalog_product_option_values')->upsert(
                $chunk,
                ['id'],
                ['stock_qty', 'updated_by', 'updated_at']
            );
        }

        $this->forgetFrontendProductCache(array_column($productUpdates, 'id'));

        return [
            'summary' => sprintf('Quantities: %d products updated, %d variant rows updated, %d unmatched.', $updatedProducts, $updatedVariants, $unmatched),
            'updated_products' => $updatedProducts,
            'updated_variants' => $updatedVariants,
            'unmatched_products' => $unmatched,
            'source_groups' => count($groups),
            'warehouse_filter' => $this->warehouseFilter(),
        ];
    }

    /**
     * @param  array<int, int|string>  $productIds
     */
    private function forgetFrontendProductCache(array $productIds): void
    {
        Cache::forget('front:catalog:last-modified-ts');

        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            if ($productId > 0) {
                Cache::forget('front:product:last-modified:'.$productId);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateActions(): array
    {
        if (! $this->catalogFeatures->useActions()) {
            throw new RuntimeException('Enable `Use Actions & Discounts` before running Kipos action sync.');
        }

        $locale = $this->defaultLocale();
        $groups = $this->groupRowsByDepartment($this->mergedProductRows());
        $products = Product::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->whereIn('code', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => strtoupper((string) $product->code));

        $activated = 0;
        $deactivated = 0;
        $skipped = 0;
        $unmatched = 0;

        foreach ($groups as $groupCode => $rows) {
            $product = $products->get($groupCode);
            if (! $product) {
                $unmatched++;

                continue;
            }

            $actionCode = $this->actionCode($groupCode);
            $action = CatalogAction::query()->firstOrNew(['code' => $actionCode]);
            $basePrice = (float) ($product->base_price ?: $this->groupBasePrice($rows));
            $actionPrice = $this->groupActionPrice($rows);

            if ($actionPrice > 0 && $basePrice > 0 && $actionPrice < $basePrice) {
                $discountValue = round($basePrice - $actionPrice, 2);

                $action->fill([
                    'scope' => CatalogAction::SCOPE_PRODUCT,
                    'type' => CatalogAction::TYPE_FIXED,
                    'discount_value' => $discountValue,
                    'target_type' => CatalogAction::TARGET_PRODUCT,
                    'audience_type' => CatalogAction::AUDIENCE_ALL,
                    'priority' => 100,
                    'is_exclusive' => false,
                    'is_active' => true,
                    'payload' => [
                        'kipos' => [
                            'department_code' => $groupCode,
                            'action_price' => $actionPrice,
                            'base_price' => $basePrice,
                            'lowest_30_days_price' => $this->lowest30DaysPrice($rows),
                        ],
                    ],
                    'created_by' => $action->exists ? $action->created_by : $this->currentUserId(),
                    'updated_by' => $this->currentUserId(),
                ]);
                $action->save();

                $title = trim((string) ($product->translations->first()?->name ?: $product->code));
                CatalogActionTranslation::query()->updateOrCreate(
                    [
                        'action_id' => $action->id,
                        'locale' => $locale,
                    ],
                    [
                        'title' => $title.' akcija',
                        'description' => 'Kipos action sync',
                        'badge' => 'AKCIJA',
                        'payload' => ['kipos' => ['department_code' => $groupCode]],
                    ]
                );

                CatalogActionTarget::query()->updateOrCreate(
                    [
                        'action_id' => $action->id,
                        'target_type' => CatalogAction::TARGET_PRODUCT,
                        'target_id' => $product->id,
                    ],
                    ['sort_order' => 0]
                );

                $activated++;

                continue;
            }

            if ($action->exists && $action->is_active) {
                $action->forceFill([
                    'is_active' => false,
                    'updated_by' => $this->currentUserId(),
                ])->save();
                $deactivated++;
            } else {
                $skipped++;
            }
        }

        return [
            'summary' => sprintf('Actions: %d activated, %d deactivated, %d skipped, %d unmatched.', $activated, $deactivated, $skipped, $unmatched),
            'activated' => $activated,
            'deactivated' => $deactivated,
            'skipped' => $skipped,
            'unmatched_products' => $unmatched,
            'source_groups' => count($groups),
            'action_price_field' => $this->actionPriceField(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportImages(): array
    {
        return $this->syncImages(replaceExisting: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateImages(): array
    {
        return $this->syncImages(replaceExisting: true);
    }

    public function startImageBatchRun(string $actionKey, ?int $initiatedBy = null, int $batchSize = 10, ?KiposSyncRun $run = null): KiposSyncRun
    {
        $action = $this->resolveAction($actionKey);
        if (! in_array($actionKey, ['update_images'], true)) {
            throw new RuntimeException('This Kipos action cannot be processed in browser batches.');
        }

        if ($run === null) {
            $run = $this->activeRun($actionKey);
        }

        if ($run instanceof KiposSyncRun && $run->status === 'started') {
            return $run->fresh(['initiator']) ?? $run;
        }

        $this->kipos->assertEnabled();

        $replaceExisting = $actionKey === 'update_images';
        $locale = $this->defaultLocale();
        $grouped = $this->remoteImageRowsByGroup();
        $products = Product::query()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('id')
            ->get(['id', 'code']);

        $productIds = $products
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $productCodes = $products
            ->map(fn (Product $product): string => strtoupper((string) $product->code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unmatchedProducts = count(array_diff(array_keys($grouped), $productCodes));
        $totalProducts = count($productIds);

        $run ??= KiposSyncRun::query()->create([
            'action_key' => $actionKey,
            'action_label' => $action['label'],
            'status' => 'started',
            'started_at' => now(),
            'initiated_by' => $initiatedBy,
        ]);

        Cache::put($this->imageBatchCacheKey($run->id, 'product_ids'), $productIds, now()->addMinutes(self::IMAGE_BATCH_CACHE_TTL_MINUTES));
        Cache::put($this->imageBatchCacheKey($run->id, 'grouped_rows'), $grouped, now()->addMinutes(self::IMAGE_BATCH_CACHE_TTL_MINUTES));

        $stats = [
            'summary' => sprintf('Images: 0 / %d products processed (0%%).', $totalProducts),
            'browser_batch' => true,
            'batch_size' => max(1, $batchSize),
            'processed_products' => 0,
            'total_products' => $totalProducts,
            'progress_percent' => $totalProducts > 0 ? 0 : 100,
            'next_offset' => 0,
            'matched_products' => 0,
            'updated_products' => 0,
            'skipped_existing' => 0,
            'skipped_without_remote' => 0,
            'unmatched_products' => $unmatchedProducts,
            'main_images_attached' => 0,
            'gallery_images_attached' => 0,
            'download_failures' => 0,
            'download_failure_details' => [],
            'replace_existing' => $replaceExisting,
            'fallback_product_lookups' => 0,
            'remote_groups' => count($grouped),
            'locale' => $locale,
        ];

        $run->fill([
            'status' => 'started',
            'summary' => (string) $stats['summary'],
            'stats' => $stats,
            'started_at' => $run->started_at ?: now(),
            'finished_at' => null,
            'error_message' => null,
            'initiated_by' => $run->initiated_by ?: $initiatedBy,
        ])->save();

        return $run->fresh(['initiator']) ?? $run;
    }

    public function processImageBatchRun(KiposSyncRun $run, int $batchSize = 10): KiposSyncRun
    {
        if ($run->status !== 'started' || ! (bool) data_get($run->stats, 'browser_batch')) {
            return $run->fresh(['initiator']) ?? $run;
        }

        $lock = Cache::lock($this->imageBatchCacheKey($run->id, 'lock'), 300);
        if (! $lock->get()) {
            return $run->fresh(['initiator']) ?? $run;
        }

        $this->runInitiatedBy = $run->initiated_by;

        try {
            $this->kipos->assertEnabled();

            $stats = (array) ($run->stats ?? []);
            $productIds = $this->cachedImageBatchProductIds($run);
            $grouped = $this->cachedImageBatchGroupedRows($run);
            $locale = (string) ($stats['locale'] ?? $this->defaultLocale());
            $replaceExisting = (bool) ($stats['replace_existing'] ?? true);
            $totalProducts = max(count($productIds), (int) ($stats['total_products'] ?? 0));
            $offset = max(0, (int) ($stats['next_offset'] ?? $stats['processed_products'] ?? 0));
            $batchIds = array_slice($productIds, $offset, max(1, $batchSize));

            if ($batchIds === []) {
                return $this->finishImageBatchRun($run, $stats);
            }

            config([
                'media-library.max_file_size' => max((int) config('media-library.max_file_size', 0), 25 * 1024 * 1024),
            ]);

            $order = array_flip($batchIds);
            $products = Product::query()
                ->with([
                    'translations' => fn ($query) => $query->where('locale', $locale),
                    'media',
                    'optionValues',
                ])
                ->whereIn('id', $batchIds)
                ->get()
                ->sortBy(fn (Product $product): int => (int) ($order[$product->id] ?? PHP_INT_MAX))
                ->values();

            foreach ($products as $product) {
                $groupCode = strtoupper((string) $product->code);
                $imageRows = $grouped[$groupCode] ?? null;

                if (is_array($imageRows)) {
                    $stats['matched_products'] = (int) ($stats['matched_products'] ?? 0) + 1;
                    $this->mergeImageBatchProductStats($stats, $this->syncImageRowsForProduct(
                        product: $product,
                        imageRows: $imageRows,
                        replaceExisting: $replaceExisting,
                        locale: $locale
                    ));

                    continue;
                }

                if (! $replaceExisting && $this->productHasLocalImages($product)) {
                    $stats['skipped_existing'] = (int) ($stats['skipped_existing'] ?? 0) + 1;

                    continue;
                }

                $stats['fallback_product_lookups'] = (int) ($stats['fallback_product_lookups'] ?? 0) + 1;
                $imageRows = $this->remoteImageRowsForProduct($product, $grouped);
                if ($imageRows === []) {
                    $stats['skipped_without_remote'] = (int) ($stats['skipped_without_remote'] ?? 0) + 1;

                    continue;
                }

                $stats['matched_products'] = (int) ($stats['matched_products'] ?? 0) + 1;
                $this->mergeImageBatchProductStats($stats, $this->syncImageRowsForProduct(
                    product: $product,
                    imageRows: $imageRows,
                    replaceExisting: $replaceExisting,
                    locale: $locale
                ));
            }

            $processedProducts = min($totalProducts, $offset + count($batchIds));
            $stats['processed_products'] = $processedProducts;
            $stats['total_products'] = $totalProducts;
            $stats['progress_percent'] = $this->progressPercent($processedProducts, $totalProducts);
            $stats['next_offset'] = $processedProducts;

            if ($processedProducts >= $totalProducts) {
                return $this->finishImageBatchRun($run, $stats);
            }

            $summary = $this->imageBatchProgressSummary($stats);
            $stats['summary'] = $summary;

            $run->fill([
                'summary' => $summary,
                'stats' => $stats,
            ])->save();

            return $run->fresh(['initiator']) ?? $run;
        } catch (\Throwable $exception) {
            $run->fill([
                'status' => 'failed',
                'summary' => 'Execution failed.',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        } finally {
            $this->runInitiatedBy = null;
            optional($lock)->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function syncProducts(
        bool $createMissing,
        bool $updateExisting,
        bool $applyPricing,
        bool $applyQuantities,
        ?array $productCodeFilter = null,
        ?array $sourceRows = null
    ): array {
        $sourceRows ??= $this->mergedProductRows();
        $productCodeFilter = $productCodeFilter !== null
            ? $this->normalizeProductCodeFilter($productCodeFilter)
            : null;
        $matchedRequestedCodes = $productCodeFilter !== null
            ? $this->matchedProductCodeFilter($sourceRows, $productCodeFilter)
            : [];
        $rows = $productCodeFilter !== null
            ? $this->filterRowsByProductCodes($sourceRows, $productCodeFilter)
            : $sourceRows;
        $groups = $this->groupRowsByDepartment($rows);
        $sizeOption = $this->resolveSizeOption();
        $requiresOptions = collect($groups)->contains(fn (array $rows): bool => $this->groupUsesSizeOptions($rows));

        if ($requiresOptions && ! $sizeOption) {
            throw new RuntimeException('Enable `Use Options` and set a valid Kipos size option ID before importing size-based Kipos products.');
        }

        $locale = $this->defaultLocale();
        $categoryId = $this->importCategoryId();
        $products = Product::query()
            ->whereIn('code', array_keys($groups))
            ->get()
            ->keyBy(fn (Product $product): string => strtoupper((string) $product->code));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $variantRowsSynced = 0;

        foreach ($groups as $groupCode => $rows) {
            $existing = $products->get($groupCode);

            if ($existing && ! $updateExisting && $createMissing) {
                $skipped++;

                continue;
            }

            if (! $existing && ! $createMissing) {
                $skipped++;

                continue;
            }

            $product = $existing ?? new Product([
                'code' => $groupCode,
                'created_by' => $this->currentUserId(),
            ]);
            $isNew = ! $product->exists;

            $payload = (array) ($product->payload ?? []);
            $payload['kipos'] = array_merge((array) ($payload['kipos'] ?? []), [
                'department_code' => $groupCode,
                'default_item_code' => $this->itemCode($rows[0] ?? []),
                'variant_count' => count($rows),
                'last_sync_at' => now()->toIso8601String(),
                'sample_row' => $rows[0] ?? null,
            ]);

            $fill = [
                'code' => $groupCode,
                'sku' => $this->groupUsesSizeOptions($rows) ? $groupCode : $this->itemCode($rows[0] ?? []),
                'is_active' => $this->groupIsActive($rows),
                'payload' => $payload,
                'updated_by' => $this->currentUserId(),
            ];

            if ($isNew || $applyPricing) {
                $fill['base_price'] = $this->groupBasePrice($rows);
            }

            if ($isNew || $applyQuantities) {
                $fill['stock_qty'] = $this->groupQuantity($rows);
            }

            $product->fill($fill);
            $product->save();

            ProductTranslation::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $this->groupName($rows),
                    'slug' => Str::slug($this->groupName($rows).'-'.$groupCode),
                    'excerpt' => $this->groupExcerpt($rows),
                    'description' => $this->groupDescription($rows),
                    'payload' => ['kipos' => ['department_code' => $groupCode]],
                ]
            );

            if ($categoryId) {
                DB::table('category_product')->updateOrInsert(
                    [
                        'category_id' => $categoryId,
                        'product_id' => $product->id,
                    ],
                    [
                        'sort_order' => 0,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            if ($sizeOption) {
                $variantRowsSynced += $this->syncProductOptionRows(
                    product: $product,
                    rows: $rows,
                    option: $sizeOption,
                    locale: $locale,
                    applyPricing: $isNew || $applyPricing,
                    applyQuantities: $isNew || $applyQuantities
                );
            }

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'summary' => sprintf('Products: %d created, %d updated, %d skipped, %d option rows synced.', $created, $updated, $skipped, $variantRowsSynced),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'option_rows_synced' => $variantRowsSynced,
            'source_groups' => count($groups),
            'requested_codes' => $productCodeFilter ?? [],
            'matched_requested_codes' => count($matchedRequestedCodes),
            'unmatched_requested_codes' => $productCodeFilter !== null
                ? array_values(array_diff($productCodeFilter, $matchedRequestedCodes))
                : [],
            'price_field' => $this->priceField(),
            'category_id' => $categoryId,
            'size_option_id' => $sizeOption?->id,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncProductOptionRows(
        Product $product,
        array $rows,
        Option $option,
        string $locale,
        bool $applyPricing,
        bool $applyQuantities
    ): int {
        if (! $this->groupUsesSizeOptions($rows)) {
            ProductOptionValue::query()
                ->where('product_id', $product->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => $this->currentUserId(),
                    'updated_at' => now(),
                ]);

            return 0;
        }

        DB::table('catalog_option_product')->updateOrInsert(
            [
                'option_id' => $option->id,
                'product_id' => $product->id,
            ],
            [
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $existingRows = ProductOptionValue::query()
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('combination_hash');

        $synced = 0;
        $activeHashes = [];

        foreach (array_values($rows) as $index => $row) {
            $sizeCode = $this->sizeCode($row);
            if ($sizeCode === '') {
                continue;
            }

            $optionValue = OptionValue::query()->firstOrCreate(
                [
                    'option_id' => $option->id,
                    'code' => $sizeCode,
                ],
                [
                    'is_active' => true,
                    'sort_order' => $index,
                    'payload' => ['kipos' => ['size_code' => $sizeCode]],
                    'created_by' => $this->currentUserId(),
                    'updated_by' => $this->currentUserId(),
                ]
            );

            $optionValue->forceFill([
                'is_active' => true,
                'sort_order' => $index,
                'updated_by' => $this->currentUserId(),
            ])->save();

            OptionValueTranslation::query()->updateOrCreate(
                [
                    'option_value_id' => $optionValue->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $sizeCode,
                    'slug' => Str::slug('size-'.$sizeCode),
                    'payload' => ['kipos' => ['size_code' => $sizeCode]],
                ]
            );

            $hash = hash('sha256', 's:'.$optionValue->id);
            $activeHashes[] = $hash;

            $optionRow = $existingRows->get($hash) ?? new ProductOptionValue([
                'product_id' => $product->id,
                'created_by' => $this->currentUserId(),
            ]);

            $fill = [
                'product_id' => $product->id,
                'option_value_id' => $optionValue->id,
                'parent_option_value_id' => null,
                'mode' => 'single',
                'sku' => $this->itemCode($row),
                'sort_order' => $index,
                'is_active' => $this->rowIsActive($row),
                'combination_hash' => $hash,
                'payload' => [
                    'kipos' => [
                        'item_code' => $this->itemCode($row),
                        'department_code' => $this->departmentCode($row),
                        'size_code' => $sizeCode,
                        'row' => $row,
                    ],
                ],
                'updated_by' => $this->currentUserId(),
            ];

            if ($applyPricing) {
                $fill['price_override'] = max(0.0, $this->rowPrice($row));
            }

            if ($applyQuantities) {
                $fill['stock_qty'] = $this->rowQuantity($row);
            }

            $optionRow->fill($fill);
            $optionRow->save();
            $synced++;
        }

        if ($activeHashes !== []) {
            ProductOptionValue::query()
                ->where('product_id', $product->id)
                ->whereNotIn('combination_hash', $activeHashes)
                ->update([
                    'is_active' => false,
                    'updated_by' => $this->currentUserId(),
                    'updated_at' => now(),
                ]);
        }

        return $synced;
    }

    /**
     * @return array<string, mixed>
     */
    private function syncImages(bool $replaceExisting): array
    {
        $grouped = $this->remoteImageRowsByGroup();

        $locale = $this->defaultLocale();
        $products = Product::query()
            ->with([
                'translations' => fn ($query) => $query->where('locale', $locale),
                'media',
            ])
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->get()
            ->keyBy(fn (Product $product): string => strtoupper((string) $product->code));

        config([
            'media-library.max_file_size' => max((int) config('media-library.max_file_size', 0), 25 * 1024 * 1024),
        ]);

        $matchedProducts = 0;
        $updatedProducts = 0;
        $skippedExisting = 0;
        $skippedWithoutRemote = 0;
        $unmatchedProducts = 0;
        $mainAttached = 0;
        $galleryAttached = 0;
        $downloadFailures = 0;
        $fallbackLookups = 0;
        $processedCodes = [];

        foreach ($grouped as $groupCode => $imageRows) {
            $product = $products->get($groupCode);
            if (! $product) {
                $unmatchedProducts++;

                continue;
            }

            $processedCodes[$groupCode] = true;
            $matchedProducts++;
            $stats = $this->syncImageRowsForProduct(
                product: $product,
                imageRows: $imageRows,
                replaceExisting: $replaceExisting,
                locale: $locale
            );

            $updatedProducts += (int) ($stats['updated_products'] ?? 0);
            $skippedExisting += (int) ($stats['skipped_existing'] ?? 0);
            $skippedWithoutRemote += (int) ($stats['skipped_without_remote'] ?? 0);
            $mainAttached += (int) ($stats['main_images_attached'] ?? 0);
            $galleryAttached += (int) ($stats['gallery_images_attached'] ?? 0);
            $downloadFailures += (int) ($stats['download_failures'] ?? 0);
        }

        foreach ($products as $groupCode => $product) {
            if (isset($processedCodes[$groupCode])) {
                continue;
            }

            if (! $replaceExisting && $this->productHasLocalImages($product)) {
                $skippedExisting++;

                continue;
            }

            $fallbackLookups++;
            $imageRows = $this->remoteImageRowsForProduct($product, $grouped);
            if ($imageRows === []) {
                $skippedWithoutRemote++;

                continue;
            }

            $matchedProducts++;
            $stats = $this->syncImageRowsForProduct(
                product: $product,
                imageRows: $imageRows,
                replaceExisting: $replaceExisting,
                locale: $locale
            );

            $updatedProducts += (int) ($stats['updated_products'] ?? 0);
            $skippedExisting += (int) ($stats['skipped_existing'] ?? 0);
            $skippedWithoutRemote += (int) ($stats['skipped_without_remote'] ?? 0);
            $mainAttached += (int) ($stats['main_images_attached'] ?? 0);
            $galleryAttached += (int) ($stats['gallery_images_attached'] ?? 0);
            $downloadFailures += (int) ($stats['download_failures'] ?? 0);
        }

        return [
            'summary' => sprintf('Images: %d products updated, %d skipped with local images, %d skipped without remote images, %d unmatched.', $updatedProducts, $skippedExisting, $skippedWithoutRemote, $unmatchedProducts),
            'matched_products' => $matchedProducts,
            'updated_products' => $updatedProducts,
            'skipped_existing' => $skippedExisting,
            'skipped_without_remote' => $skippedWithoutRemote,
            'unmatched_products' => $unmatchedProducts,
            'main_images_attached' => $mainAttached,
            'gallery_images_attached' => $galleryAttached,
            'download_failures' => $downloadFailures,
            'replace_existing' => $replaceExisting,
            'fallback_product_lookups' => $fallbackLookups,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function cachedImageBatchProductIds(KiposSyncRun $run): array
    {
        $key = $this->imageBatchCacheKey($run->id, 'product_ids');
        $productIds = Cache::get($key);
        if (is_array($productIds)) {
            return collect($productIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        $productIds = Product::query()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        Cache::put($key, $productIds, now()->addMinutes(self::IMAGE_BATCH_CACHE_TTL_MINUTES));

        return $productIds;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function cachedImageBatchGroupedRows(KiposSyncRun $run): array
    {
        $key = $this->imageBatchCacheKey($run->id, 'grouped_rows');
        $grouped = Cache::get($key);
        if (is_array($grouped)) {
            return $grouped;
        }

        $grouped = $this->remoteImageRowsByGroup();
        Cache::put($key, $grouped, now()->addMinutes(self::IMAGE_BATCH_CACHE_TTL_MINUTES));

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $productStats
     */
    private function mergeImageBatchProductStats(array &$stats, array $productStats): void
    {
        foreach ([
            'updated_products',
            'skipped_existing',
            'skipped_without_remote',
            'main_images_attached',
            'gallery_images_attached',
            'download_failures',
        ] as $key) {
            $stats[$key] = (int) ($stats[$key] ?? 0) + (int) ($productStats[$key] ?? 0);
        }

        $details = $stats['download_failure_details'] ?? [];
        if (! is_array($details) || count($details) >= 5) {
            return;
        }

        $newDetails = $productStats['download_failure_details'] ?? [];
        if (! is_array($newDetails)) {
            return;
        }

        foreach ($newDetails as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $details[] = $detail;
            if (count($details) >= 5) {
                break;
            }
        }

        $stats['download_failure_details'] = array_values($details);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function finishImageBatchRun(KiposSyncRun $run, array $stats): KiposSyncRun
    {
        $summary = $this->imageSyncSummary($stats);
        $stats['summary'] = $summary;
        $stats['progress_percent'] = 100;
        $stats['next_offset'] = (int) ($stats['total_products'] ?? $stats['processed_products'] ?? 0);

        Cache::forget($this->imageBatchCacheKey($run->id, 'product_ids'));
        Cache::forget($this->imageBatchCacheKey($run->id, 'grouped_rows'));

        $run->fill([
            'status' => 'success',
            'summary' => $summary,
            'stats' => $stats,
            'error_message' => null,
            'finished_at' => now(),
        ])->save();

        return $run->fresh(['initiator']) ?? $run;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function imageBatchProgressSummary(array $stats): string
    {
        return sprintf(
            'Images: %d / %d products processed (%d%%). %d products updated, %d download failures.',
            (int) ($stats['processed_products'] ?? 0),
            (int) ($stats['total_products'] ?? 0),
            (int) ($stats['progress_percent'] ?? 0),
            (int) ($stats['updated_products'] ?? 0),
            (int) ($stats['download_failures'] ?? 0)
        );
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function imageSyncSummary(array $stats): string
    {
        return sprintf(
            'Images: %d products updated, %d skipped with local images, %d skipped without remote images, %d unmatched.',
            (int) ($stats['updated_products'] ?? 0),
            (int) ($stats['skipped_existing'] ?? 0),
            (int) ($stats['skipped_without_remote'] ?? 0),
            (int) ($stats['unmatched_products'] ?? 0)
        );
    }

    private function progressPercent(int $processed, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return min(100, max(0, (int) floor(($processed / $total) * 100)));
    }

    private function imageBatchCacheKey(int $runId, string $name): string
    {
        return 'kipos-sync-run.'.$runId.'.image-batch.'.$name;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function remoteImageRowsByGroup(): array
    {
        $rows = $this->kipos->getRows('sif_roba/getOdjelSlike');
        if ($rows === []) {
            $rows = $this->kipos->getRows('sif_roba/getSlike');
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (strtoupper($this->stringValue($row, 'TIP')) !== 'SLIKA') {
                continue;
            }

            $groupCode = $this->departmentCode($row);
            $url = $this->kipos->resolveImageUrl($this->stringValue($row, 'URL'));
            if ($groupCode === '' || $url === null) {
                continue;
            }

            $row['URL'] = $url;
            $grouped[$groupCode][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function remoteImageRowsForProduct(Product $product, ?array $groupedFallback = null): array
    {
        $departmentCode = strtoupper(trim((string) $product->code));
        $itemCodes = $this->productImageLookupItemCodes($product);

        $routes = [];
        foreach ($itemCodes as $itemCode) {
            $routes[] = 'sif_roba/getSlike/'.$itemCode;
            $routes[] = 'sif_roba/getItemSlike/'.$itemCode;
        }

        if ($departmentCode !== '') {
            $routes[] = 'sif_roba/getItemOdjelSlike/'.$departmentCode;
            $routes[] = 'sif_roba/getOdjelItemsSlike/'.$departmentCode;
            $routes[] = 'sif_roba/getOdjelSlike/'.$departmentCode;
        }

        $rows = [];
        foreach (array_values(array_unique($routes)) as $route) {
            $rows = array_merge($rows, $this->specificRemoteImageRows($route, $departmentCode, $itemCodes));
        }

        if ($rows !== []) {
            return $this->dedupeRemoteImageRows($rows);
        }

        $grouped = $groupedFallback ?? $this->remoteImageRowsByGroup();

        return $grouped[$departmentCode] ?? [];
    }

    private function productHasLocalImages(Product $product): bool
    {
        return $product->media
            ->whereIn('collection_name', ['product_main', 'product_gallery'])
            ->isNotEmpty();
    }

    /**
     * @param  array<int, string>  $itemCodes
     * @return array<int, array<string, mixed>>
     */
    private function specificRemoteImageRows(string $route, string $departmentCode, array $itemCodes): array
    {
        try {
            $rows = $this->kipos->getRows($route);
        } catch (\Throwable) {
            return [];
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (strtoupper($this->stringValue($row, 'TIP')) !== 'SLIKA') {
                continue;
            }

            $rowDepartmentCode = $this->departmentCode($row);
            $rowItemCode = $this->itemCode($row);
            if (
                $departmentCode !== ''
                && $rowDepartmentCode !== ''
                && $rowDepartmentCode !== $departmentCode
                && ! in_array($rowItemCode, $itemCodes, true)
            ) {
                continue;
            }

            $url = $this->kipos->resolveImageUrl($this->stringValue($row, 'URL'));
            if ($url === null) {
                continue;
            }

            $row['URL'] = $url;
            $row['_source_route'] = $route;
            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @return array<int, string>
     */
    private function productImageLookupItemCodes(Product $product): array
    {
        $product->loadMissing('optionValues');

        return collect([
            data_get($product->payload, 'kipos.default_item_code'),
            $product->sku,
            $product->code,
        ])
            ->merge($product->optionValues->pluck('sku'))
            ->merge($product->optionValues->map(fn (ProductOptionValue $row): mixed => data_get($row->payload, 'kipos.item_code')))
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function dedupeRemoteImageRows(array $rows): array
    {
        $deduped = [];

        foreach ($rows as $row) {
            $key = strtoupper(trim((string) ($row['URL'] ?? ''))).'|'.strtoupper(trim((string) ($row['NAZIV'] ?? '')));
            if ($key === '|' || isset($deduped[$key])) {
                continue;
            }

            $deduped[$key] = $row;
        }

        return array_values($deduped);
    }

    /**
     * @param  array<int, array<string, mixed>>  $imageRows
     * @return array<string, mixed>
     */
    private function syncImageRowsForProduct(Product $product, array $imageRows, bool $replaceExisting, string $locale): array
    {
        $stats = [
            'updated_products' => 0,
            'skipped_existing' => 0,
            'skipped_without_remote' => 0,
            'main_images_attached' => 0,
            'gallery_images_attached' => 0,
            'download_failures' => 0,
            'download_failure_details' => [],
            'replace_existing' => $replaceExisting,
        ];

        $existingMedia = $product->media
            ->whereIn('collection_name', ['product_main', 'product_gallery'])
            ->values();

        if (! $replaceExisting && $existingMedia->isNotEmpty()) {
            $stats['skipped_existing']++;

            return $stats;
        }

        $usableRows = collect($imageRows)
            ->unique(fn (array $row): string => (string) ($row['URL'] ?? ''))
            ->sortBy(function (array $row): array {
                return [
                    $this->boolValue($row, 'GLAVNA') ? 0 : 1,
                    strtolower((string) ($row['NAZIV'] ?? '')),
                ];
            })
            ->values();

        if ($usableRows->isEmpty()) {
            $stats['skipped_without_remote']++;

            return $stats;
        }

        $label = trim((string) ($product->translations->first()?->name ?: $product->code));
        $attachedAny = false;
        $hasMain = false;
        $clearedExistingGallery = false;
        $tempFiles = [];

        try {
            foreach ($usableRows as $row) {
                $downloaded = $this->downloadImage($row);
                if (! (bool) ($downloaded['ok'] ?? false)) {
                    $stats['download_failures']++;
                    $this->rememberImageDownloadFailure($stats, $row, $downloaded);

                    continue;
                }

                $path = (string) ($downloaded['path'] ?? '');
                $fileName = (string) ($downloaded['file_name'] ?? '');
                if ($path === '' || $fileName === '') {
                    $stats['download_failures']++;
                    $this->rememberImageDownloadFailure($stats, $row, [
                        'url' => (string) ($downloaded['url'] ?? ($row['URL'] ?? '')),
                        'reason' => 'missing_download_payload',
                        'message' => 'Downloaded image payload is incomplete.',
                    ]);

                    continue;
                }

                $tempFiles[] = $path;

                $collection = ! $hasMain ? 'product_main' : 'product_gallery';

                try {
                    $this->attachImage($product, $path, $fileName, $collection, $label, $locale);
                } catch (\Throwable $exception) {
                    $stats['download_failures']++;
                    $this->rememberImageDownloadFailure($stats, $row, [
                        'url' => (string) ($downloaded['url'] ?? ($row['URL'] ?? '')),
                        'reason' => 'attach_failed',
                        'message' => $exception->getMessage(),
                        'file_name' => $fileName,
                    ]);

                    continue;
                }

                if (! $hasMain) {
                    $stats['main_images_attached']++;
                    $hasMain = true;

                    if ($replaceExisting && ! $clearedExistingGallery) {
                        $product->clearMediaCollection('product_gallery');
                        $clearedExistingGallery = true;
                    }
                } else {
                    $stats['gallery_images_attached']++;
                }

                $attachedAny = true;
            }
        } finally {
            foreach ($tempFiles as $path) {
                @unlink($path);
            }
        }

        if ($attachedAny) {
            $stats['updated_products']++;
        } elseif ($usableRows->isEmpty()) {
            $stats['skipped_without_remote']++;
        }

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mergedProductRows(): array
    {
        $baseRows = $this->kipos->getRows('sif_roba/getitems');
        $extendedRows = $this->kipos->getRows('sif_roba/getitemsextended');

        $merged = [];
        foreach ($baseRows as $row) {
            $itemCode = $this->itemCode($row);
            if ($itemCode === '') {
                continue;
            }

            $merged[$itemCode] = $row;
        }

        foreach ($extendedRows as $row) {
            $itemCode = $this->itemCode($row);
            if ($itemCode === '') {
                continue;
            }

            $merged[$itemCode] = array_merge($merged[$itemCode] ?? [], $row);
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, string>  $codes
     * @return list<array<string, mixed>>
     */
    private function importProductRowsForCodes(array $codes): array
    {
        return $this->filterRowsByProductCodes(
            $this->kipos->getRows('sif_roba/getitems'),
            $codes
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedStockRows(): array
    {
        $warehouses = $this->warehouseFilter();
        if ($warehouses === []) {
            return $this->mergedProductRows();
        }

        $rows = [];
        foreach ($warehouses as $warehouse) {
            $rows = array_merge(
                $rows,
                $this->kipos->getRows('sif_roba/getZalihaK', ['idskl' => $warehouse])
            );
        }

        $grouped = [];

        foreach ($rows as $row) {
            $warehouseId = strtoupper($this->stringValue($row, 'IDSKL'));
            if ($warehouses !== [] && ! in_array($warehouseId, $warehouses, true)) {
                continue;
            }

            $itemCode = $this->itemCode($row);
            if ($itemCode === '') {
                continue;
            }

            $grouped[$itemCode] ??= [
                'IDROBA' => $itemCode,
                'IDODJEL' => $this->departmentCode($row),
                'ZALIHAK' => 0,
                'DATUM_USER' => $this->stringValue($row, 'DATUM_USER'),
            ];

            $grouped[$itemCode]['ZALIHAK'] += (float) $this->rowQuantity($row);
        }

        return array_values($grouped);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupRowsByDepartment(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $groupCode = $this->departmentCode($row);
            if ($groupCode === '') {
                continue;
            }

            $grouped[$groupCode] ??= [];
            $grouped[$groupCode][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  mixed  $codes
     * @return array<int, string>
     */
    private function normalizeProductCodeFilter(mixed $codes): array
    {
        if (is_string($codes)) {
            $codes = preg_split('/[\s,;]+/', $codes) ?: [];
        }

        if (! is_array($codes)) {
            return [];
        }

        return collect($codes)
            ->flatMap(fn ($code): array => is_string($code) ? (preg_split('/[\s,;]+/', $code) ?: []) : [$code])
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $codes
     * @return list<array<string, mixed>>
     */
    private function filterRowsByProductCodes(array $rows, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => in_array($this->itemCode($row), $codes, true)
                || in_array($this->departmentCode($row), $codes, true)
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function matchedProductCodeFilter(array $rows, array $codes): array
    {
        $matched = [];

        foreach ($rows as $row) {
            $itemCode = $this->itemCode($row);
            $departmentCode = $this->departmentCode($row);

            if (in_array($itemCode, $codes, true)) {
                $matched[] = $itemCode;
            }

            if (in_array($departmentCode, $codes, true)) {
                $matched[] = $departmentCode;
            }
        }

        return collect($matched)->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function itemCode(array $row): string
    {
        return strtoupper(trim($this->stringValue($row, 'IDROBA')));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function departmentCode(array $row): string
    {
        $department = strtoupper(trim($this->stringValue($row, 'IDODJEL')));
        if ($department !== '') {
            return $department;
        }

        $itemCode = $this->itemCode($row);
        if ($itemCode === '') {
            return '';
        }

        if (str_contains($itemCode, '.')) {
            return strtoupper((string) Str::beforeLast($itemCode, '.'));
        }

        return $itemCode;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sizeCode(array $row): string
    {
        $size = strtoupper(trim($this->stringValue($row, 'IDVELICINA')));
        if ($size !== '') {
            return $size;
        }

        $itemCode = $this->itemCode($row);
        if ($itemCode !== '' && str_contains($itemCode, '.')) {
            return strtoupper((string) Str::afterLast($itemCode, '.'));
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupUsesSizeOptions(array $rows): bool
    {
        if (count($rows) > 1) {
            return true;
        }

        return $this->sizeCode($rows[0] ?? []) !== '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupBasePrice(array $rows): float
    {
        $source = collect($rows)
            ->filter(fn (array $row): bool => $this->rowPrice($row) > 0)
            ->values();

        if ($source->isEmpty()) {
            return 0.0;
        }

        return round((float) $source->min(fn (array $row): float => $this->rowPrice($row)), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function lowest30DaysPrice(array $rows): ?float
    {
        $values = collect($rows)
            ->map(fn (array $row): float => round($this->floatValue($row, 'CIJENA_NAJNIZA_30DANA'), 2))
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return (float) $values->min();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupActionPrice(array $rows): float
    {
        $field = $this->actionPriceField();
        $values = collect($rows)
            ->filter(fn (array $row): bool => $this->rowIsActive($row))
            ->map(fn (array $row): float => round($this->floatValue($row, $field), 2))
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        if ($values->isEmpty()) {
            return 0.0;
        }

        return (float) $values->min();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupQuantity(array $rows): int
    {
        return (int) collect($rows)->sum(fn (array $row): int => $this->rowQuantity($row));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowPrice(array $row): float
    {
        $price = $this->floatValue($row, $this->priceField());
        if ($price > 0) {
            return round($price, 2);
        }

        foreach (['CIJENA_MPC', 'CIJENA_EUR_MPC', 'CIJENA_EUR'] as $fallbackKey) {
            $fallback = $this->floatValue($row, $fallbackKey);
            if ($fallback > 0) {
                return round($fallback, 2);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowQuantity(array $row): int
    {
        $itemCode = $this->itemCode($row);
        $departmentCode = $this->departmentCode($row);

        foreach ($this->quantityOverrideMap() as $quantity => $codes) {
            if (in_array($itemCode, $codes, true) || in_array($departmentCode, $codes, true)) {
                return $quantity;
            }
        }

        return max(0, (int) round($this->floatValue($row, 'ZALIHAK')));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsActive(array $row): bool
    {
        $hidden = $this->boolValue($row, 'HIDE');
        if ($hidden === true) {
            return false;
        }

        return trim($this->stringValue($row, 'DATUM_DEAKTIVIRANJA')) === '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupIsActive(array $rows): bool
    {
        return collect($rows)->contains(fn (array $row): bool => $this->rowIsActive($row));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupName(array $rows): string
    {
        $first = $rows[0] ?? [];

        return trim($this->stringValue($first, 'NAZIV_ODJELA', 'NAZIV')) ?: $this->departmentCode($first);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupExcerpt(array $rows): ?string
    {
        $first = $rows[0] ?? [];
        $excerpt = trim($this->stringValue($first, 'NAZIV_DODATNI'));

        return $excerpt !== '' ? $excerpt : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function groupDescription(array $rows): ?string
    {
        $first = $rows[0] ?? [];
        $description = trim($this->stringValue($first, 'OPIS_ODJEL', 'NAZIV_DODATNI'));

        return $description !== '' ? $description : null;
    }

    private function resolveSizeOption(): ?Option
    {
        if (! $this->catalogFeatures->useOptions()) {
            return null;
        }

        $optionId = (int) ($this->syncSettings()['kipos_sync_size_option_id'] ?? 0);
        if ($optionId > 0) {
            return Option::query()->find($optionId);
        }

        return Option::query()->where('code', 'size')->first();
    }

    private function importCategoryId(): ?int
    {
        $value = (int) ($this->syncSettings()['kipos_sync_import_category_id'] ?? 0);

        return $value > 0 ? $value : null;
    }

    private function defaultLocale(): string
    {
        return strtolower(trim((string) ($this->syncSettings()['kipos_sync_default_locale'] ?? config('app.locale', 'hr'))));
    }

    private function normalizeSyncLocale(?string $locale = null): string
    {
        $locale = strtolower(trim((string) $locale));

        return $locale !== '' ? $locale : $this->defaultLocale();
    }

    private function priceField(): string
    {
        return strtoupper(trim((string) ($this->syncSettings()['kipos_sync_price_field'] ?? 'CIJENA_MPC')));
    }

    private function actionPriceField(): string
    {
        return strtoupper(trim((string) ($this->syncSettings()['kipos_sync_action_price_field'] ?? 'AKCIJSKA_CIJENA')));
    }

    /**
     * @return array<int, string>
     */
    private function warehouseFilter(): array
    {
        return collect(explode(',', (string) ($this->syncSettings()['kipos_sync_stock_warehouse_ids'] ?? '')))
            ->map(fn ($item): string => strtoupper(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function quantityOverrideMap(): array
    {
        $raw = trim((string) ($this->syncSettings()['kipos_sync_quantity_overrides'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $map = [];
            foreach ($decoded as $quantity => $codes) {
                $qty = max(0, (int) $quantity);
                if ($qty <= 0) {
                    continue;
                }

                $map[$qty] = collect(is_array($codes) ? $codes : explode(',', (string) $codes))
                    ->map(fn ($item): string => strtoupper(trim((string) $item)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            return $map;
        }

        $map = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || (! str_contains($line, ':') && ! str_contains($line, '='))) {
                continue;
            }

            [$quantity, $codes] = preg_split('/[:=]/', $line, 2) ?: [null, null];
            $qty = max(0, (int) $quantity);
            if ($qty <= 0) {
                continue;
            }

            $map[$qty] = collect(explode(',', (string) $codes))
                ->map(fn ($item): string => strtoupper(trim((string) $item)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stringValue(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function floatValue(array $row, string ...$keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if (is_numeric($value)) {
                return (float) $value;
            }

            $normalized = trim((string) $value);
            $normalized = str_replace([' ', "\xc2\xa0"], '', $normalized);

            if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
            }

            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function boolValue(array $row, string ...$keys): ?bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = filter_var($row[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function actionCode(string $groupCode): string
    {
        return substr('kipos-'.$groupCode, 0, 120);
    }

    private function currentUserId(): ?int
    {
        $userId = $this->runInitiatedBy ?? auth()->id();

        return $userId ? (int) $userId : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJsonColumn(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function downloadImage(array $row): array
    {
        $url = trim((string) ($row['URL'] ?? ''));
        if ($url === '') {
            return [
                'ok' => false,
                'url' => '',
                'reason' => 'missing_url',
                'message' => 'Image row does not contain a URL.',
            ];
        }

        $settings = $this->kipos->getSettings();
        $timeout = max(5, min(120, (int) ($settings['kipos_api_timeout_seconds'] ?? 30)));

        $client = app(\Illuminate\Http\Client\Factory::class)
            ->connectTimeout(min($timeout, 15))
            ->timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'AGShop-Kipos-Connector/1.0',
            ]);

        if (! (bool) ($settings['kipos_api_verify_tls'] ?? true)) {
            $client = $client->withoutVerifying();
        }

        try {
            $response = $client->get($url);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'url' => $url,
                'reason' => 'request_failed',
                'message' => $exception->getMessage(),
            ];
        }

        if (! $response->successful() || trim($response->body()) === '') {
            return [
                'ok' => false,
                'url' => $url,
                'status' => $response->status(),
                'reason' => ! $response->successful() ? 'http_status' : 'empty_body',
                'message' => ! $response->successful()
                    ? 'Remote image request returned HTTP '.$response->status().'.'
                    : 'Remote image response body is empty.',
            ];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'kipos_');
        if ($tempPath === false) {
            return [
                'ok' => false,
                'url' => $url,
                'reason' => 'temp_file_failed',
                'message' => 'Failed to allocate a temp file for the downloaded image.',
            ];
        }

        file_put_contents($tempPath, $response->body());
        if (! is_file($tempPath) || filesize($tempPath) <= 0) {
            @unlink($tempPath);

            return [
                'ok' => false,
                'url' => $url,
                'status' => $response->status(),
                'reason' => 'empty_file',
                'message' => 'Downloaded file is empty.',
            ];
        }

        $mimeType = $this->detectImageMimeType($tempPath, (string) $response->header('Content-Type', ''));
        if ($mimeType === null || ! in_array($mimeType, $this->acceptedProductImageMimeTypes(), true)) {
            @unlink($tempPath);

            return [
                'ok' => false,
                'url' => $url,
                'status' => $response->status(),
                'reason' => 'invalid_mime',
                'message' => 'Remote response is not a supported image.',
                'mime_type' => $mimeType ?: $this->normalizeMimeType((string) $response->header('Content-Type', '')),
            ];
        }

        $fileName = $this->resolveImageFileName(
            rowName: trim((string) ($row['NAZIV'] ?? '')),
            url: $url,
            mimeType: $mimeType,
            tempPath: $tempPath
        );
        if ($fileName === '') {
            $fileName = basename($tempPath).'.jpg';
        }

        return [
            'ok' => true,
            'url' => $url,
            'path' => $tempPath,
            'file_name' => $fileName,
        ];
    }

    private function attachImage(Product $product, string $path, string $fileName, string $collection, string $label, string $locale): void
    {
        $product->addMedia(new HttpFile($path))
            ->usingName(pathinfo($fileName, PATHINFO_FILENAME) ?: $label)
            ->usingFileName($fileName)
            ->preservingOriginal()
            ->withCustomProperties([
                'alt' => [$locale => $label],
            ])
            ->toMediaCollection($collection);
    }

    private function detectImageMimeType(string $path, string $contentTypeHeader): ?string
    {
        $candidates = [
            $this->normalizeMimeType($contentTypeHeader),
            $this->normalizeMimeType((string) (mime_content_type($path) ?: '')),
        ];

        $imageSize = @getimagesize($path);
        if (is_array($imageSize) && isset($imageSize['mime'])) {
            $candidates[] = $this->normalizeMimeType((string) $imageSize['mime']);
        }

        foreach ($candidates as $mimeType) {
            if ($mimeType !== '' && str_starts_with($mimeType, 'image/')) {
                return $mimeType;
            }
        }

        return null;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        if ($mimeType === '') {
            return '';
        }

        $parts = explode(';', $mimeType);

        return trim((string) ($parts[0] ?? ''));
    }

    /**
     * @return list<string>
     */
    private function acceptedProductImageMimeTypes(): array
    {
        $modelProfiles = (array) config('media_profiles.models', []);
        $productProfile = (array) ($modelProfiles[Product::class] ?? []);
        $collections = (array) ($productProfile['collections'] ?? []);
        $mimeTypes = collect([
            (array) (($collections['product_main'] ?? [])['accept_mime_types'] ?? []),
            (array) (($collections['product_gallery'] ?? [])['accept_mime_types'] ?? []),
        ])
            ->flatten()
            ->map(fn ($mimeType): string => $this->normalizeMimeType((string) $mimeType))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $mimeTypes !== [] ? $mimeTypes : ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    }

    private function resolveImageFileName(string $rowName, string $url, string $mimeType, string $tempPath): string
    {
        $fileName = trim($rowName);
        if ($fileName === '') {
            $pathName = (string) parse_url($url, PHP_URL_PATH);
            $fileName = basename($pathName);
        }

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $fileName = basename($tempPath);
        }

        $fileName = preg_replace('/[^\pL\pN._-]+/u', '_', $fileName) ?? $fileName;
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $acceptedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
        $targetExtension = $this->extensionForMimeType($mimeType);

        if (
            $targetExtension !== null
            && ($extension === '' || ! in_array($extension, $acceptedExtensions, true) || ! $this->extensionMatchesMimeType($extension, $targetExtension))
        ) {
            $baseName = trim((string) pathinfo($fileName, PATHINFO_FILENAME), '._-');
            $fileName = ($baseName !== '' ? $baseName : 'kipos-image').'.'.$targetExtension;
        }

        return $fileName;
    }

    private function extensionForMimeType(string $mimeType): ?string
    {
        return match ($this->normalizeMimeType($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => null,
        };
    }

    private function extensionMatchesMimeType(string $extension, string $targetExtension): bool
    {
        $extension = strtolower(trim($extension));
        $targetExtension = strtolower(trim($targetExtension));

        if ($targetExtension === 'jpg') {
            return in_array($extension, ['jpg', 'jpeg'], true);
        }

        return $extension === $targetExtension;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $failure
     */
    private function rememberImageDownloadFailure(array &$stats, array $row, array $failure): void
    {
        $details = $stats['download_failure_details'] ?? [];
        if (! is_array($details) || count($details) >= 5) {
            return;
        }

        $details[] = array_filter([
            'file_name' => trim((string) ($failure['file_name'] ?? ($row['NAZIV'] ?? ''))),
            'url' => trim((string) ($failure['url'] ?? ($row['URL'] ?? ''))),
            'status' => isset($failure['status']) ? (int) $failure['status'] : null,
            'reason' => trim((string) ($failure['reason'] ?? 'download_failed')),
            'message' => trim((string) ($failure['message'] ?? '')),
            'mime_type' => trim((string) ($failure['mime_type'] ?? '')),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $stats['download_failure_details'] = array_values($details);
    }
}
