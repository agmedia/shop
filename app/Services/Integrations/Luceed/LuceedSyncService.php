<?php

namespace App\Services\Integrations\Luceed;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Action\CatalogActionTranslation;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Manufacturer\ManufacturerTranslation;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Integrations\LuceedSyncRun;
use App\Models\Sales\Order\Order;
use App\Models\Sales\Order\OrderHistory;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\Local\PaymentMethod;
use App\Services\Settings\SystemSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LuceedSyncService
{
    public function __construct(
        private readonly LuceedSdkService $luceed,
        private readonly SystemSettingsService $settings
    ) {
    }

    /**
     * @return array<string, array{title:string,description:string,actions:array<int,array{key:string,label:string,description:string}>}>
     */
    public function actionGroups(): array
    {
        return [
            'catalog' => [
                'title' => 'Catalog Foundations',
                'description' => 'Category, manufacturer, warehouse and payment dictionaries.',
                'actions' => [
                    ['key' => 'import_categories', 'label' => 'Import Categories', 'description' => 'Create missing category rows from Luceed groups.'],
                    ['key' => 'update_categories', 'label' => 'Update Categories', 'description' => 'Refresh category names/status/payload for existing rows.'],
                    ['key' => 'sync_category_uids', 'label' => 'Sync Category UIDs', 'description' => 'Only update Luceed UID references by category code.'],
                    ['key' => 'import_manufacturers', 'label' => 'Import Manufacturers', 'description' => 'Create missing manufacturer rows from Luceed brands.'],
                    ['key' => 'sync_manufacturer_uids', 'label' => 'Sync Manufacturer UIDs', 'description' => 'Only update Luceed UID references by manufacturer code.'],
                    ['key' => 'import_warehouses', 'label' => 'Import Warehouses', 'description' => 'Store Luceed warehouse snapshot and default stock warehouse list.'],
                    ['key' => 'import_payments', 'label' => 'Import Payment Methods', 'description' => 'Sync Luceed payment types into local Payment Methods table.'],
                ],
            ],
            'products' => [
                'title' => 'Products And Prices',
                'description' => 'Product base sync, prices, quantities, promotions and related links.',
                'actions' => [
                    ['key' => 'import_products', 'label' => 'Import Products', 'description' => 'Create only missing products from Luceed artikli list.'],
                    ['key' => 'update_products', 'label' => 'Update Products', 'description' => 'Upsert product base data (name, code, active, payload).'],
                    ['key' => 'update_product_additional_data', 'label' => 'Update Additional Product Data', 'description' => 'Refresh unit/package metadata from Luceed fields.'],
                    ['key' => 'import_related_products', 'label' => 'Import Related Products', 'description' => 'Attach related product code lists from Luceed bundle endpoint.'],
                    ['key' => 'import_actions', 'label' => 'Import Actions', 'description' => 'Create/update catalog actions from Luceed action feeds.'],
                    ['key' => 'import_action_prices_last_30_days', 'label' => 'Import Last 30d Action Prices', 'description' => 'Store last 30-day promo pricing markers in product payload.'],
                    ['key' => 'update_prices', 'label' => 'Update Prices', 'description' => 'Update base product price from Luceed cjenik.'],
                    ['key' => 'update_b2b_prices', 'label' => 'Update B2B Prices', 'description' => 'Update partner pricing payload from Luceed cjenikpartner diff.'],
                    ['key' => 'update_vpc_prices', 'label' => 'Update VPC Prices', 'description' => 'Refresh wholesale/secondary price payload from cjenik rows.'],
                    ['key' => 'update_quantities', 'label' => 'Update Quantities', 'description' => 'Update stock quantity from Luceed stanje zalihe.'],
                    ['key' => 'update_prices_and_quantities', 'label' => 'Update Prices + Quantities', 'description' => 'Run both price and quantity sync in sequence.'],
                ],
            ],
            'orders' => [
                'title' => 'Order Status Sync',
                'description' => 'Status dictionary and order timeline updates.',
                'actions' => [
                    ['key' => 'update_order_statuses', 'label' => 'Update Order Statuses', 'description' => 'Sync statuses from Luceed and apply to local web orders.'],
                    ['key' => 'update_b2b_order_statuses', 'label' => 'Update B2B Order Statuses', 'description' => 'Sync statuses from Luceed for local B2B orders only.'],
                    ['key' => 'check_order_status_duration', 'label' => 'Check Order Status Duration', 'description' => 'Report long-running local orders in non-final statuses.'],
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
            'luceed_sync_default_locale' => (string) config('app.locale', 'en'),
            'luceed_sync_article_limit' => 0,
            'luceed_sync_stock_warehouses' => '',
            'luceed_sync_partner_type' => 'sifra',
            'luceed_sync_partner_value' => '',
            'luceed_sync_partner_currency' => 'EUR',
            'luceed_sync_orders_lookback_days' => 30,
            'luceed_sync_status_codes' => '',
            'luceed_sync_status_duration_days' => 4,
            'luceed_sync_order_status_domain' => 'NaloziProdaje',
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

    public function run(string $actionKey, ?int $initiatedBy = null): LuceedSyncRun
    {
        $catalog = $this->flatActionCatalog();
        abort_unless(isset($catalog[$actionKey]), 404, 'Unknown Luceed sync action.');

        $run = LuceedSyncRun::query()->create([
            'action_key' => $actionKey,
            'action_label' => $catalog[$actionKey]['label'],
            'status' => 'started',
            'started_at' => now(),
            'initiated_by' => $initiatedBy,
        ]);

        try {
            $this->luceed->assertEnabled();

            $handler = $this->handlerMap()[$actionKey] ?? null;
            if (! $handler) {
                throw new \RuntimeException('No handler configured for Luceed sync action: '.$actionKey);
            }

            /** @var array<string, mixed> $result */
            $result = $this->{$handler}();
            $summary = (string) ($result['summary'] ?? 'Completed.');

            $run->fill([
                'status' => 'success',
                'summary' => $summary,
                'stats' => Arr::except($result, ['summary']),
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
        }

        return $run->fresh(['initiator']) ?? $run;
    }

    /**
     * @return array<string, string>
     */
    private function handlerMap(): array
    {
        return [
            'import_categories' => 'handleImportCategories',
            'update_categories' => 'handleUpdateCategories',
            'sync_category_uids' => 'handleSyncCategoryUids',
            'import_manufacturers' => 'handleImportManufacturers',
            'sync_manufacturer_uids' => 'handleSyncManufacturerUids',
            'import_warehouses' => 'handleImportWarehouses',
            'import_payments' => 'handleImportPayments',
            'import_products' => 'handleImportProducts',
            'update_products' => 'handleUpdateProducts',
            'update_product_additional_data' => 'handleUpdateProductAdditionalData',
            'import_related_products' => 'handleImportRelatedProducts',
            'import_actions' => 'handleImportActions',
            'import_action_prices_last_30_days' => 'handleImportActionPricesLast30Days',
            'update_prices' => 'handleUpdatePrices',
            'update_b2b_prices' => 'handleUpdateB2bPrices',
            'update_vpc_prices' => 'handleUpdateVpcPrices',
            'update_quantities' => 'handleUpdateQuantities',
            'update_prices_and_quantities' => 'handleUpdatePricesAndQuantities',
            'update_order_statuses' => 'handleUpdateOrderStatuses',
            'update_b2b_order_statuses' => 'handleUpdateB2bOrderStatuses',
            'check_order_status_duration' => 'handleCheckOrderStatusDuration',
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
     * @return array<string, mixed>
     */
    private function handleImportCategories(): array
    {
        return $this->syncCategories(updateExisting: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateCategories(): array
    {
        return $this->syncCategories(updateExisting: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCategories(bool $updateExisting): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->grupeArtikala()->list());
        $locale = (string) $this->syncSettings()['luceed_sync_default_locale'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $codeToParent = [];

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['grupa_artikala', 'grupa', 'sifra', 'oznaka', 'id']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $name = $this->firstString($row, ['naziv', 'name', 'opis']) ?? $code;
            $uid = $this->firstString($row, ['grupa_uid', 'uid', 'id_uid']);
            $parentCode = $this->firstString($row, ['nadgrupa', 'parent', 'grupa_nadredena', 'roditelj']);
            $sortOrder = $this->firstInt($row, ['sort', 'sort_order', 'redni_broj', 'rbr']) ?? 0;
            $isActive = $this->firstBool($row, ['enabled', 'active', 'aktivan', 'status']) ?? true;

            $category = Category::query()
                ->where('scope', Category::SCOPE_CATALOG)
                ->where('code', $code)
                ->first();

            if ($category === null) {
                $category = new Category([
                    'scope' => Category::SCOPE_CATALOG,
                    'code' => $code,
                    'created_by' => auth()->id(),
                ]);

                $created++;
            } elseif (! $updateExisting) {
                $skipped++;
                continue;
            } else {
                $updated++;
            }

            $payload = (array) ($category->payload ?? []);
            $payload['luceed'] = [
                'uid' => $uid,
                'code' => $code,
                'row' => $row,
            ];

            $category->fill([
                'is_active' => $isActive,
                'show_in_menu' => true,
                'sort_order' => max(0, $sortOrder),
                'payload' => $payload,
                'updated_by' => auth()->id(),
            ]);
            $category->save();

            CategoryTranslation::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'scope' => Category::SCOPE_CATALOG,
                    'locale' => $locale,
                ],
                [
                    'name' => $name,
                    'slug' => Str::slug($name.'-'.$code),
                    'description' => $this->firstString($row, ['opis', 'description']),
                    'payload' => ['luceed' => ['code' => $code]],
                ]
            );

            if ($parentCode !== null) {
                $codeToParent[$code] = $parentCode;
            }
        }

        $linked = 0;
        if ($codeToParent !== []) {
            $codeMap = Category::query()
                ->where('scope', Category::SCOPE_CATALOG)
                ->whereIn('code', array_values(array_unique(array_merge(array_keys($codeToParent), array_values($codeToParent)))))
                ->get(['id', 'code'])
                ->pluck('id', 'code');

            foreach ($codeToParent as $code => $parentCode) {
                $categoryId = $codeMap[$code] ?? null;
                $parentId = $codeMap[$parentCode] ?? null;

                if (! $categoryId || ! $parentId || $categoryId === $parentId) {
                    continue;
                }

                $category = Category::query()->find($categoryId);
                if (! $category || (int) $category->parent_id === (int) $parentId) {
                    continue;
                }

                $category->parent_id = $parentId;
                $category->save();
                $linked++;
            }
        }

        return [
            'summary' => sprintf('Categories synced: %d created, %d updated, %d skipped.', $created, $updated, $skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'parent_links_updated' => $linked,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleSyncCategoryUids(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->grupeArtikala()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['grupa_artikala', 'grupa', 'sifra', 'oznaka']);
            $uid = $this->firstString($row, ['grupa_uid', 'uid']);

            if ($code === null || $uid === null) {
                $skipped++;
                continue;
            }

            $category = Category::query()
                ->where('scope', Category::SCOPE_CATALOG)
                ->where('code', $code)
                ->first();

            if (! $category) {
                $skipped++;
                continue;
            }

            $payload = (array) ($category->payload ?? []);
            $payload['luceed'] = array_merge((array) ($payload['luceed'] ?? []), [
                'uid' => $uid,
                'code' => $code,
            ]);

            $category->payload = $payload;
            $category->updated_by = auth()->id();
            $category->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Category UID sync completed: %d updated, %d skipped.', $updated, $skipped),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportManufacturers(): array
    {
        return $this->syncManufacturers();
    }

    /**
     * @return array<string, mixed>
     */
    private function syncManufacturers(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->robneMarke()->list());
        $locale = (string) $this->syncSettings()['luceed_sync_default_locale'];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['robna_marka', 'marka', 'sifra', 'oznaka', 'id']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $name = $this->firstString($row, ['naziv', 'name']) ?? $code;
            $uid = $this->firstString($row, ['robna_marka_uid', 'uid']);

            $manufacturer = Manufacturer::query()->where('code', $code)->first();
            if ($manufacturer === null) {
                $manufacturer = new Manufacturer([
                    'code' => $code,
                    'created_by' => auth()->id(),
                ]);
                $created++;
            } else {
                $updated++;
            }

            $payload = (array) ($manufacturer->payload ?? []);
            $payload['luceed'] = [
                'uid' => $uid,
                'code' => $code,
                'row' => $row,
            ];

            $manufacturer->fill([
                'is_active' => $this->firstBool($row, ['enabled', 'active', 'aktivan', 'status']) ?? true,
                'sort_order' => $this->firstInt($row, ['sort', 'sort_order', 'redni_broj']) ?? 0,
                'payload' => $payload,
                'updated_by' => auth()->id(),
            ]);
            $manufacturer->save();

            ManufacturerTranslation::query()->updateOrCreate(
                [
                    'manufacturer_id' => $manufacturer->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $name,
                    'slug' => Str::slug($name.'-'.$code),
                    'description' => $this->firstString($row, ['opis', 'description']),
                    'payload' => ['luceed' => ['code' => $code]],
                ]
            );
        }

        return [
            'summary' => sprintf('Manufacturers synced: %d created, %d updated, %d skipped.', $created, $updated, $skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleSyncManufacturerUids(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->robneMarke()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['robna_marka', 'marka', 'sifra', 'oznaka']);
            $uid = $this->firstString($row, ['robna_marka_uid', 'uid']);

            if ($code === null || $uid === null) {
                $skipped++;
                continue;
            }

            $manufacturer = Manufacturer::query()->where('code', $code)->first();
            if (! $manufacturer) {
                $skipped++;
                continue;
            }

            $payload = (array) ($manufacturer->payload ?? []);
            $payload['luceed'] = array_merge((array) ($payload['luceed'] ?? []), [
                'uid' => $uid,
                'code' => $code,
            ]);

            $manufacturer->payload = $payload;
            $manufacturer->updated_by = auth()->id();
            $manufacturer->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Manufacturer UID sync completed: %d updated, %d skipped.', $updated, $skipped),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportWarehouses(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->skladista()->list());
        $codes = [];

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['skladiste', 'sifra', 'oznaka', 'id']);
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));

        if (trim((string) $this->settings->get('luceed_sync_stock_warehouses', '')) === '' && $codes !== []) {
            $this->settings->put('luceed_sync_stock_warehouses', implode(',', $codes));
        }

        $this->settings->put('luceed_sync_warehouses_snapshot', [
            'captured_at' => now()->toDateTimeString(),
            'count' => count($rows),
            'rows' => $rows,
        ]);

        return [
            'summary' => sprintf('Warehouse snapshot stored: %d warehouses.', count($rows)),
            'warehouses' => count($rows),
            'warehouse_codes' => $codes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportPayments(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->vrstePlacanja()->list());

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceCode = $this->firstString($row, ['vrsta_placanja', 'placanje', 'sifra', 'oznaka', 'id']);
            $name = $this->firstString($row, ['naziv', 'name']);

            if ($sourceCode === null || $name === null) {
                $skipped++;
                continue;
            }

            $code = $this->toLocalCode('luceed-pay-', $sourceCode, 60);
            $method = PaymentMethod::query()->where('code', $code)->first();
            if ($method === null) {
                $method = new PaymentMethod(['code' => $code]);
                $created++;
            } else {
                $updated++;
            }

            $settings = (array) ($method->settings ?? []);
            $settings['luceed'] = [
                'source_code' => $sourceCode,
                'uid' => $this->firstString($row, ['vrsta_placanja_uid', 'uid']),
                'row' => $row,
            ];

            $method->fill([
                'name' => $name,
                'provider' => 'luceed',
                'description' => $this->firstString($row, ['opis', 'description']),
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => $this->firstBool($row, ['enabled', 'active', 'aktivan', 'status']) ?? true,
                'sort_order' => $this->firstInt($row, ['sort', 'sort_order', 'redni_broj']) ?? 0,
                'settings' => $settings,
            ]);
            $method->save();
        }

        return [
            'summary' => sprintf('Payment methods synced: %d created, %d updated, %d skipped.', $created, $updated, $skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportProducts(): array
    {
        return $this->syncProducts(createMissing: true, updateExisting: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateProducts(): array
    {
        return $this->syncProducts(createMissing: true, updateExisting: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncProducts(bool $createMissing, bool $updateExisting): array
    {
        $settings = $this->syncSettings();
        $locale = (string) $settings['luceed_sync_default_locale'];
        $articleLimit = max(0, (int) ($settings['luceed_sync_article_limit'] ?? 0));

        $articles = $this->luceed->makeClient()->artikli()->list();

        if ($articleLimit > 0 && count($articles) > $articleLimit) {
            $articles = array_slice($articles, 0, $articleLimit);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($articles as $article) {
            $row = (array) $article->extra;
            $code = $article->code ?: $this->firstString($row, ['artikl', 'sifra', 'oznaka', 'id']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if ($product === null && ! $createMissing) {
                $skipped++;
                continue;
            }

            if ($product === null) {
                $product = new Product([
                    'code' => $code,
                    'created_by' => auth()->id(),
                ]);
                $created++;
            } elseif (! $updateExisting) {
                $skipped++;
                continue;
            } else {
                $updated++;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed'] = [
                'uid' => $article->uid ?: $this->firstString($row, ['artikl_uid', 'uid']),
                'code' => $code,
                'barcode' => $article->barcode,
                'modified' => $article->modifiedAt?->format('Y-m-d H:i:s'),
                'row' => $row,
            ];

            $manufacturerCode = $this->firstString($row, ['robna_marka', 'marka', 'brand']);
            $manufacturerId = null;
            if ($manufacturerCode !== null) {
                $manufacturerId = Manufacturer::query()->where('code', $manufacturerCode)->value('id');
            }

            $product->fill([
                'sku' => $this->firstString($row, ['sku', 'barcode', 'ean']) ?: $article->barcode,
                'is_active' => $article->enabled ?? ($this->firstBool($row, ['enabled', 'active', 'webshop']) ?? true),
                'manufacturer_id' => $manufacturerId,
                'base_price' => $this->firstFloat($row, ['mpc', 'web_cijena', 'cijena', 'price']) ?? (float) ($product->base_price ?? 0),
                'stock_qty' => $this->firstInt($row, ['kolicina', 'stanje', 'zaliha']) ?? (int) ($product->stock_qty ?? 0),
                'payload' => $payload,
                'updated_by' => auth()->id(),
            ]);
            $product->save();

            $name = $article->name ?: $this->firstString($row, ['naziv', 'name']) ?: $code;
            ProductTranslation::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $name,
                    'slug' => Str::slug($name.'-'.$code),
                    'excerpt' => $this->firstString($row, ['kratki_opis', 'kratkiopis', 'short_description']),
                    'description' => $this->firstString($row, ['opis', 'description', 'web_opis']),
                    'payload' => ['luceed' => ['code' => $code]],
                ]
            );
        }

        return [
            'summary' => sprintf('Products synced: %d created, %d updated, %d skipped.', $created, $updated, $skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($articles),
            'article_limit' => $articleLimit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateProductAdditionalData(): array
    {
        $settings = $this->syncSettings();
        $articleLimit = max(0, (int) ($settings['luceed_sync_article_limit'] ?? 0));

        $articles = $this->luceed->makeClient()->artikli()->list();
        if ($articleLimit > 0 && count($articles) > $articleLimit) {
            $articles = array_slice($articles, 0, $articleLimit);
        }

        $updated = 0;
        $skipped = 0;

        foreach ($articles as $article) {
            $row = (array) $article->extra;
            $code = $article->code ?: $this->firstString($row, ['artikl', 'sifra']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_additional'] = [
                'unit' => $this->firstString($row, ['jm', 'jedinica_mjere', 'jedinica']),
                'package' => $this->firstFloat($row, ['pakiranje', 'paket', 'paketiranje']) ?? 1,
                'weight' => $this->firstFloat($row, ['tezina', 'weight']),
                'dimensions' => [
                    'width' => $this->firstFloat($row, ['sirina', 'width']),
                    'height' => $this->firstFloat($row, ['visina', 'height']),
                    'depth' => $this->firstFloat($row, ['dubina', 'depth']),
                ],
            ];

            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Additional product data refreshed on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($articles),
            'article_limit' => $articleLimit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportRelatedProducts(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->bundleArtikl()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['artikl', 'sifra', 'bundle_artikl', 'glavni_artikl']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $relatedRaw = $row['povezani']
                ?? $row['bundle']
                ?? $row['stavke']
                ?? $row['artikli']
                ?? $row['bundle_stavke']
                ?? null;

            $relatedCodes = [];

            if (is_string($relatedRaw) && trim($relatedRaw) !== '') {
                $relatedCodes = collect(explode(',', $relatedRaw))
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values()
                    ->all();
            } elseif (is_array($relatedRaw)) {
                foreach ($relatedRaw as $item) {
                    if (is_array($item)) {
                        $relatedCode = $this->firstString($item, ['artikl', 'sifra', 'code']);
                        if ($relatedCode !== null) {
                            $relatedCodes[] = $relatedCode;
                        }
                    } elseif (is_string($item) && trim($item) !== '') {
                        $relatedCodes[] = trim($item);
                    }
                }
            }

            $relatedCodes = array_values(array_unique($relatedCodes));

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_related'] = [
                'codes' => $relatedCodes,
                'row' => $row,
            ];

            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Related-product payload refreshed on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportActions(): array
    {
        $actionRows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->akcije()->list());
        $salesRows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->prodajneAkcije()->list());

        $rows = array_merge($actionRows, $salesRows);
        $locale = (string) $this->syncSettings()['luceed_sync_default_locale'];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceCode = $this->firstString($row, ['akcija', 'sifra', 'oznaka', 'id', 'akcija_sifra']);
            $name = $this->firstString($row, ['naziv', 'name']);

            if ($sourceCode === null || $name === null) {
                $skipped++;
                continue;
            }

            $code = $this->toLocalCode('luceed-act-', $sourceCode, 120);

            $action = CatalogAction::query()->where('code', $code)->first();
            if ($action === null) {
                $action = new CatalogAction([
                    'code' => $code,
                    'scope' => CatalogAction::SCOPE_PRODUCT,
                    'target_type' => CatalogAction::TARGET_ALL,
                    'audience_type' => CatalogAction::AUDIENCE_ALL,
                    'type' => CatalogAction::TYPE_PERCENTAGE,
                    'created_by' => auth()->id(),
                ]);
                $created++;
            } else {
                $updated++;
            }

            $discount = $this->firstFloat($row, ['popust', 'rabat', 'postotak', 'discount', 'iznos']) ?? 0;
            $type = $discount <= 100 ? CatalogAction::TYPE_PERCENTAGE : CatalogAction::TYPE_FIXED;

            $action->fill([
                'type' => $type,
                'discount_value' => $discount,
                'is_active' => $this->firstBool($row, ['enabled', 'active', 'aktivan', 'status']) ?? true,
                'priority' => $this->firstInt($row, ['prioritet', 'priority', 'sort_order']) ?? 0,
                'starts_at' => $this->firstDateTime($row, ['datum_od', 'vrijedi_od', 'od']),
                'ends_at' => $this->firstDateTime($row, ['datum_do', 'vrijedi_do', 'do']),
                'payload' => [
                    'luceed' => [
                        'source_code' => $sourceCode,
                        'uid' => $this->firstString($row, ['akcija_uid', 'uid']),
                        'row' => $row,
                    ],
                ],
                'updated_by' => auth()->id(),
            ]);
            $action->save();

            CatalogActionTranslation::query()->updateOrCreate(
                [
                    'action_id' => $action->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $name,
                    'description' => $this->firstString($row, ['opis', 'description']),
                    'badge' => $this->firstString($row, ['oznaka', 'label']) ?: 'LUCEED',
                    'payload' => ['luceed' => ['source_code' => $sourceCode]],
                ]
            );
        }

        return [
            'summary' => sprintf('Catalog actions synced: %d created, %d updated, %d skipped.', $created, $updated, $skipped),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
            'action_rows' => count($actionRows),
            'sales_action_rows' => count($salesRows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleImportActionPricesLast30Days(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->artikliPopusti()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['artikl', 'sifra', 'code']);
            if ($code === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_last30_action_prices'] = [
                'discount' => $this->firstFloat($row, ['popust', 'rabat', 'postotak', 'discount']),
                'price' => $this->firstFloat($row, ['cijena', 'akcijska_cijena', 'price']),
                'valid_from' => $this->firstString($row, ['datum_od', 'od']),
                'valid_to' => $this->firstString($row, ['datum_do', 'do']),
                'row' => $row,
            ];

            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Last-30-day action prices applied on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdatePrices(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->cjenik()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['artikl', 'sifra', 'code']);
            $price = $this->firstFloat($row, ['mpc', 'cijena', 'web_cijena', 'price']);

            if ($code === null || $price === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_price_row'] = $row;

            $product->fill([
                'base_price' => $price,
                'payload' => $payload,
                'updated_by' => auth()->id(),
            ]);
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Base prices updated on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateB2bPrices(): array
    {
        $settings = $this->syncSettings();
        $partnerType = trim((string) ($settings['luceed_sync_partner_type'] ?? ''));
        $partnerValue = trim((string) ($settings['luceed_sync_partner_value'] ?? ''));

        if ($partnerType === '' || $partnerValue === '') {
            throw new \RuntimeException('Set B2B partner type/value in Luceed Sync Settings before running B2B price sync.');
        }

        $rows = array_map(
            static fn ($row) => $row->data,
            $this->luceed->makeClient()->cjenikPartner()->getDiff(
                Carbon::now()->subDays(30)->format('Y-m-d'),
                $partnerType,
                $partnerValue,
                null,
                null
            )
        );

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['artikl', 'sifra', 'code']);
            $price = $this->firstFloat($row, ['mpc', 'cijena', 'b2b_cijena', 'price']);

            if ($code === null || $price === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_b2b_price'] = [
                'partner_type' => $partnerType,
                'partner_value' => $partnerValue,
                'price' => $price,
                'currency' => (string) ($settings['luceed_sync_partner_currency'] ?? 'EUR'),
                'row' => $row,
            ];

            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('B2B price payload updated on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
            'partner_type' => $partnerType,
            'partner_value' => $partnerValue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateVpcPrices(): array
    {
        $rows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->cjenik()->list());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = $this->firstString($row, ['artikl', 'sifra', 'code']);
            $vpc = $this->firstFloat($row, ['vpc', 'veleprodajna_cijena', 'wholesale_price']);

            if ($code === null || $vpc === null) {
                $skipped++;
                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_vpc_price'] = [
                'price' => $vpc,
                'row' => $row,
            ];

            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('VPC payload updated on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateQuantities(): array
    {
        $settings = $this->syncSettings();
        $warehouseCodes = $this->csvToArray((string) ($settings['luceed_sync_stock_warehouses'] ?? ''));

        if ($warehouseCodes === []) {
            $warehouseRows = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->skladista()->list());
            foreach ($warehouseRows as $row) {
                $code = $this->firstString($row, ['skladiste', 'sifra', 'oznaka']);
                if ($code !== null) {
                    $warehouseCodes[] = $code;
                }
            }
            $warehouseCodes = array_values(array_unique($warehouseCodes));
        }

        if ($warehouseCodes === []) {
            throw new \RuntimeException('No warehouse codes available for stock sync.');
        }

        $states = $this->luceed->makeClient()->stanjeZalihe()->bySkladiste($warehouseCodes);

        $quantities = [];
        foreach ($states as $state) {
            $code = $state->articleCode ?: $this->firstString($state->extra, ['artikl', 'sifra']);
            if ($code === null) {
                continue;
            }

            $qty = $state->quantity ?? $this->firstFloat($state->extra, ['stanje', 'kolicina']) ?? 0;
            $quantities[$code] = ($quantities[$code] ?? 0) + $qty;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($quantities as $code => $quantity) {
            $product = Product::query()->where('code', $code)->first();
            if (! $product) {
                $skipped++;
                continue;
            }

            $product->stock_qty = (int) round($quantity);

            $payload = (array) ($product->payload ?? []);
            $payload['luceed_stock'] = [
                'warehouses' => $warehouseCodes,
                'total' => $quantity,
                'synced_at' => now()->toDateTimeString(),
            ];
            $product->payload = $payload;
            $product->updated_by = auth()->id();
            $product->save();
            $updated++;
        }

        return [
            'summary' => sprintf('Stock quantity updated on %d products.', $updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'source_rows' => count($states),
            'warehouse_codes' => $warehouseCodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdatePricesAndQuantities(): array
    {
        $prices = $this->handleUpdatePrices();
        $quantities = $this->handleUpdateQuantities();

        return [
            'summary' => sprintf(
                'Combined sync done. Prices updated: %d. Quantities updated: %d.',
                (int) ($prices['updated'] ?? 0),
                (int) ($quantities['updated'] ?? 0)
            ),
            'prices' => $prices,
            'quantities' => $quantities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateOrderStatuses(): array
    {
        return $this->syncOrderStatuses(b2bOnly: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleUpdateB2bOrderStatuses(): array
    {
        return $this->syncOrderStatuses(b2bOnly: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncOrderStatuses(bool $b2bOnly): array
    {
        $settings = $this->syncSettings();
        $domain = trim((string) ($settings['luceed_sync_order_status_domain'] ?? 'NaloziProdaje'));
        $lookbackDays = max(1, (int) ($settings['luceed_sync_orders_lookback_days'] ?? 30));

        $remoteStatuses = array_map(static fn ($row) => $row->data, $this->luceed->makeClient()->statusi()->list($domain));
        $statusCodeMap = [];

        foreach ($remoteStatuses as $index => $row) {
            $sourceCode = $this->firstString($row, ['status', 'sifra', 'oznaka', 'status_sifra']);
            $name = $this->firstString($row, ['naziv', 'name', 'status_naziv']);
            if ($sourceCode === null || $name === null) {
                continue;
            }

            $code = $this->toLocalCode('luceed-status-', $sourceCode, 60);

            $status = OrderStatus::query()->firstOrNew(['code' => $code]);
            $status->fill([
                'name' => $name,
                'description' => $this->firstString($row, ['opis', 'description']),
                'color' => $this->statusColorByIndex($index),
                'is_default' => (bool) ($status->exists ? $status->is_default : false),
                'is_paid' => $this->firstBool($row, ['placeno', 'is_paid']) ?? false,
                'is_cancelled' => $this->firstBool($row, ['storno', 'cancelled', 'is_cancelled']) ?? false,
                'is_active' => true,
                'sort_order' => $index,
                'settings' => [
                    'luceed' => [
                        'source_code' => $sourceCode,
                        'uid' => $this->firstString($row, ['status_uid', 'uid']),
                        'row' => $row,
                    ],
                ],
            ]);
            $status->save();

            $statusCodeMap[$sourceCode] = $status;
            $statusCodeMap[$code] = $status;
        }

        $statusFilter = $this->csvToArray((string) ($settings['luceed_sync_status_codes'] ?? ''));

        $from = Carbon::now()->subDays($lookbackDays)->format('d.m.Y');
        $rows = [];

        if ($statusFilter !== []) {
            $rows = array_map(
                static fn ($row) => $row->data,
                $this->luceed->makeClient()->naloziProdaje()->listByStatuses($statusFilter, $from)
            );
        } else {
            $rows = array_map(
                static fn ($row) => $row->data,
                $this->luceed->makeClient()->naloziProdaje()->listByStatusChange($from)
            );
        }

        $updated = 0;
        $notFound = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $remoteStatusCode = $this->firstString($row, ['status', 'status_sifra', 'oznaka_statusa']);
            if ($remoteStatusCode === null) {
                $unchanged++;
                continue;
            }

            $status = $statusCodeMap[$remoteStatusCode] ?? $statusCodeMap[$this->toLocalCode('luceed-status-', $remoteStatusCode, 60)] ?? null;
            if (! $status) {
                $unchanged++;
                continue;
            }

            $orderUid = $this->firstString($row, ['nalog_uid', 'uid', 'naloz_uid', 'dokument_uid']);
            $orderNumber = $this->firstString($row, ['broj', 'broj_dokumenta', 'oznaka', 'naloz']);

            $query = Order::query();
            if ($b2bOnly) {
                $query->where('source', 'b2b');
            }

            if ($orderUid !== null) {
                $query->where('payload->luceed_uid', $orderUid);
            } elseif ($orderNumber !== null) {
                $query->where('order_number', $orderNumber);
            } else {
                $notFound++;
                continue;
            }

            $order = $query->first();
            if (! $order) {
                $notFound++;
                continue;
            }

            $oldStatusId = (int) ($order->status_id ?? 0);
            $newStatusId = (int) $status->id;

            $payload = (array) ($order->payload ?? []);
            $payload['luceed_order_status'] = [
                'remote_code' => $remoteStatusCode,
                'order_uid' => $orderUid,
                'row' => $row,
                'synced_at' => now()->toDateTimeString(),
            ];

            $order->payload = $payload;

            if ($oldStatusId === $newStatusId) {
                $order->save();
                $unchanged++;
                continue;
            }

            $order->status_id = $newStatusId;
            $order->updated_by = auth()->id();
            $order->save();

            OrderHistory::query()->create([
                'order_id' => $order->id,
                'from_status_id' => $oldStatusId ?: null,
                'to_status_id' => $newStatusId,
                'changed_by' => auth()->id(),
                'comment' => 'Luceed sync: status updated.',
                'payload' => [
                    'luceed' => [
                        'remote_status' => $remoteStatusCode,
                        'order_uid' => $orderUid,
                    ],
                ],
            ]);

            $updated++;
        }

        return [
            'summary' => sprintf('Order status sync complete: %d updated, %d unchanged, %d unmatched.', $updated, $unchanged, $notFound),
            'updated' => $updated,
            'unchanged' => $unchanged,
            'unmatched' => $notFound,
            'source_rows' => count($rows),
            'remote_status_rows' => count($remoteStatuses),
            'b2b_only' => $b2bOnly,
            'lookback_days' => $lookbackDays,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleCheckOrderStatusDuration(): array
    {
        $settings = $this->syncSettings();
        $days = max(1, (int) ($settings['luceed_sync_status_duration_days'] ?? 4));
        $threshold = now()->subDays($days);

        $rows = Order::query()
            ->with('status:id,name,is_paid,is_cancelled')
            ->whereNotNull('status_id')
            ->where(function ($query): void {
                $query->whereHas('status', function ($statusQuery): void {
                    $statusQuery->where('is_paid', false)->where('is_cancelled', false);
                });
            })
            ->where(function ($query) use ($threshold): void {
                $query->where('placed_at', '<', $threshold)
                    ->orWhere('updated_at', '<', $threshold);
            })
            ->orderBy('placed_at')
            ->limit(100)
            ->get(['id', 'order_number', 'status_id', 'placed_at', 'updated_at']);

        $sample = $rows->take(20)->map(function (Order $order): array {
            return [
                'order_number' => $order->order_number,
                'status' => $order->status?->name,
                'placed_at' => optional($order->placed_at)->toDateTimeString(),
                'updated_at' => optional($order->updated_at)->toDateTimeString(),
            ];
        })->values()->all();

        return [
            'summary' => sprintf('Long-running order check complete: %d order(s) exceed %d day(s).', $rows->count(), $days),
            'threshold_days' => $days,
            'flagged_orders' => $rows->count(),
            'sample' => $sample,
        ];
    }

    private function statusColorByIndex(int $index): string
    {
        $colors = ['slate', 'cyan', 'amber', 'violet', 'emerald', 'rose', 'indigo'];

        return $colors[$index % count($colors)];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstInt(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstFloat(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && preg_match('/^-?\d+[\.,]\d+$/', trim($value)) === 1) {
                return (float) str_replace(',', '.', trim($value));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstBool(array $row, array $keys): ?bool
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                return $value;
            }

            $normalized = strtoupper(trim((string) $value));
            if (in_array($normalized, ['1', 'Y', 'YES', 'TRUE', 'T', 'D'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'N', 'NO', 'FALSE', 'F'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstDateTime(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return Carbon::parse($value)->toDateTimeString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function csvToArray(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function toLocalCode(string $prefix, string $source, int $max): string
    {
        $base = Str::slug($source, '-');
        if ($base === '') {
            $base = substr(sha1($source), 0, 12);
        }

        $code = $prefix.$base;

        if (strlen($code) <= $max) {
            return $code;
        }

        $hash = substr(sha1($code), 0, 8);
        $cut = max(1, $max - strlen($prefix) - 9);

        return $prefix.substr($base, 0, $cut).'-'.$hash;
    }
}
