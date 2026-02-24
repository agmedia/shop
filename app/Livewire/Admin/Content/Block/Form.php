<?php

namespace App\Livewire\Admin\Content\Block;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\ContentBlock;
use App\Services\Content\ContentBlockResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    private const ITEM_TYPE_MAP = [
        'products' => 'product',
        'products_carousel' => 'product',
        'categories' => 'category',
        'mobile_hero_banner' => 'category',
        'manufacturers' => 'manufacturer',
        'blogs' => 'blog',
    ];

    public ?int $blockId = null;

    public array $form = [];

    /**
     * @var array<string, string>
     */
    public array $types = [];

    /**
     * @var array<string, string>
     */
    public array $placements = [];

    /**
     * @var array<string, string>
     */
    public array $targetTypes = [
        '' => 'Global (no target)',
        'category' => 'Category',
        'product' => 'Product',
        'blog_post' => 'Blog Post',
        'page' => 'Static Page',
        'custom' => 'Custom',
    ];

    /**
     * @var array<string, string>
     */
    public array $frontendVariants = [
        'all' => 'All Devices',
        'desktop' => 'Desktop Only',
        'mobile' => 'Mobile Only',
    ];

    public ?int $pickerItemId = null;
    public string $lastType = '';

    public function mount(?int $blockId = null): void
    {
        /** @var array<string, string> $types */
        $types = config('content_blocks.types', []);
        /** @var array<string, string> $placements */
        $placements = config('content_blocks.placements', []);

        $this->types = collect($this->orderedTypes($types))
            ->mapWithKeys(static fn ($label, $key) => [$key => __((string) $label)])
            ->all();
        $this->placements = $placements;
        $this->targetTypes = collect($this->targetTypes)
            ->map(static fn ($label) => __((string) $label))
            ->all();
        $this->frontendVariants = collect($this->frontendVariants)
            ->map(static fn ($label) => __((string) $label))
            ->all();

        $this->resetForm();
        $this->lastType = (string) ($this->form['type'] ?? '');

        if ($blockId) {
            $this->blockId = $blockId;
            $this->loadBlock();
        }
    }

    public function getIsEditProperty(): bool
    {
        return (bool) $this->blockId;
    }

    public function getCurrentItemTypeProperty(): ?string
    {
        return $this->itemTypeForBlockType((string) ($this->form['type'] ?? ''));
    }

    public function getIsItemBlockProperty(): bool
    {
        return $this->currentItemType !== null;
    }

    public function getItemOptionsProperty(): Collection
    {
        return $this->loadItemOptions($this->currentItemType);
    }

    public function getSelectedItemsProperty(): Collection
    {
        $optionsById = $this->itemOptions->keyBy('id');

        return collect((array) ($this->form['selected_item_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->map(function (int $id, int $index) use ($optionsById): array {
                $row = $optionsById->get($id);

                return [
                    'id' => $id,
                    'index' => $index,
                    'label' => (string) ($row['label'] ?? ('#'.$id)),
                ];
            });
    }

    public function updatedFormLocale(): void
    {
        $this->loadTranslationForLocale();
    }

    public function updatedFormType(string $type): void
    {
        if (!array_key_exists($type, $this->types)) {
            return;
        }

        $suggestedSurface = $this->suggestedFrontendVariantForType($type);
        if ($suggestedSurface !== null) {
            $this->form['slot_frontend_variant'] = $suggestedSurface;
        }

        $currentItemType = $this->itemTypeForBlockType($type);
        $existingType = $this->itemTypeForBlockType($this->lastType);

        if ($currentItemType !== $existingType) {
            $this->form['selected_item_ids'] = [];
            $this->pickerItemId = null;
        }

        $currentTemplate = (string) ($this->form['template_body'] ?? '');
        $shouldLoadDefault = trim($currentTemplate) === '';

        if (! $shouldLoadDefault && $this->lastType !== '') {
            $previousDefault = $this->defaultTemplateForType($this->lastType);
            $shouldLoadDefault = $this->normalizedTemplate($currentTemplate) === $this->normalizedTemplate($previousDefault);
        }

        if ($shouldLoadDefault) {
            $this->form['template_body'] = $this->defaultTemplateForType($type);
        }

        $this->lastType = $type;
    }

    public function loadTemplatePreset(): void
    {
        $type = (string) ($this->form['type'] ?? 'banner');
        $this->form['template_body'] = $this->defaultTemplateForType($type);
    }

    public function addSelectedItem(): void
    {
        $id = (int) ($this->pickerItemId ?? 0);
        if ($id <= 0) {
            return;
        }

        $existing = collect((array) ($this->form['selected_item_ids'] ?? []))->map(fn ($v) => (int) $v);
        if ($existing->contains($id)) {
            return;
        }

        $optionExists = $this->itemOptions->contains(fn ($row) => (int) ($row['id'] ?? 0) === $id);
        if (! $optionExists) {
            return;
        }

        $this->form['selected_item_ids'][] = $id;
        $this->form['selected_item_ids'] = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            (array) $this->form['selected_item_ids']
        )));
    }

    public function removeSelectedItem(int $id): void
    {
        $this->form['selected_item_ids'] = array_values(array_filter(
            array_map(static fn ($value): int => (int) $value, (array) ($this->form['selected_item_ids'] ?? [])),
            static fn (int $value): bool => $value !== $id
        ));
    }

    public function moveSelectedItemUp(int $index): void
    {
        $rows = array_values(array_map(static fn ($value): int => (int) $value, (array) ($this->form['selected_item_ids'] ?? [])));
        if ($index <= 0 || $index >= count($rows)) {
            return;
        }

        [$rows[$index - 1], $rows[$index]] = [$rows[$index], $rows[$index - 1]];
        $this->form['selected_item_ids'] = $rows;
    }

    public function moveSelectedItemDown(int $index): void
    {
        $rows = array_values(array_map(static fn ($value): int => (int) $value, (array) ($this->form['selected_item_ids'] ?? [])));
        if ($index < 0 || $index >= count($rows) - 1) {
            return;
        }

        [$rows[$index + 1], $rows[$index]] = [$rows[$index], $rows[$index + 1]];
        $this->form['selected_item_ids'] = $rows;
    }

    public function save()
    {
        $validated = $this->validate($this->rules());
        $userId = auth()->id();
        $isEdit = $this->blockId !== null;
        $existingBlockPayload = null;
        $translationPayload = [];
        if ($this->blockId) {
            $existingBlock = ContentBlock::query()
                ->with(['translations' => fn ($q) => $q->where('locale', (string) $validated['form']['locale'])])
                ->find($this->blockId);
            $existingBlockPayload = is_array($existingBlock?->payload ?? null) ? $existingBlock->payload : null;
            $translationPayload = is_array($existingBlock?->translations->first()?->payload ?? null)
                ? $existingBlock->translations->first()->payload
                : [];
        }

        $bgCss = trim((string) ($validated['form']['bg_css'] ?? ''));
        if ($bgCss !== '') {
            $translationPayload['bg_css'] = $bgCss;
        } else {
            unset($translationPayload['bg_css']);
        }

        $customClasses = trim((string) ($validated['form']['custom_classes'] ?? ''));
        if ($customClasses !== '') {
            $translationPayload['custom_classes'] = $customClasses;
        } else {
            unset($translationPayload['custom_classes']);
        }

        $itemsLimit = (int) ($validated['form']['items_limit'] ?? 0);
        if ($itemsLimit > 0) {
            $translationPayload['items_limit'] = $itemsLimit;
        } else {
            unset($translationPayload['items_limit']);
        }

        $reviewsFeaturedOnly = (bool) ($validated['form']['reviews_featured_only'] ?? false);
        if ($reviewsFeaturedOnly) {
            $translationPayload['reviews_featured_only'] = true;
        } else {
            unset($translationPayload['reviews_featured_only']);
        }
        $blogSource = (string) ($validated['form']['blog_source'] ?? 'latest');
        $translationPayload['blog_source'] = in_array($blogSource, ['latest', 'featured'], true) ? $blogSource : 'latest';
        unset($translationPayload['render_mode'], $translationPayload['body_html_container_class']);

        $itemType = $this->itemTypeForBlockType((string) $validated['form']['type']);
        $selectedIds = collect((array) ($validated['form']['selected_item_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (trim((string) ($validated['form']['slot_target_ref'] ?? '')) !== '' && trim((string) ($validated['form']['slot_target_type'] ?? '')) === '') {
            $this->addError('form.slot_target_type', __('Target type is required when target ref is set.'));
            $this->dispatch('notify', type: 'warning', message: __('Choose target type when target ref is set.'));

            return null;
        }

        if ($itemType !== null && $selectedIds === []) {
            $this->addError('form.selected_item_ids', __('Select at least one item for this block type.'));
            $this->dispatch('notify', type: 'warning', message: __('Select at least one item.'));

            return null;
        }

        if ($itemType !== null && $selectedIds !== []) {
            $validIds = $this->validIdsForItemType($itemType, $selectedIds);
            if (count($validIds) !== count($selectedIds)) {
                $this->addError('form.selected_item_ids', __('One or more selected items are invalid.'));
                $this->dispatch('notify', type: 'warning', message: __('Invalid item selection detected.'));

                return null;
            }
        }

        $oldCode = null;
        if ($this->blockId) {
            $oldCode = (string) ContentBlock::query()->whereKey($this->blockId)->value('code');
        }

        DB::transaction(function () use (
            $validated,
            $existingBlockPayload,
            $translationPayload,
            $itemType,
            $selectedIds,
            $userId,
            $oldCode,
            $isEdit
        ): void {
            $blockData = [
                'code' => trim((string) $validated['form']['code']),
                'name' => trim((string) $validated['form']['name']),
                'type' => (string) $validated['form']['type'],
                'is_active' => (bool) $validated['form']['is_active'],
                'payload' => $existingBlockPayload,
                'updated_by' => $userId,
            ];

            if ($this->blockId) {
                $block = ContentBlock::query()->findOrFail($this->blockId);
                $block->update($blockData);
            } else {
                $block = ContentBlock::query()->create($blockData + ['created_by' => $userId]);
                $this->blockId = $block->id;
            }

            $block->translations()->updateOrCreate(
                ['locale' => (string) $validated['form']['locale']],
                [
                    'title' => $validated['form']['title'] ?: null,
                    'subtitle' => $validated['form']['subtitle'] ?: null,
                    'body_html' => null,
                    'cta_label' => $validated['form']['cta_label'] ?: null,
                    'cta_url' => $validated['form']['cta_url'] ?: null,
                    'payload' => $translationPayload === [] ? null : $translationPayload,
                ]
            );

            $this->savePrimarySlot($block, $validated, $userId);
            $this->syncBlockItems($block, $itemType, $selectedIds);

            $template = trim((string) ($validated['form']['template_body'] ?? ''));
            if ($template === '') {
                $template = $this->defaultTemplateForType((string) $block->type);
            }
            $this->writeTemplateFile((string) $block->code, $template);

            if ($oldCode !== null && $oldCode !== '' && $oldCode !== $block->code) {
                $this->deleteTemplateFile($oldCode);
            }

            activity('content_blocks')
                ->performedOn($block)
                ->causedBy(auth()->user())
                ->event($isEdit ? 'updated' : 'created')
                ->withProperties([
                    'code' => $block->code,
                    'type' => $block->type,
                    'placement' => $validated['form']['slot_placement'],
                    'frontend_variant' => $validated['form']['slot_frontend_variant'],
                    'item_type' => $itemType,
                    'item_count' => count($selectedIds),
                    'template_file' => $this->templateViewName((string) $block->code),
                ])
                ->log('Content block saved (v2 builder)');
        });

        ContentBlockResolver::bumpCacheVersion();

        return redirect()->route('admin.content.blocks')->with('notify', [
            'type' => 'success',
            'message' => $isEdit ? __('Content block updated.') : __('Content block created.'),
        ]);
    }

    public function backToList()
    {
        return redirect()->route('admin.content.blocks');
    }

    public function render()
    {
        return view('livewire.admin.content.block.form');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.code' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('content_blocks', 'code')->ignore($this->blockId)],
            'form.name' => ['required', 'string', 'max:180'],
            'form.type' => ['required', Rule::in(array_keys($this->types))],
            'form.is_active' => ['boolean'],

            'form.locale' => ['required', 'string', 'max:12'],
            'form.title' => ['nullable', 'string', 'max:255'],
            'form.subtitle' => ['nullable', 'string'],
            'form.cta_label' => ['nullable', 'string', 'max:120'],
            'form.cta_url' => ['nullable', 'string', 'max:2048'],
            'form.bg_css' => ['nullable', 'string', 'max:6000'],
            'form.custom_classes' => ['nullable', 'string', 'max:1000'],
            'form.items_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'form.reviews_featured_only' => ['boolean'],
            'form.blog_source' => ['nullable', Rule::in(['latest', 'featured'])],
            'form.template_body' => ['nullable', 'string'],

            'form.slot_placement' => ['required', 'string', 'max:120'],
            'form.slot_frontend_variant' => ['required', Rule::in(array_keys($this->frontendVariants))],
            'form.slot_target_type' => ['nullable', 'string', 'max:80'],
            'form.slot_target_ref' => ['nullable', 'string', 'max:191'],
            'form.slot_sort_order' => ['nullable', 'integer', 'min:0'],
            'form.slot_is_active' => ['boolean'],
            'form.slot_starts_at' => ['nullable', 'date'],
            'form.slot_ends_at' => ['nullable', 'date', 'after_or_equal:form.slot_starts_at'],

            'form.selected_item_ids' => ['nullable', 'array'],
            'form.selected_item_ids.*' => ['integer', 'min:1'],
        ];
    }

    private function resetForm(): void
    {
        $defaultType = array_key_exists('banner', $this->types)
            ? 'banner'
            : (array_key_first($this->types) ?: 'custom');

        $this->form = [
            'code' => '',
            'name' => '',
            'type' => $defaultType,
            'is_active' => true,

            'locale' => config('app.locale'),
            'title' => '',
            'subtitle' => '',
            'cta_label' => '',
            'cta_url' => '',
            'bg_css' => '',
            'custom_classes' => '',
            'items_limit' => 6,
            'reviews_featured_only' => false,
            'blog_source' => 'latest',
            'template_body' => $this->defaultTemplateForType($defaultType),

            'slot_placement' => array_key_first($this->placements) ?: 'home.hero',
            'slot_frontend_variant' => 'all',
            'slot_target_type' => '',
            'slot_target_ref' => '',
            'slot_sort_order' => 0,
            'slot_is_active' => true,
            'slot_starts_at' => '',
            'slot_ends_at' => '',

            'selected_item_ids' => [],
        ];

        $this->pickerItemId = null;
    }

    private function loadBlock(): void
    {
        if (! $this->blockId) {
            return;
        }

        $block = ContentBlock::query()
            ->with([
                'translations',
                'slots' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])
            ->findOrFail($this->blockId);

        $translation = $this->resolveInitialTranslation(
            $block->translations,
            (string) ($this->form['locale'] ?? config('app.locale'))
        );

        $slot = $block->slots->first();
        $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];

        $this->form['code'] = $block->code;
        $this->form['name'] = $block->name;
        $this->form['type'] = $block->type;
        $this->form['is_active'] = (bool) $block->is_active;

        if ($translation) {
            $this->form['locale'] = $translation->locale;
            $this->form['title'] = $translation->title ?? '';
            $this->form['subtitle'] = $translation->subtitle ?? '';
            $this->form['cta_label'] = $translation->cta_label ?? '';
            $this->form['cta_url'] = $translation->cta_url ?? '';
        }

        $this->form['bg_css'] = (string) ($translationPayload['bg_css'] ?? '');
        $this->form['custom_classes'] = (string) ($translationPayload['custom_classes'] ?? '');
        $this->form['items_limit'] = (int) ($translationPayload['items_limit'] ?? 6);
        $this->form['reviews_featured_only'] = (bool) ($translationPayload['reviews_featured_only'] ?? false);
        $blogSource = (string) ($translationPayload['blog_source'] ?? 'latest');
        $this->form['blog_source'] = in_array($blogSource, ['latest', 'featured'], true)
            ? $blogSource
            : 'latest';

        $this->form['slot_placement'] = (string) ($slot?->placement ?? (array_key_first($this->placements) ?: 'home.hero'));
        $loadedVariant = (string) ($slot?->frontend_variant ?? 'all');
        $this->form['slot_frontend_variant'] = in_array($loadedVariant, ['all', 'desktop', 'mobile'], true) ? $loadedVariant : 'all';
        $this->form['slot_target_type'] = (string) ($slot?->target_type ?? '');
        $this->form['slot_target_ref'] = (string) ($slot?->target_ref ?? '');
        $this->form['slot_sort_order'] = (int) ($slot?->sort_order ?? 0);
        $this->form['slot_is_active'] = (bool) ($slot?->is_active ?? true);
        $this->form['slot_starts_at'] = $slot?->starts_at?->format('Y-m-d\\TH:i') ?? '';
        $this->form['slot_ends_at'] = $slot?->ends_at?->format('Y-m-d\\TH:i') ?? '';

        $expectedItemType = $this->itemTypeForBlockType($block->type);
        $selected = $block->items;
        if ($expectedItemType !== null) {
            $selected = $selected->where('item_type', $expectedItemType);
        }

        $this->form['selected_item_ids'] = $selected
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->lastType = (string) $block->type;

        $existingTemplate = $this->readTemplateFile($block->code);
        $this->form['template_body'] = $existingTemplate !== ''
            ? $existingTemplate
            : $this->defaultTemplateForType($block->type);
    }

    private function loadTranslationForLocale(): void
    {
        if (! $this->blockId) {
            $this->clearTranslationFields();

            return;
        }

        $block = ContentBlock::query()
            ->with('translations')
            ->find($this->blockId);

        if (! $block) {
            return;
        }

        $translation = $block->translations->firstWhere('locale', $this->form['locale']);

        if (! $translation) {
            $this->clearTranslationFields();

            return;
        }

        $translationPayload = is_array($translation->payload ?? null) ? $translation->payload : [];

        $this->form['title'] = $translation->title ?? '';
        $this->form['subtitle'] = $translation->subtitle ?? '';
        $this->form['cta_label'] = $translation->cta_label ?? '';
        $this->form['cta_url'] = $translation->cta_url ?? '';
        $this->form['bg_css'] = (string) ($translationPayload['bg_css'] ?? '');
        $this->form['custom_classes'] = (string) ($translationPayload['custom_classes'] ?? '');
        $this->form['items_limit'] = (int) ($translationPayload['items_limit'] ?? 6);
        $this->form['reviews_featured_only'] = (bool) ($translationPayload['reviews_featured_only'] ?? false);
        $blogSource = (string) ($translationPayload['blog_source'] ?? 'latest');
        $this->form['blog_source'] = in_array($blogSource, ['latest', 'featured'], true)
            ? $blogSource
            : 'latest';
    }

    private function resolveInitialTranslation(Collection $translations, string $preferredLocale): mixed
    {
        $fallbackLocale = (string) config('app.locale');

        $preferredWithContent = $translations->first(
            fn ($row): bool => (string) ($row->locale ?? '') === $preferredLocale && $this->translationHasContent($row)
        );
        if ($preferredWithContent) {
            return $preferredWithContent;
        }

        $fallbackWithContent = $translations->first(
            fn ($row): bool => (string) ($row->locale ?? '') === $fallbackLocale && $this->translationHasContent($row)
        );
        if ($fallbackWithContent) {
            return $fallbackWithContent;
        }

        $anyWithContent = $translations->first(fn ($row): bool => $this->translationHasContent($row));
        if ($anyWithContent) {
            return $anyWithContent;
        }

        return $translations->firstWhere('locale', $preferredLocale)
            ?? $translations->firstWhere('locale', $fallbackLocale)
            ?? $translations->first();
    }

    private function translationHasContent(mixed $translation): bool
    {
        if (! $translation) {
            return false;
        }

        $payload = is_array($translation->payload ?? null) ? $translation->payload : [];

        return trim((string) ($translation->title ?? '')) !== ''
            || trim((string) ($translation->subtitle ?? '')) !== ''
            || trim((string) ($translation->cta_label ?? '')) !== ''
            || trim((string) ($translation->cta_url ?? '')) !== ''
            || trim((string) ($translation->body_html ?? '')) !== ''
            || trim((string) ($payload['bg_css'] ?? '')) !== ''
            || trim((string) ($payload['custom_classes'] ?? '')) !== ''
            || (int) ($payload['items_limit'] ?? 0) > 0
            || (bool) ($payload['reviews_featured_only'] ?? false);
    }

    private function clearTranslationFields(): void
    {
        $this->form['title'] = '';
        $this->form['subtitle'] = '';
        $this->form['cta_label'] = '';
        $this->form['cta_url'] = '';
        $this->form['bg_css'] = '';
        $this->form['custom_classes'] = '';
        $this->form['items_limit'] = 6;
        $this->form['reviews_featured_only'] = false;
        $this->form['blog_source'] = 'latest';
    }

    private function itemTypeForBlockType(string $type): ?string
    {
        return self::ITEM_TYPE_MAP[$type] ?? null;
    }

    private function loadItemOptions(?string $itemType): Collection
    {
        $locale = (string) ($this->form['locale'] ?? config('app.locale'));
        $fallbackLocale = config('app.locale');

        if ($itemType === 'product') {
            return Product::query()
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(function (Product $row) use ($locale, $fallbackLocale): array {
                    $translation = $row->translations->firstWhere('locale', $locale)
                        ?? $row->translations->firstWhere('locale', $fallbackLocale);
                    $name = $translation?->name ?: $row->code;

                    return ['id' => (int) $row->id, 'label' => '#'.$row->id.' - '.$name];
                });
        }

        if ($itemType === 'category') {
            return Category::query()
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(function (Category $row) use ($locale, $fallbackLocale): array {
                    $translation = $row->translations->firstWhere('locale', $locale)
                        ?? $row->translations->firstWhere('locale', $fallbackLocale);
                    $name = $translation?->name ?: $row->code;

                    return ['id' => (int) $row->id, 'label' => '#'.$row->id.' - '.$name.' ('.$row->scope.')'];
                });
        }

        if ($itemType === 'manufacturer') {
            return Manufacturer::query()
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(function (Manufacturer $row) use ($locale, $fallbackLocale): array {
                    $translation = $row->translations->firstWhere('locale', $locale)
                        ?? $row->translations->firstWhere('locale', $fallbackLocale);
                    $name = $translation?->name ?: $row->code;

                    return ['id' => (int) $row->id, 'label' => '#'.$row->id.' - '.$name];
                });
        }

        if ($itemType === 'blog') {
            return BlogPost::query()
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(function (BlogPost $row) use ($locale, $fallbackLocale): array {
                    $translation = $row->translations->firstWhere('locale', $locale)
                        ?? $row->translations->firstWhere('locale', $fallbackLocale);
                    $title = $translation?->title ?: $row->code;

                    return ['id' => (int) $row->id, 'label' => '#'.$row->id.' - '.$title];
                });
        }

        return collect();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function validIdsForItemType(string $itemType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        if ($itemType === 'product') {
            return Product::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($itemType === 'category') {
            return Category::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($itemType === 'manufacturer') {
            return Manufacturer::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($itemType === 'blog') {
            return BlogPost::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
    }

    private function savePrimarySlot(ContentBlock $block, array $validated, ?int $userId): void
    {
        $slotData = [
            'placement' => (string) $validated['form']['slot_placement'],
            'frontend_variant' => (string) ($validated['form']['slot_frontend_variant'] ?? 'all'),
            'target_type' => $validated['form']['slot_target_type'] ?: null,
            'target_ref' => $validated['form']['slot_target_ref'] ?: null,
            'sort_order' => (int) $validated['form']['slot_sort_order'],
            'is_active' => (bool) $validated['form']['slot_is_active'],
            'starts_at' => $validated['form']['slot_starts_at'] ?: null,
            'ends_at' => $validated['form']['slot_ends_at'] ?: null,
            'updated_by' => $userId,
        ];

        $slot = $block->slots()->orderBy('sort_order')->orderBy('id')->first();
        if ($slot) {
            $slot->update($slotData);

            return;
        }

        $block->slots()->create($slotData + ['created_by' => $userId]);
    }

    /**
     * @param  array<int, int>  $itemIds
     */
    private function syncBlockItems(ContentBlock $block, ?string $itemType, array $itemIds): void
    {
        if ($itemType === null) {
            $block->items()->delete();

            return;
        }

        $block->items()->delete();

        foreach (array_values($itemIds) as $index => $itemId) {
            $block->items()->create([
                'item_type' => $itemType,
                'item_id' => (int) $itemId,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $types
     * @return array<string, string>
     */
    private function orderedTypes(array $types): array
    {
        $priority = [
            'banner',
            'desktop_hero_banner',
            'full_width_image_slider',
            'dual_image_cta',
            'five_star_reviews_carousel',
            'mobile_hero_banner',
            'hero_highlights_strip',
            'products',
            'products_carousel',
            'blogs_carousel',
            'categories',
            'manufacturers',
            'blogs',
        ];
        $ordered = [];

        foreach ($priority as $key) {
            if (array_key_exists($key, $types)) {
                $ordered[$key] = $types[$key];
                unset($types[$key]);
            }
        }

        foreach ($types as $key => $label) {
            $ordered[$key] = $label;
        }

        return $ordered;
    }

    private function suggestedFrontendVariantForType(string $type): ?string
    {
        return match ($type) {
            'mobile_hero_banner' => 'mobile',
            'desktop_hero_banner',
            'full_width_image_slider',
            'dual_image_cta',
            'five_star_reviews_carousel',
            'blogs_carousel',
            'hero_highlights_strip' => 'desktop',
            default => null,
        };
    }

    private function defaultTemplateForType(string $type): string
    {
        return match ($type) {
            'banner' => <<<'BLADE'
@php
    $basePayload = is_array($block->payload ?? null) ? $block->payload : [];
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $payload = array_merge($basePayload, $translationPayload);

    $bgCss = trim((string) ($payload['bg_css'] ?? ''));
    $customClasses = trim((string) ($payload['custom_classes'] ?? ''));

    $bgUrl = $block->getFirstMediaUrl('block_background', 'hero_1440x480');
    if ($bgUrl === '') {
        $bgUrl = $block->getFirstMediaUrl('block_background');
    }

    $style = $bgCss;
    if ($bgUrl !== '') {
        $style = "background-image:url('{$bgUrl}');background-size:cover;background-position:center; ".$bgCss;
    }
@endphp

<section class="relative overflow-hidden rounded-3xl border border-slate-200/70 p-8 md:p-12 {{ $customClasses }}" @if($style !== '') style="{{ $style }}" @endif>
    <div class="absolute inset-0 bg-gradient-to-br from-white/80 via-white/60 to-white/40"></div>
    <div class="relative z-10 max-w-3xl">
        <h2 class="text-4xl font-extrabold tracking-tight md:text-5xl">{{ $translation?->title ?: $block->name }}</h2>
        @if(!empty($translation?->subtitle))
            <p class="mt-4 text-lg text-slate-700">{{ $translation->subtitle }}</p>
        @endif
        @if(!empty($translation?->cta_label) && !empty($translation?->cta_url))
            <a href="{{ $translation->cta_url }}" class="mt-8 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $translation->cta_label }}</a>
        @endif
    </div>
</section>
BLADE,
            'products' => <<<'BLADE'
<section class="rounded-3xl border border-slate-200 bg-white p-6">
    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $translation?->title ?: $block->name }}</h2>
    @if(!empty($translation?->subtitle))
        <p class="mt-2 text-sm text-slate-600">{{ $translation->subtitle }}</p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($products as $product)
            @php
                $pt = $product->translations->firstWhere('locale', app()->getLocale())
                    ?? $product->translations->firstWhere('locale', config('app.locale'));
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="h-36 rounded-xl bg-gradient-to-br from-slate-200 to-slate-100"></div>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $pt?->name ?? $product->code }}</h3>
                <p class="mt-2 text-sm font-semibold text-slate-800">{{ \App\Support\Currency::format((float) $product->base_price) }}</p>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-4">No products selected.</div>
        @endforelse
    </div>
</section>
BLADE,
            'products_carousel' => <<<'BLADE'
@php
    $locale = app()->getLocale();
    $fallbackLocale = config('app.locale');
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $blockPayload = is_array($block->payload ?? null) ? $block->payload : [];
    $mergedPayload = array_merge($blockPayload, $translationPayload);
    $allowedRoutes = config('content_blocks.route_whitelist', []);
    $displayTitle = trim((string) ($translation?->title ?? ''));
    $displaySubtitle = trim((string) ($translation?->subtitle ?? ''));

    if ($displayTitle === '' || $displaySubtitle === '') {
        $allTranslations = $block->translations()->get(['locale', 'title', 'subtitle']);

        if ($displayTitle === '') {
            $displayTitle = trim((string) ($allTranslations->firstWhere('locale', $locale)?->title ?? ''));
            if ($displayTitle === '') {
                $displayTitle = trim((string) ($allTranslations->firstWhere('locale', $fallbackLocale)?->title ?? ''));
            }
            if ($displayTitle === '') {
                $displayTitle = trim((string) ($allTranslations->first(
                    static fn ($row): bool => trim((string) ($row->title ?? '')) !== ''
                )?->title ?? ''));
            }
        }

        if ($displaySubtitle === '') {
            $displaySubtitle = trim((string) ($allTranslations->firstWhere('locale', $locale)?->subtitle ?? ''));
            if ($displaySubtitle === '') {
                $displaySubtitle = trim((string) ($allTranslations->firstWhere('locale', $fallbackLocale)?->subtitle ?? ''));
            }
            if ($displaySubtitle === '') {
                $displaySubtitle = trim((string) ($allTranslations->first(
                    static fn ($row): bool => trim((string) ($row->subtitle ?? '')) !== ''
                )?->subtitle ?? ''));
            }
        }
    }

    if ($displayTitle === '') {
        $displayTitle = (string) $block->name;
    }

    $resolveRouteUrl = function (?string $routeName, mixed $routeParams, string $fallbackUrl = '#') use ($allowedRoutes): string {
        $name = trim((string) $routeName);
        if ($name === '') {
            return $fallbackUrl;
        }

        $isAllowed = $allowedRoutes === []
            || collect($allowedRoutes)->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, $name));

        if (! $isAllowed || !\Illuminate\Support\Facades\Route::has($name)) {
            return $fallbackUrl;
        }

        $params = is_array($routeParams) ? $routeParams : [];

        try {
            return route($name, $params);
        } catch (\Throwable) {
            return $fallbackUrl;
        }
    };

    $ctaLabel = trim((string) ($translation?->cta_label ?? ''));
    $ctaFallbackUrl = (string) ($translation?->cta_url ?? '#');
    $ctaRoute = (string) ($mergedPayload['cta_route'] ?? '');
    $ctaRouteParams = $mergedPayload['cta_route_params'] ?? [];
    $ctaUrl = $resolveRouteUrl($ctaRoute, $ctaRouteParams, $ctaFallbackUrl);
@endphp

<section class="relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] w-screen bg-white py-8">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="mb-8 text-center">
            <div class="mx-auto flex max-w-3xl items-center gap-4 md:gap-6">
                <span class="h-px flex-1 bg-slate-300"></span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">{{ $displayTitle }}</h2>
                <span class="h-px flex-1 bg-slate-300"></span>
            </div>
            @if ($displaySubtitle !== '')
                <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 md:text-base">{{ $displaySubtitle }}</p>
            @endif

            @if ($ctaLabel !== '' && $ctaUrl !== '')
                <a href="{{ $ctaUrl }}" class="mt-4 inline-flex h-10 items-center bg-slate-100 px-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 hover:bg-slate-200">
                    {{ $ctaLabel }}
                </a>
            @endif
        </div>

        @if ($products->isNotEmpty())
            <style>
                #products-carousel-{{ $block->id }} .splide__arrow {
                    opacity: 0;
                    width: 46px;
                    height: 46px;
                    border-radius: 9999px;
                    border: 1px solid rgba(255, 255, 255, 0.75);
                    background: rgba(15, 23, 42, 0.35);
                    backdrop-filter: blur(6px);
                    transform: translateY(-50%) scale(0.92);
                    transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
                }

                #products-carousel-{{ $block->id }}:hover .splide__arrow,
                #products-carousel-{{ $block->id }}:focus-within .splide__arrow {
                    opacity: 1;
                    transform: translateY(-50%) scale(1);
                }

                #products-carousel-{{ $block->id }} .splide__arrow:hover {
                    background: rgba(15, 23, 42, 0.55);
                }

                #products-carousel-{{ $block->id }} .splide__arrow svg {
                    fill: #fff;
                }

                @media (hover: none) {
                    #products-carousel-{{ $block->id }} .splide__arrow {
                        opacity: 1;
                        transform: translateY(-50%) scale(1);
                    }
                }
            </style>

            @once
                @push('scripts')
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
                    <script defer src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
                @endpush
            @endonce

            <div class="mt-4">
                <div id="products-carousel-{{ $block->id }}" class="splide" data-products-carousel-splide>
                    <div class="splide__track">
                        <ul class="splide__list">
                            @foreach ($products as $product)
                                <li class="splide__slide">
                                    @include('front.desktop.partials.product-card', [
                                        'product' => $product,
                                        'locale' => $locale,
                                        'fallbackLocale' => $fallbackLocale,
                                        'flat' => true,
                                    ])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            @once
                @push('scripts')
                    <script>
                        (function () {
                            const init = function () {
                                if (typeof window.Splide !== 'function') {
                                    return false;
                                }

                                const sliders = document.querySelectorAll('[data-products-carousel-splide]');
                                sliders.forEach(function (el) {
                                    if (el.dataset.splideReady === '1') {
                                        return;
                                    }
                                    el.dataset.splideReady = '1';

                                    const count = el.querySelectorAll('.splide__slide').length;
                                    new window.Splide(el, {
                                        type: count > 1 ? 'loop' : 'slide',
                                        perPage: Math.min(4, Math.max(1, count)),
                                        perMove: 1,
                                        gap: '1.25rem',
                                        drag: count > 1,
                                        snap: true,
                                        pagination: count > 1,
                                        arrows: count > 1,
                                        updateOnMove: true,
                                        speed: 520,
                                        breakpoints: {
                                            1280: { perPage: Math.min(3, Math.max(1, count)) },
                                            1024: { perPage: Math.min(2, Math.max(1, count)) },
                                            860: { perPage: 1, gap: '1rem' },
                                            640: { perPage: 1, gap: '0.8rem' },
                                        },
                                    }).mount();
                                });

                                return true;
                            };

                            if (init()) {
                                return;
                            }

                            let attempts = 0;
                            const timer = window.setInterval(function () {
                                attempts += 1;
                                if (init() || attempts > 40) {
                                    window.clearInterval(timer);
                                }
                            }, 120);
                        })();
                    </script>
                @endpush
            @endonce
        @else
            <div class="bg-slate-50 p-4 text-xs text-slate-500">
                No selected products for this carousel.
            </div>
        @endif
    </div>
