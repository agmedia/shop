<?php

namespace App\Livewire\Admin\Catalog\Product;

use App\Models\Catalog\Attribute\Attribute;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Settings\Local\TaxRate;
use App\Services\Catalog\CatalogFeatureService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $productId = null;
    public string $activeTab = 'content';
    public string $categorySearch = '';
    public array $attributeSelections = [];
    public string $attributeGroupView = 'all';
    public bool $attributeShowAssignedOnly = false;

    public array $form = [
        'code' => '',
        'sku' => '',
        'is_active' => true,
        'manufacturer_id' => null,
        'tax_rate_id' => null,
        'base_price' => 0,
        'stock_qty' => 0,
        'payload_text' => '',
        'locale' => 'en',
        'name' => '',
        'slug' => '',
        'excerpt' => '',
        'description' => '',
        'meta_title' => '',
        'meta_description' => '',
        'translation_payload_text' => '',
        'category_ids' => [],
        'attribute_ids' => [],
    ];

    public function mount(?int $productId = null): void
    {
        $this->form['locale'] = (string) (request()->query('locale') ?: config('app.locale', 'en'));

        if ($productId) {
            $this->productId = $productId;
            $this->loadProduct();
        } else {
            $this->form['tax_rate_id'] = $this->defaultTaxRateId();
        }
    }

    public function updatedFormLocale(): void
    {
        $this->loadTranslationForLocale();
    }

    public function generateSlug(): void
    {
        $name = trim((string) $this->form['name']);
        if ($name !== '') {
            $this->form['slug'] = Str::slug($name);
        }
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['content', 'seo', 'media', 'catalog'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function save()
    {
        if ($this->useAttributes()) {
            $resolvedAttributeIds = $this->resolveAttributeIdsForSave();
            if ($resolvedAttributeIds === false) {
                return null;
            }

            $this->form['attribute_ids'] = $resolvedAttributeIds;
        }

        $validated = $this->validate($this->rules());
        $wasEditing = (bool) $this->productId;

        $payload = $this->decodeJsonField('form.payload_text');
        if ($payload === false) {
            return null;
        }

        $translationPayload = $this->decodeJsonField('form.translation_payload_text');
        if ($translationPayload === false) {
            return null;
        }

        $userId = auth()->id();

        DB::transaction(function () use ($validated, $payload, $translationPayload, $userId, $wasEditing): void {
            $productData = [
                'code' => trim((string) $validated['form']['code']),
                'sku' => trim((string) $validated['form']['sku']) !== '' ? trim((string) $validated['form']['sku']) : null,
                'is_active' => (bool) $validated['form']['is_active'],
                'tax_rate_id' => (int) (($validated['form']['tax_rate_id'] ?? null) ?: $this->defaultTaxRateId()),
                'base_price' => (float) $validated['form']['base_price'],
                'stock_qty' => (int) $validated['form']['stock_qty'],
                'payload' => $payload,
                'updated_by' => $userId,
            ];
            if ($this->useManufacturers()) {
                $productData['manufacturer_id'] = $validated['form']['manufacturer_id'] ?: null;
            }

            if ($this->productId) {
                $product = Product::query()->findOrFail($this->productId);
                $product->fill($productData)->save();
            } else {
                $product = Product::query()->create($productData + ['created_by' => $userId]);
                $this->productId = $product->id;
            }

            $product->translations()->updateOrCreate(
                ['locale' => $validated['form']['locale']],
                [
                    'name' => $validated['form']['name'],
                    'slug' => $validated['form']['slug'],
                    'excerpt' => $validated['form']['excerpt'] ?: null,
                    'description' => $validated['form']['description'] ?: null,
                    'meta_title' => $validated['form']['meta_title'] ?: null,
                    'meta_description' => $validated['form']['meta_description'] ?: null,
                    'payload' => $translationPayload,
                ]
            );

            $syncPayload = [];
            foreach (array_values($validated['form']['category_ids'] ?? []) as $index => $categoryId) {
                $syncPayload[(int) $categoryId] = [
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ];
            }
            $product->categories()->sync($syncPayload);

            if ($this->useAttributes()) {
                $attributeSyncPayload = [];
                foreach (array_values($validated['form']['attribute_ids'] ?? []) as $index => $attributeId) {
                    $attributeSyncPayload[(int) $attributeId] = [
                        'sort_order' => $index,
                    ];
                }
                $product->attributes()->sync($attributeSyncPayload);
            }

            activity('catalog_products')
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->event($wasEditing ? 'updated' : 'created')
                ->withProperties([
                    'locale' => $validated['form']['locale'],
                    'slug' => $validated['form']['slug'],
                    'category_count' => count($syncPayload),
                    'attribute_count' => count($validated['form']['attribute_ids'] ?? []),
                ])
                ->log('Product saved');
        });

        if ($wasEditing) {
            return redirect()
                ->route('admin.products.edit', ['product' => $this->productId, 'locale' => $this->form['locale']])
                ->with('notify', [
                    'type' => 'success',
                    'message' => __('Product updated.'),
                ]);
        }

        return redirect()
            ->route('admin.products.edit', ['product' => $this->productId, 'locale' => $this->form['locale']])
            ->with('notify', [
                'type' => 'success',
                'message' => __('Product created. Now upload product images.'),
            ]);
    }

    public function backToList()
    {
        return redirect()->route('admin.products', ['locale' => $this->form['locale']]);
    }

    public function getCategoryOptionsProperty(): Collection
    {
        return Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->withDepth()
            ->defaultOrder()
            ->with([
                'translations' => fn ($q) => $q
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->where('locale', $this->form['locale']),
            ])
            ->get();
    }

    public function getFilteredCategoryOptionsProperty(): Collection
    {
        $search = Str::lower(trim($this->categorySearch));
        $selected = collect($this->form['category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $labels = $this->categoryLabelMap;

        return $this->categoryOptions
            ->reject(fn ($category) => in_array((int) $category->id, $selected, true))
            ->map(function ($category) use ($labels): array {
                $id = (int) $category->id;

                return [
                    'id' => $id,
                    'label' => (string) ($labels[$id] ?? ('#'.$id)),
                ];
            })
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return Str::contains(Str::lower($row['label']), $search);
            })
            ->values()
            ->take(120);
    }

    public function getSelectedCategoryRowsProperty(): Collection
    {
        $labels = $this->categoryLabelMap;

        return collect($this->form['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->map(function (int $id) use ($labels): array {
                return ['id' => $id, 'label' => (string) ($labels[$id] ?? ('#'.$id))];
            });
    }

    /**
     * @return array<int, string>
     */
    public function getCategoryLabelMapProperty(): array
    {
        $categories = $this->categoryOptions;
        $nameById = $categories->mapWithKeys(function ($category): array {
            $name = (string) ($category->translations->first()?->name ?? ($category->code ?: ('#'.$category->id)));

            return [(int) $category->id => $name];
        });
        $byId = $categories->keyBy(fn ($category): int => (int) $category->id);
        $labels = [];

        $build = function (int $id) use (&$build, &$labels, $byId, $nameById): string {
            if (isset($labels[$id])) {
                return $labels[$id];
            }

            $current = $byId->get($id);
            if ($current === null) {
                return '#'.$id;
            }

            $name = (string) ($nameById[$id] ?? ('#'.$id));
            $parentId = (int) ($current->parent_id ?? 0);

            if ($parentId > 0 && $byId->has($parentId)) {
                $labels[$id] = $build($parentId).' > '.$name;
            } else {
                $labels[$id] = $name;
            }

            return $labels[$id];
        };

        foreach ($byId->keys() as $id) {
            $build((int) $id);
        }

        return $labels;
    }

    public function addCategory(int $categoryId): void
    {
        $ids = collect($this->form['category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->contains($categoryId)) {
            return;
        }

        $ids->push($categoryId);
        $this->form['category_ids'] = $ids->all();
    }

    public function removeCategory(int $categoryId): void
    {
        $this->form['category_ids'] = collect($this->form['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $categoryId)
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.admin.catalog.product.form', [
            'isEdit' => (bool) $this->productId,
            'useAttributes' => $this->useAttributes(),
            'useOptions' => $this->useOptions(),
            'useManufacturers' => $this->useManufacturers(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'form.code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('products', 'code')->ignore($this->productId),
            ],
            'form.sku' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'sku')->ignore($this->productId),
            ],
            'form.is_active' => ['boolean'],
            'form.tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')],
            'form.base_price' => ['required', 'numeric', 'min:0'],
            'form.stock_qty' => ['required', 'integer', 'min:0'],
            'form.payload_text' => ['nullable', 'string'],
            'form.manufacturer_id' => ['nullable'],

            'form.locale' => ['required', 'string', 'max:12'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.slug' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_translations', 'slug')
                    ->where(fn ($q) => $q->where('locale', $this->form['locale']))
                    ->ignore($this->productId, 'product_id'),
            ],
            'form.excerpt' => ['nullable', 'string'],
            'form.description' => ['nullable', 'string'],
            'form.meta_title' => ['nullable', 'string', 'max:255'],
            'form.meta_description' => ['nullable', 'string'],
            'form.translation_payload_text' => ['nullable', 'string'],

            'form.category_ids' => ['nullable', 'array'],
            'form.category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('scope', Category::SCOPE_CATALOG)),
            ],
            'form.attribute_ids' => ['nullable', 'array'],
        ];

        if ($this->useManufacturers()) {
            $rules['form.manufacturer_id'] = [
                'nullable',
                'integer',
                Rule::exists('catalog_manufacturers', 'id'),
            ];
        }

        if ($this->useAttributes()) {
            $rules['form.attribute_ids.*'] = [
                'integer',
                Rule::exists('catalog_attributes', 'id'),
            ];
        }

        return $rules;
    }

    private function loadProduct(): void
    {
        if (!$this->productId) {
            return;
        }

        $productQuery = Product::query()
            ->with('translations')
            ->with(['categories' => fn ($q) => $q->orderBy('category_product.sort_order')]);

        if ($this->useAttributes()) {
            $productQuery->with(['attributes' => fn ($q) => $q->orderBy('catalog_attribute_product.sort_order')]);
        }

        $product = $productQuery->findOrFail($this->productId);

        $preferredLocale = $this->form['locale'] ?: config('app.locale', 'en');
        $translation = $product->translations->firstWhere('locale', $preferredLocale)
            ?? $product->translations->firstWhere('locale', config('app.locale', 'en'))
            ?? $product->translations->first();

        $this->form['code'] = $product->code;
        $this->form['sku'] = $product->sku ?? '';
        $this->form['is_active'] = (bool) $product->is_active;
        $this->form['tax_rate_id'] = $product->tax_rate_id ? (int) $product->tax_rate_id : $this->defaultTaxRateId();
        $this->form['base_price'] = (float) $product->base_price;
        $this->form['stock_qty'] = (int) $product->stock_qty;
        if ($this->useManufacturers()) {
            $this->form['manufacturer_id'] = $product->manufacturer_id ? (int) $product->manufacturer_id : null;
        }
        $this->form['payload_text'] = $product->payload
            ? json_encode($product->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
        $this->form['category_ids'] = $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($this->useAttributes()) {
            $this->form['attribute_ids'] = $product->attributes->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->hydrateAttributeSelectionsFromAttributeIds($this->form['attribute_ids']);
        }

        if ($translation) {
            $this->form['locale'] = $translation->locale;
            $this->form['name'] = $translation->name;
            $this->form['slug'] = $translation->slug;
            $this->form['excerpt'] = $translation->excerpt ?? '';
            $this->form['description'] = $translation->description ?? '';
            $this->form['meta_title'] = $translation->meta_title ?? '';
            $this->form['meta_description'] = $translation->meta_description ?? '';
            $this->form['translation_payload_text'] = $translation->payload
                ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '';
        }
    }

    public function getManufacturerOptionsProperty(): Collection
    {
        if (!$this->useManufacturers()) {
            return collect();
        }

        return Manufacturer::query()
            ->where('is_active', true)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $this->form['locale']),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getTaxRateOptionsProperty(): Collection
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getAttributeOptionsProperty(): Collection
    {
        if (!$this->useAttributes()) {
            return collect();
        }

        return Attribute::query()
            ->where(function ($q): void {
                $q->where('is_active', true);

                if (!empty($this->form['attribute_ids'])) {
                    $q->orWhereIn('id', array_map('intval', $this->form['attribute_ids']));
                }
            })
            ->with([
                'translations' => fn ($q) => $q->where('locale', $this->form['locale']),
            ])
            ->orderBy('group_code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array{group_code:string,group_name:string,type:string,items:array<int, array{id:int,name:string}>}>
     */
    public function getAttributeGroupsProperty(): array
    {
        if (!$this->useAttributes()) {
            return [];
        }

        $groups = [];

        foreach ($this->attributeOptions as $attribute) {
            $tr = $attribute->translations->first();
            $groupCode = (string) $attribute->group_code;

            if (!isset($groups[$groupCode])) {
                $groups[$groupCode] = [
                    'group_code' => $groupCode,
                    'group_name' => (string) ($tr?->group_name ?? str($groupCode)->replace('_', ' ')->title()),
                    'type' => (string) $attribute->type,
                    'items' => [],
                ];
            }

            $groups[$groupCode]['items'][] = [
                'id' => (int) $attribute->id,
                'name' => (string) ($tr?->name ?? $attribute->code),
            ];
        }

        return array_values($groups);
    }

    /**
     * @return array<int, array{group_code:string,group_name:string,type:string,item_count:int}>
     */
    public function getAttributeGroupOptionsProperty(): array
    {
        return array_map(
            fn (array $group): array => [
                'group_code' => (string) $group['group_code'],
                'group_name' => (string) $group['group_name'],
                'type' => (string) $group['type'],
                'item_count' => count($group['items']),
            ],
            $this->attributeGroups
        );
    }

    /**
     * @return array<int, array{group_code:string,group_name:string,type:string,items:array<int, array{id:int,name:string}>}>
     */
    public function getVisibleAttributeGroupsProperty(): array
    {
        $groups = $this->attributeGroups;

        $knownGroupCodes = array_map(fn (array $group): string => (string) $group['group_code'], $groups);
        if ($this->attributeGroupView !== 'all' && !in_array($this->attributeGroupView, $knownGroupCodes, true)) {
            $this->attributeGroupView = 'all';
        }

        if ($this->attributeGroupView !== 'all') {
            $groups = array_values(array_filter(
                $groups,
                fn (array $group): bool => (string) $group['group_code'] === $this->attributeGroupView
            ));
        }

        if ($this->attributeShowAssignedOnly) {
            $groups = array_values(array_filter(
                $groups,
                fn (array $group): bool => $this->hasSelectionForAttributeGroup((string) $group['group_code'])
            ));
        }

        return $groups;
    }

    private function loadTranslationForLocale(): void
    {
        if (!$this->productId) {
            $this->clearTranslationFields();
            return;
        }

        $translation = ProductTranslation::query()
            ->where('product_id', $this->productId)
            ->where('locale', $this->form['locale'])
            ->first();

        if (!$translation) {
            $this->clearTranslationFields();
            return;
        }

        $this->form['name'] = $translation->name;
        $this->form['slug'] = $translation->slug;
        $this->form['excerpt'] = $translation->excerpt ?? '';
        $this->form['description'] = $translation->description ?? '';
        $this->form['meta_title'] = $translation->meta_title ?? '';
        $this->form['meta_description'] = $translation->meta_description ?? '';
        $this->form['translation_payload_text'] = $translation->payload
            ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
    }

    private function clearTranslationFields(): void
    {
        $this->form['name'] = '';
        $this->form['slug'] = '';
        $this->form['excerpt'] = '';
        $this->form['description'] = '';
        $this->form['meta_title'] = '';
        $this->form['meta_description'] = '';
        $this->form['translation_payload_text'] = '';
    }

    /**
     * @return array<mixed>|null|false
     */
    private function decodeJsonField(string $field): array|null|false
    {
        $value = trim((string) data_get($this, $field));
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field, __('Invalid JSON payload.'));
            $this->dispatch('notify', type: 'danger', message: __('Invalid JSON payload.'));
            return false;
        }

        if (!is_array($decoded)) {
            $this->addError($field, __('JSON payload must decode to object/array.'));
            $this->dispatch('notify', type: 'danger', message: __('JSON payload must decode to object/array.'));
            return false;
        }

        return $decoded;
    }

    /**
     * @return array<int>|false
     */
    private function resolveAttributeIdsForSave(): array|false
    {
        if (!$this->useAttributes()) {
            return [];
        }

        if (!empty($this->attributeSelections)) {
            return $this->resolveGroupedAttributeSelections();
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->form['attribute_ids'] ?? [])),
            fn (int $id): bool => $id > 0
        )));

        if (empty($ids)) {
            return [];
        }

        $rows = Attribute::query()
            ->whereIn('id', $ids)
            ->get(['id', 'group_code', 'type']);

        if ($rows->count() !== count($ids)) {
            $this->addError('form.attribute_ids', __('Invalid attribute selection.'));
            $this->dispatch('notify', type: 'danger', message: __('Invalid attribute selection.'));
            return false;
        }

        $byGroup = $rows->groupBy('group_code');

        foreach ($byGroup as $groupCode => $groupRows) {
            $type = (string) ($groupRows->first()->type ?? Attribute::TYPE_SELECT);
            if ($type === Attribute::TYPE_SELECT && $groupRows->count() > 1) {
                $this->addError('form.attribute_ids', __('Only one value is allowed for group ":group".', ['group' => $groupCode]));
                $this->dispatch('notify', type: 'danger', message: __('Only one value is allowed for select-type attribute groups.'));
                return false;
            }
        }

        return $ids;
    }

    private function defaultTaxRateId(): ?int
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @return array<int>|false
     */
    private function resolveGroupedAttributeSelections(): array|false
    {
        $normalizedByGroup = [];
        $allIds = [];

        foreach ($this->attributeSelections as $groupCode => $value) {
            $ids = [];

            if (is_array($value)) {
                $ids = array_values(array_unique(array_filter(
                    array_map('intval', $value),
                    fn (int $id): bool => $id > 0
                )));
            } else {
                $single = (int) $value;
                if ($single > 0) {
                    $ids = [$single];
                }
            }

            if (!empty($ids)) {
                $normalizedByGroup[(string) $groupCode] = $ids;
                foreach ($ids as $id) {
                    $allIds[] = $id;
                }
            }
        }

        $allIds = array_values(array_unique($allIds));
        if (empty($allIds)) {
            return [];
        }

        $rows = Attribute::query()
            ->whereIn('id', $allIds)
            ->get(['id', 'group_code', 'type']);

        if ($rows->count() !== count($allIds)) {
            $this->addError('form.attribute_ids', __('Invalid attribute selection.'));
            $this->dispatch('notify', type: 'danger', message: __('Invalid attribute selection.'));
            return false;
        }

        $byId = $rows->keyBy('id');

        foreach ($normalizedByGroup as $groupCode => $ids) {
            $groupType = null;

            foreach ($ids as $id) {
                /** @var Attribute|null $row */
                $row = $byId->get($id);

                if (!$row) {
                    $this->addError('form.attribute_ids', __('Invalid attribute selection.'));
                    $this->dispatch('notify', type: 'danger', message: __('Invalid attribute selection.'));
                    return false;
                }

                if ((string) $row->group_code !== $groupCode) {
                    $this->addError('attributeSelections.'.$groupCode, __('Selected value does not belong to this group.'));
                    $this->dispatch('notify', type: 'danger', message: __('Attribute group/value mismatch detected.'));
                    return false;
                }

                $groupType ??= (string) $row->type;
            }

            if (($groupType ?? Attribute::TYPE_SELECT) === Attribute::TYPE_SELECT && count($ids) > 1) {
                $this->addError('attributeSelections.'.$groupCode, __('Only one value can be selected for this group.'));
                $this->dispatch('notify', type: 'danger', message: __('Only one value is allowed for select-type attribute groups.'));
                return false;
            }
        }

        return $allIds;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function hydrateAttributeSelectionsFromAttributeIds(array $ids): void
    {
        $this->attributeSelections = [];

        if (empty($ids)) {
            return;
        }

        $attributes = Attribute::query()
            ->whereIn('id', $ids)
            ->orderBy('group_code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'group_code', 'type']);

        foreach ($attributes as $attribute) {
            $groupCode = (string) $attribute->group_code;
            $id = (int) $attribute->id;

            if ((string) $attribute->type === Attribute::TYPE_MULTI) {
                $existing = $this->attributeSelections[$groupCode] ?? [];
                if (!is_array($existing)) {
                    $existing = [];
                }
                $existing[] = $id;
                $this->attributeSelections[$groupCode] = array_values(array_unique(array_map('intval', $existing)));
                continue;
            }

            if (!isset($this->attributeSelections[$groupCode]) || (int) $this->attributeSelections[$groupCode] <= 0) {
                $this->attributeSelections[$groupCode] = $id;
            }
        }
    }

    private function hasSelectionForAttributeGroup(string $groupCode): bool
    {
        if (!array_key_exists($groupCode, $this->attributeSelections)) {
            return false;
        }

        $value = $this->attributeSelections[$groupCode];
        if (is_array($value)) {
            return !empty(array_filter(array_map('intval', $value), fn (int $id): bool => $id > 0));
        }

        return (int) $value > 0;
    }

    private function useOptions(): bool
    {
        return app(CatalogFeatureService::class)->useOptions();
    }

    private function useAttributes(): bool
    {
        return app(CatalogFeatureService::class)->useAttributes();
    }

    private function useManufacturers(): bool
    {
        return app(CatalogFeatureService::class)->useManufacturers();
    }
}