</section>
BLADE,
            'five_star_reviews_carousel' => <<<'BLADE'
@php
    $locale = app()->getLocale();
    $fallbackLocale = config('app.locale');
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $blockPayload = is_array($block->payload ?? null) ? $block->payload : [];
    $mergedPayload = array_merge($blockPayload, $translationPayload);
    $allowedRoutes = config('content_blocks.route_whitelist', []);
    $displayTitle = trim((string) ($translation?->title ?? ''));
    $displaySubtitle = trim((string) ($translation?->subtitle ?? ''));
    $itemsLimit = max(1, (int) ($mergedPayload['items_limit'] ?? 6));

    if ($displayTitle === '' || $displaySubtitle === '') {
        $allTranslations = $block->translations()->get(['locale', 'title', 'subtitle']);

        if ($displayTitle === '') {
            $displayTitle = trim((string) ($allTranslations->firstWhere('locale', $locale)?->title ?? ''));
            if ($displayTitle === '') {
                $displayTitle = trim((string) ($allTranslations->firstWhere('locale', $fallbackLocale)?->title ?? ''));
            }
            if ($displayTitle === '') {
                $displayTitle = trim((string) ($allTranslations->first(
                    static fn ($row): bool => trim((string) ($row->title ?? '')) !== ''
                )?->title ?? ''));
            }
        }

        if ($displaySubtitle === '') {
            $displaySubtitle = trim((string) ($allTranslations->firstWhere('locale', $locale)?->subtitle ?? ''));
            if ($displaySubtitle === '') {
                $displaySubtitle = trim((string) ($allTranslations->firstWhere('locale', $fallbackLocale)?->subtitle ?? ''));
            }
            if ($displaySubtitle === '') {
                $displaySubtitle = trim((string) ($allTranslations->first(
                    static fn ($row): bool => trim((string) ($row->subtitle ?? '')) !== ''
                )?->subtitle ?? ''));
            }
        }
    }

    if ($displayTitle === '') {
        $displayTitle = (string) $block->name;
    }

    $resolveRouteUrl = function (?string $routeName, mixed $routeParams, string $fallbackUrl = '#') use ($allowedRoutes): string {
        $name = trim((string) $routeName);
        if ($name === '') {
            return $fallbackUrl;
        }

        $isAllowed = $allowedRoutes === []
            || collect($allowedRoutes)->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, $name));

        if (! $isAllowed || !\Illuminate\Support\Facades\Route::has($name)) {
            return $fallbackUrl;
        }

        $params = is_array($routeParams) ? $routeParams : [];

        try {
            return route($name, $params);
        } catch (\Throwable) {
            return $fallbackUrl;
        }
    };

    $ctaLabel = trim((string) ($translation?->cta_label ?? ''));
    $ctaFallbackUrl = (string) ($translation?->cta_url ?? '#');
    $ctaRoute = (string) ($mergedPayload['cta_route'] ?? '');
    $ctaRouteParams = $mergedPayload['cta_route_params'] ?? [];
    $ctaUrl = $resolveRouteUrl($ctaRoute, $ctaRouteParams, $ctaFallbackUrl);
    $reviewRows = ($comments ?? collect())->take($itemsLimit);
@endphp

<section class="relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] w-screen bg-white py-8">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="mb-8 text-center">
            <div class="mx-auto flex max-w-3xl items-center gap-4 md:gap-6">
                <span class="h-px flex-1 bg-slate-300"></span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">{{ $displayTitle }}</h2>
                <span class="h-px flex-1 bg-slate-300"></span>
            </div>
            @if ($displaySubtitle !== '')
                <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 md:text-base">{{ $displaySubtitle }}</p>
            @endif
            @if ($ctaLabel !== '' && $ctaUrl !== '')
                <a href="{{ $ctaUrl }}" class="mt-4 inline-flex h-10 items-center bg-slate-100 px-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 hover:bg-slate-200">
                    {{ $ctaLabel }}
                </a>
            @endif
        </div>

        @if ($reviewRows->isNotEmpty())
            <style>
                #reviews-carousel-{{ $block->id }} .splide__arrow {
                    opacity: 0;
                    width: 46px;
                    height: 46px;
                    border-radius: 9999px;
                    border: 1px solid rgba(255, 255, 255, 0.75);
                    background: rgba(15, 23, 42, 0.35);
                    backdrop-filter: blur(6px);
                    transform: translateY(-50%) scale(0.92);
                    transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
                }

                #reviews-carousel-{{ $block->id }}:hover .splide__arrow,
                #reviews-carousel-{{ $block->id }}:focus-within .splide__arrow {
                    opacity: 1;
                    transform: translateY(-50%) scale(1);
                }

                #reviews-carousel-{{ $block->id }} .splide__arrow:hover {
                    background: rgba(15, 23, 42, 0.55);
                }

                #reviews-carousel-{{ $block->id }} .splide__arrow svg {
                    fill: #fff;
                }

                #reviews-carousel-{{ $block->id }} .review-card {
                    border: 1px solid #dbe3ef;
                    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
                }

                #reviews-carousel-{{ $block->id }} .review-quote {
                    color: #c9d3e5;
                    font-size: 2rem;
                    line-height: 1;
                    font-weight: 700;
                }
            </style>

            @once
                @push('scripts')
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
                    <script defer src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
                @endpush
            @endonce

            <div id="reviews-carousel-{{ $block->id }}" class="splide" data-five-star-reviews-splide>
                <div class="splide__track">
                    <ul class="splide__list">
                        @foreach ($reviewRows as $row)
                            <li class="splide__slide">
                                @php
                                    $author = $row->author_name ?: __('ui.product.comments_anonymous');
                                    $authorInitial = mb_strtoupper(mb_substr(trim($author), 0, 1));
                                @endphp
                                <article class="review-card h-full p-6">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-amber-500">★★★★★</p>
                                        <span class="review-quote" aria-hidden="true">“</span>
                                    </div>
                                    <p class="mt-3 line-clamp-4 text-sm leading-relaxed text-slate-700">{{ $row->body }}</p>
                                    <div class="mt-5 flex items-center gap-3 border-t border-slate-200 pt-3">
                                        <span class="inline-flex h-8 w-8 items-center justify-center border border-slate-300 bg-white text-xs font-bold uppercase text-slate-700">{{ $authorInitial }}</span>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $author }}</p>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @once
                @push('scripts')
                    <script>
                        (function () {
                            const init = function () {
                                if (typeof window.Splide !== 'function') {
                                    return false;
                                }

                                const sliders = document.querySelectorAll('[data-five-star-reviews-splide]');
                                sliders.forEach(function (el) {
                                    if (el.dataset.splideReady === '1') {
                                        return;
                                    }
                                    el.dataset.splideReady = '1';

                                    const count = el.querySelectorAll('.splide__slide').length;
                                    new window.Splide(el, {
                                        type: count > 1 ? 'loop' : 'slide',
                                        perPage: Math.min(3, Math.max(1, count)),
                                        perMove: 1,
                                        gap: '1.25rem',
                                        drag: count > 1,
                                        snap: true,
                                        pagination: count > 1,
                                        arrows: count > 1,
                                        breakpoints: {
                                            1024: { perPage: Math.min(2, Math.max(1, count)) },
                                            640: { perPage: 1 },
                                        },
                                    }).mount();
                                });

                                return true;
                            };

                            if (init()) {
                                return;
                            }

                            let attempts = 0;
                            const timer = window.setInterval(function () {
                                attempts += 1;
                                if (init() || attempts > 40) {
                                    window.clearInterval(timer);
                                }
                            }, 120);
                        })();
                    </script>
                @endpush
            @endonce
        @endif
    </div>
</section>
BLADE,
            'blogs_carousel' => <<<'BLADE'
@include('front.content-blocks.types.blogs_carousel', [
    'block' => $block,
    'translation' => $translation,
    'slot' => $slot ?? null,
    'blockItems' => $blockItems ?? collect(),
])
BLADE,
            'categories' => <<<'BLADE'
<section class="rounded-3xl border border-slate-200 bg-white p-6">
    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $translation?->title ?: $block->name }}</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($categories as $category)
            @php
                $ct = $category->translations->firstWhere('locale', app()->getLocale())
                    ?? $category->translations->firstWhere('locale', config('app.locale'));
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">{{ $ct?->name ?? $category->code }}</h3>
                <p class="mt-1 text-xs uppercase tracking-[0.12em] text-slate-500">{{ $category->scope }}</p>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-4">No categories selected.</div>
        @endforelse
    </div>
</section>
BLADE,
            'manufacturers' => <<<'BLADE'
<section class="rounded-3xl border border-slate-200 bg-white p-6">
    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $translation?->title ?: $block->name }}</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($manufacturers as $manufacturer)
            @php
                $mt = $manufacturer->translations->firstWhere('locale', app()->getLocale())
                    ?? $manufacturer->translations->firstWhere('locale', config('app.locale'));
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">{{ $mt?->name ?? $manufacturer->code }}</h3>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-4">No manufacturers selected.</div>
        @endforelse
    </div>
</section>
BLADE,
            'blogs' => <<<'BLADE'
<section class="rounded-3xl border border-slate-200 bg-white p-6">
    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $translation?->title ?: $block->name }}</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($blogs as $post)
            @php
                $bt = $post->translations->firstWhere('locale', app()->getLocale())
                    ?? $post->translations->firstWhere('locale', config('app.locale'));
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">{{ $bt?->title ?? $post->code }}</h3>
                @if(!empty($bt?->excerpt))
                    <p class="mt-1 text-xs text-slate-600">{{ \Illuminate\Support\Str::limit((string)$bt->excerpt, 100, '...') }}</p>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">No blog posts selected.</div>
        @endforelse
    </div>
</section>
BLADE,
            'desktop_hero_banner' => <<<'BLADE'
@php
    $title = $translation?->title ?: 'Modern essentials, built for everyday carry.';
    $subtitle = $translation?->subtitle ?: 'AGShop combines durable materials, clean silhouettes and practical storage to keep your daily setup lightweight and ready.';
    $primaryCtaLabel = $translation?->cta_label ?: 'Shop featured';
    $primaryCtaUrl = $translation?->cta_url ?: '#featured';
@endphp

<div class="max-w-3xl text-white">
    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm">
        <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
        New season collection live now
    </p>

    <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight lg:text-6xl">
        {!! nl2br(e($title)) !!}
    </h1>

    @if ($subtitle !== '')
        <p class="mt-6 max-w-xl text-lg text-white/90">{{ $subtitle }}</p>
    @endif

    <div class="mt-10 flex flex-wrap items-center gap-4">
        <a href="{{ $primaryCtaUrl }}" class="rounded-xl bg-white px-6 py-3 font-semibold text-blue-700 hover:bg-slate-100">
            {{ $primaryCtaLabel }}
        </a>
        <a href="#categories" class="rounded-xl border border-white/30 px-6 py-3 text-white hover:bg-white/10">
            Browse categories
        </a>
    </div>
</div>
BLADE,
            'full_width_image_slider' => <<<'BLADE'
@php
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $customClasses = trim((string) ($translationPayload['custom_classes'] ?? ''));
    $sliderId = 'full-width-slider-'.$block->id;
    $slides = $block->getMedia('block_slides');

    if ($slides->isEmpty()) {
        $fallback = $block->getFirstMedia('block_background');
        if ($fallback) {
            $slides = collect([$fallback]);
        }
    }

    $autoplayMs = 5000;
@endphp

@if ($slides->isNotEmpty())
    @once
        @push('scripts')
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
            <script defer src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
        @endpush
    @endonce

    <style>
        #{{ $sliderId }} .splide__arrow {
            opacity: 0;
            width: 46px;
            height: 46px;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(6px);
            transform: translateY(-50%) scale(0.92);
            transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
        }

        #{{ $sliderId }}:hover .splide__arrow,
        #{{ $sliderId }}:focus-within .splide__arrow {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }

        #{{ $sliderId }} .splide__arrow:hover {
            background: rgba(15, 23, 42, 0.55);
        }

        #{{ $sliderId }} .splide__arrow svg {
            fill: #fff;
        }
    </style>

    <section class="relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] w-screen overflow-hidden {{ $customClasses }}">
        <div id="{{ $sliderId }}" class="splide" data-fullwidth-splide>
            <div class="splide__track">
                <ul class="splide__list">
                    @foreach ($slides as $media)
                        @php
                            $slideUrl = $media->hasGeneratedConversion('hero_1440x480')
                                ? $media->getUrl('hero_1440x480')
                                : $media->getUrl();
                            $slideLink = trim((string) (
                                data_get($media->custom_properties, 'link_url.'.app()->getLocale())
                                ?: data_get($media->custom_properties, 'link_url.'.config('app.locale'))
                                ?: data_get($media->custom_properties, 'link_url_value', '')
                            ));
                            $hasSlideLink = $slideLink !== '';
                        @endphp
                        <li class="splide__slide">
                            <article class="relative min-w-full">
                                @if ($hasSlideLink)
                                    <a href="{{ $slideLink }}" class="block">
                                @endif
                                    <img src="{{ $slideUrl }}" alt="{{ $translation?->title ?: $block->name }} {{ $loop->iteration }}" class="h-[42vw] min-h-[420px] max-h-[880px] w-full object-cover">
                                    <div class="absolute inset-0 bg-black/10"></div>
                                    @if (($translation?->title ?? '') !== '' || ($translation?->subtitle ?? '') !== '')
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 via-black/20 to-transparent px-6 pb-10 pt-16 text-white md:px-12">
                                            @if (($translation?->title ?? '') !== '')
                                                <h2 class="text-3xl font-extrabold tracking-tight md:text-5xl">{{ $translation->title }}</h2>
                                            @endif
                                            @if (($translation?->subtitle ?? '') !== '')
                                                <p class="mt-3 max-w-3xl text-sm text-white/90 md:text-base">{{ $translation->subtitle }}</p>
                                            @endif
                                            @if (($translation?->cta_label ?? '') !== '' && (($translation?->cta_url ?? '') !== '' || $hasSlideLink))
                                                @if ($hasSlideLink)
                                                    <span class="mt-6 inline-flex h-11 items-center border border-white bg-white px-6 text-sm font-semibold text-slate-900">
                                                        {{ $translation->cta_label }}
                                                    </span>
                                                @else
                                                    <a href="{{ $translation->cta_url }}" class="mt-6 inline-flex h-11 items-center border border-white bg-white px-6 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                                                        {{ $translation->cta_label }}
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                @if ($hasSlideLink)
                                    </a>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    @once
        @push('scripts')
            <script>
                (function () {
                    const init = function () {
                        if (typeof window.Splide !== 'function') {
                            return false;
                        }

                        const sliders = document.querySelectorAll('[data-fullwidth-splide]');
                        sliders.forEach(function (el) {
                            if (el.dataset.splideReady === '1') {
                                return;
                            }
                            el.dataset.splideReady = '1';

                            const count = el.querySelectorAll('.splide__slide').length;
                            new window.Splide(el, {
                                type: count > 1 ? 'loop' : 'slide',
                                perPage: 1,
                                perMove: 1,
                                arrows: count > 1,
                                pagination: count > 1,
                                autoplay: count > 1,
                                interval: {{ $autoplayMs }},
                                pauseOnHover: true,
                                pauseOnFocus: true,
                                speed: 700,
                                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                            }).mount();
                        });

                        return true;
                    };

                    if (init()) {
                        return;
                    }

                    let attempts = 0;
                    const timer = window.setInterval(function () {
                        attempts += 1;
                        if (init() || attempts > 40) {
                            window.clearInterval(timer);
                        }
                    }, 120);
                })();
            </script>
        @endpush
    @endonce
@endif
BLADE,
            'dual_image_cta' => <<<'BLADE'
@php
    $locale = app()->getLocale();
    $fallbackLocale = config('app.locale');
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $customClasses = trim((string) ($translationPayload['custom_classes'] ?? ''));
    $slides = $block->getMedia('block_slides')->take(2);
@endphp

@if ($slides->isNotEmpty())
    <section class="relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] w-screen {{ $customClasses }}">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            @foreach ($slides as $media)
                @php
                    $imageUrl = $media->hasGeneratedConversion('hero_1440x480')
                        ? $media->getUrl('hero_1440x480')
                        : $media->getUrl();
                    $props = (array) ($media->custom_properties ?? []);
                    $slideTitle = trim((string) (
                        data_get($props, "block_title.$locale")
                        ?: data_get($props, "block_title.$fallbackLocale")
                        ?: $media->name
                    ));

                    $cta1Label = trim((string) (
                        data_get($props, "cta_1_label.$locale")
                        ?: data_get($props, "cta_1_label.$fallbackLocale")
                        ?: __('ui.content_blocks.dual_image_cta.default_cta_1')
                    ));
                    $cta1Url = trim((string) (
                        data_get($props, "cta_1_url.$locale")
                        ?: data_get($props, "cta_1_url.$fallbackLocale")
                        ?: '#'
                    ));

                    $cta2Label = trim((string) (
                        data_get($props, "cta_2_label.$locale")
                        ?: data_get($props, "cta_2_label.$fallbackLocale")
                        ?: __('ui.content_blocks.dual_image_cta.default_cta_2')
                    ));
                    $cta2Url = trim((string) (
                        data_get($props, "cta_2_url.$locale")
                        ?: data_get($props, "cta_2_url.$fallbackLocale")
                        ?: '#'
                    ));
                @endphp

                <article class="group relative min-h-[360px] overflow-hidden md:min-h-[560px]">
                    <img src="{{ $imageUrl }}" alt="{{ $slideTitle !== '' ? $slideTitle : $block->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/20 to-transparent"></div>

                    <div class="absolute inset-x-0 bottom-12 px-8 text-center text-white md:bottom-16 md:px-10">
                        @if ($slideTitle !== '')
                            <h3 class="text-3xl font-black uppercase tracking-[0.02em] md:text-4xl">{{ $slideTitle }}</h3>
                        @endif

                        <div class="mx-auto mt-5 flex max-w-[460px] flex-col justify-center gap-2.5 sm:flex-row">
                            @if ($cta1Label !== '')
                                <a href="{{ $cta1Url !== '' ? $cta1Url : '#' }}" class="inline-flex h-11 min-w-[145px] items-center justify-center border border-white bg-white px-5 text-base font-black uppercase tracking-[0.02em] text-slate-900 transition hover:bg-slate-100">
                                    {{ $cta1Label }}
                                </a>
                            @endif

                            @if ($cta2Label !== '')
                                <a href="{{ $cta2Url !== '' ? $cta2Url : '#' }}" class="inline-flex h-11 min-w-[145px] items-center justify-center border border-white bg-white px-5 text-base font-black uppercase tracking-[0.02em] text-slate-900 transition hover:bg-slate-100">
                                    {{ $cta2Label }}
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
BLADE,
            'mobile_hero_banner' => <<<'BLADE'
@php
    $basePayload = is_array($block->payload ?? null) ? $block->payload : [];
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $payload = array_merge($basePayload, $translationPayload);

    $title = $translation?->title ?: 'Modern essentials';
    $subtitle = $translation?->subtitle ?: 'Browse category picks and essentials.';
    $ctaLabel = $translation?->cta_label ?: 'Shop';
    $ctaUrl = $translation?->cta_url ?: '#categories';
    $sliderId = 'mobile-hero-slider-'.$block->id;

    $slideClassList = ['bg-19', 'bg-18', 'bg-17', 'bg-20'];
    $customClasses = trim((string) ($payload['custom_classes'] ?? ''));
    if ($customClasses !== '') {
        $slideClassList = preg_split('/\s+/', $customClasses) ?: $slideClassList;
    }
@endphp

@if ($categories->isNotEmpty())
    <div class="splide single-slider slider-no-arrows slider-no-dots" id="{{ $sliderId }}">
        <div class="splide__track">
            <div class="splide__list">
                @foreach ($categories as $index => $category)
                    @php
                        $ct = $category->translations->firstWhere('locale', app()->getLocale())
                            ?? $category->translations->firstWhere('locale', config('app.locale'));
                        $categoryName = $ct?->name ?: $category->code;
                        $slideClass = $slideClassList[$index % max(count($slideClassList), 1)] ?? 'bg-19';
                    @endphp
                    <div class="splide__slide">
                        <div class="card card-style mb-3 {{ $slideClass }}" data-card-height="300">
                            <div class="card-bottom mb-3 ms-3 me-3">
                                <h1 class="color-white font-800 mb-n2">{{ $categoryName }}</h1>
                                <p class="color-white font-14 mb-2 opacity-60">{{ $subtitle }}</p>
                                <a href="{{ $ctaUrl }}" class="btn btn-xxs rounded-xs bg-white color-black font-700 mt-2">
                                    {{ trim($ctaLabel.' '.$categoryName) }}
                                </a>
                            </div>
                            <div class="card-overlay bg-black opacity-60"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="card card-style mb-3 bg-19" data-card-height="300">
        <div class="card-bottom mb-3 ms-3 me-3">
            <h1 class="color-white font-800 mb-n2">{{ $title }}</h1>
            <p class="color-white font-14 mb-2 opacity-60">{{ $subtitle }}</p>
            <a href="{{ $ctaUrl }}" class="btn btn-xxs rounded-xs bg-white color-black font-700 mt-2">{{ $ctaLabel }}</a>
        </div>
        <div class="card-overlay bg-black opacity-60"></div>
    </div>
@endif
BLADE,
            'hero_highlights_strip' => <<<'BLADE'
<div class="mx-auto grid max-w-7xl gap-8 text-white md:grid-cols-3">
    <div class="flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15">
            <span class="text-xl font-bold">+</span>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none">Fast Dispatch</div>
            <div class="mt-1 text-sm leading-tight text-white/80">Orders before 14:00 ship same day.</div>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15">
            <span class="text-xl font-bold">+</span>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none">Easy Returns</div>
            <div class="mt-1 text-sm leading-tight text-white/80">30-day return flow with no paperwork.</div>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15">
            <span class="text-xl font-bold">+</span>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none">Secure Checkout</div>
            <div class="mt-1 text-sm leading-tight text-white/80">Card, Apple Pay, and Google Pay support.</div>
        </div>
    </div>
</div>
BLADE,
            default => <<<'BLADE'
<section class="rounded-2xl border border-slate-200 bg-white p-6">
    @if(!empty($translation?->title))
        <h2 class="text-xl font-semibold text-slate-900">{{ $translation->title }}</h2>
    @endif
    @if(!empty($translation?->subtitle))
        <p class="mt-2 text-sm text-slate-600">{{ $translation->subtitle }}</p>
    @endif
</section>
BLADE,
        };
    }

    private function templateViewName(string $code): string
    {
        return 'front.content-blocks.instances.'.$code;
    }

    private function templateFilePath(string $code): string
    {
        return resource_path('views/front/content-blocks/instances/'.$code.'.blade.php');
    }

    private function readTemplateFile(string $code): string
    {
        $path = $this->templateFilePath($code);
        if (! File::exists($path)) {
            return '';
        }

        return (string) File::get($path);
    }

    private function writeTemplateFile(string $code, string $contents): void
    {
        $dir = resource_path('views/front/content-blocks/instances');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->templateFilePath($code), rtrim($contents)."\n");
    }

    private function deleteTemplateFile(string $code): void
    {
        $path = $this->templateFilePath($code);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function normalizedTemplate(string $template): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $template));
    }
}
