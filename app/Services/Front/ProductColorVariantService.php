<?php

namespace App\Services\Front;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Services\Catalog\CatalogFeatureService;
use App\Support\ProductColorSwatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductColorVariantService
{
    private const MAX_VARIANTS = 24;

    private const MAX_VARIANT_CANDIDATES = 96;

    /**
     * @return Collection<int, array{
     *     product_id:int,
     *     color_value_id:int,
     *     color_key:string,
     *     label:string,
     *     url:string,
     *     sku:string,
     *     is_current:bool,
     *     sort_order:int,
     *     swatch_image_url:?string,
     *     swatch_style:string
     * }>
     */
    public function variantsFor(Product $product, string $locale, string $fallbackLocale): Collection
    {
        $currentColor = $this->colorValueForProduct($product, $locale, $fallbackLocale);
        if (! $currentColor) {
            return collect();
        }

        $group = $this->variantGroup($product, $locale, $fallbackLocale);
        if ($group === null) {
            return collect();
        }

        $variants = $this->variantProducts($group, $locale, $fallbackLocale)
            ->map(function (Product $variantProduct) use ($product, $locale, $fallbackLocale): ?array {
                $colorValue = $this->colorValueForProduct($variantProduct, $locale, $fallbackLocale);
                if (! $colorValue) {
                    return null;
                }

                $translation = $this->productTranslation($variantProduct, $locale, $fallbackLocale);
                $slug = trim((string) ($translation?->slug ?? ''));
                if ($slug === '') {
                    return null;
                }

                $label = $this->valueLabel($colorValue, $locale, $fallbackLocale);
                $colorKey = $this->normalizeSwatchKey((string) $colorValue->code);
                if ($colorKey === '') {
                    $colorKey = 'value-'.$colorValue->id;
                }

                return [
                    'product_id' => (int) $variantProduct->id,
                    'color_value_id' => (int) $colorValue->id,
                    'color_key' => $colorKey,
                    'label' => $label !== '' ? $label : (string) $colorValue->code,
                    'url' => route('products.show', ['slug' => $slug]),
                    'sku' => (string) ($variantProduct->sku ?: $variantProduct->code),
                    'is_current' => (int) $variantProduct->id === (int) $product->id,
                    'sort_order' => (int) ($colorValue->sort_order ?? 0),
                    'swatch_image_url' => $this->swatchImageUrl($colorValue),
                    'swatch_style' => ProductColorSwatch::style($colorValue),
                ];
            })
            ->filter()
            ->sortBy(fn (array $variant): string => implode('-', [
                $variant['is_current'] ? '0' : '1',
                str_pad((string) max(0, (int) $variant['sort_order']), 6, '0', STR_PAD_LEFT),
                str_pad((string) max(0, (int) $variant['product_id']), 10, '0', STR_PAD_LEFT),
            ]))
            ->unique('color_key')
            ->values();

        return $variants->count() > 1 ? $variants : collect();
    }

    /**
     * @param  array{type:string,column?:string,payload_path?:string,value:string,base_name:string,description:string}  $group
     * @return Collection<int, Product>
     */
    private function variantProducts(array $group, string $locale, string $fallbackLocale): Collection
    {
        $hideOutOfStock = app(CatalogFeatureService::class)->hideOutOfStockProducts();

        $query = Product::query()
            ->select(['id', 'code', 'sku', 'is_active', 'payload'])
            ->visibleOnStorefront($hideOutOfStock)
            ->with([
                'translations' => fn ($q) => $q
                    ->select(['id', 'product_id', 'locale', 'slug', 'name', 'description'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'optionValues' => fn ($q) => $q
                    ->select(['id', 'product_id', 'option_value_id', 'parent_option_value_id', 'is_active', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'optionValues.optionValue.option.translations' => fn ($q) => $q
                    ->select(['id', 'option_id', 'locale', 'name'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'optionValues.optionValue.translations' => fn ($q) => $q
                    ->select(['id', 'option_value_id', 'locale', 'name'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'optionValues.parentOptionValue.option.translations' => fn ($q) => $q
                    ->select(['id', 'option_id', 'locale', 'name'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'optionValues.parentOptionValue.translations' => fn ($q) => $q
                    ->select(['id', 'option_value_id', 'locale', 'name'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->orderBy('id')
            ->limit(self::MAX_VARIANT_CANDIDATES);

        if (($group['type'] ?? '') === 'payload') {
            $query->where(function ($candidateQuery) use ($group, $locale, $fallbackLocale): void {
                $candidateQuery->where((string) $group['column'], (string) $group['value']);
                $this->orWhereMatchesTranslationSignature($candidateQuery, $group, $locale, $fallbackLocale);
            });
        } elseif ($group['description'] !== '') {
            $query->where(function ($candidateQuery) use ($group, $locale, $fallbackLocale): void {
                $this->whereMatchesTranslationSignature($candidateQuery, $group, $locale, $fallbackLocale);
            });
        } else {
            $query->whereHas('translations', function ($translationQuery) use ($group, $locale, $fallbackLocale): void {
                $translationQuery
                    ->whereIn('locale', [$locale, $fallbackLocale])
                    ->where('name', (string) $group['value']);
            });
        }

        return $query->get()
            ->filter(fn (Product $candidate): bool => $this->matchesVariantGroup($candidate, $group, $locale, $fallbackLocale))
            ->take(self::MAX_VARIANTS)
            ->values();
    }

    /**
     * @return array{type:string,column?:string,payload_path?:string,value:string,base_name:string,description:string}|null
     */
    private function variantGroup(Product $product, string $locale, string $fallbackLocale): ?array
    {
        $translation = $this->productTranslation($product, $locale, $fallbackLocale);
        $name = trim((string) ($translation?->name ?? ''));
        $description = $this->plainVariantText((string) ($translation?->description ?? ''));
        $baseName = $this->variantBaseName($name);
        $payloadPaths = [
            'color_variant_group' => 'payload->color_variant_group',
            'variant_group' => 'payload->variant_group',
            'source.color_variant_group' => 'payload->source->color_variant_group',
            'source.variant_group' => 'payload->source->variant_group',
            'source.mpn' => 'payload->source->mpn',
            'source.model' => 'payload->source->model',
            'kipos.color_variant_group' => 'payload->kipos->color_variant_group',
            'kipos.variant_group' => 'payload->kipos->variant_group',
        ];

        foreach ($payloadPaths as $path => $column) {
            $value = trim((string) data_get($product->payload, $path, ''));
            if ($value !== '') {
                return [
                    'type' => 'payload',
                    'column' => $column,
                    'payload_path' => $path,
                    'value' => $value,
                    'base_name' => $baseName,
                    'description' => $description,
                ];
            }
        }

        return $name !== ''
            ? [
                'type' => 'translation_name',
                'value' => $name,
                'base_name' => $baseName,
                'description' => $description,
            ]
            : null;
    }

    /**
     * @param  array{base_name:string,description:string}  $group
     */
    private function whereMatchesTranslationSignature(mixed $query, array $group, string $locale, string $fallbackLocale): void
    {
        $query->whereHas('translations', function ($translationQuery) use ($group, $locale, $fallbackLocale): void {
            $translationQuery
                ->whereIn('locale', [$locale, $fallbackLocale])
                ->where('description', 'like', '%'.$group['description'].'%');
        });
    }

    /**
     * @param  array{base_name:string,description:string}  $group
     */
    private function orWhereMatchesTranslationSignature(mixed $query, array $group, string $locale, string $fallbackLocale): void
    {
        if ($group['base_name'] === '' || $group['description'] === '') {
            return;
        }

        $query->orWhere(function ($signatureQuery) use ($group, $locale, $fallbackLocale): void {
            $this->whereMatchesTranslationSignature($signatureQuery, $group, $locale, $fallbackLocale);
        });
    }

    /**
     * @param  array{type:string,payload_path?:string,value:string,base_name:string,description:string}  $group
     */
    private function matchesVariantGroup(Product $candidate, array $group, string $locale, string $fallbackLocale): bool
    {
        if (($group['type'] ?? '') === 'payload') {
            $candidateValue = trim((string) data_get($candidate->payload, (string) ($group['payload_path'] ?? ''), ''));
            if ($candidateValue !== '' && $candidateValue === (string) $group['value']) {
                return true;
            }
        }

        $translation = $this->productTranslation($candidate, $locale, $fallbackLocale);
        $name = trim((string) ($translation?->name ?? ''));
        $description = $this->plainVariantText((string) ($translation?->description ?? ''));

        if ($group['description'] === '') {
            return $name === (string) $group['value'];
        }

        return $this->normalizeVariantText($this->variantBaseName($name)) === $this->normalizeVariantText($group['base_name'])
            && $this->normalizeVariantText($description) === $this->normalizeVariantText($group['description']);
    }

    private function variantBaseName(string $name): string
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($name)) ?: [];
        $baseName = trim((string) ($parts[0] ?? $name));

        return trim((string) preg_replace('/^[a-z]*\d[a-z0-9._-]*\s+/iu', '', $baseName));
    }

    private function normalizeVariantText(string $value): string
    {
        $value = Str::lower(Str::ascii($this->plainVariantText($value)));
        $tokens = preg_split('/[^a-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(
            static fn (string $token): string => strlen($token) > 3
                ? (preg_replace('/[aeo]$/', '', $token) ?: $token)
                : $token,
            $tokens
        ));
    }

    private function plainVariantText(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function colorValueForProduct(Product $product, string $locale, string $fallbackLocale): ?OptionValue
    {
        $product->loadMissing([
            'optionValues.optionValue.option.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            'optionValues.optionValue.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            'optionValues.parentOptionValue.option.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            'optionValues.parentOptionValue.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
        ]);

        foreach ($product->optionValues->where('is_active', true) as $row) {
            $parentValue = $row->parentOptionValue;
            if ($parentValue && $this->isColorOption($parentValue->option)) {
                return $parentValue;
            }

            $value = $row->optionValue;
            if ($value && $this->isColorOption($value->option)) {
                return $value;
            }
        }

        return null;
    }

    private function isColorOption(?Option $option): bool
    {
        if (! $option) {
            return false;
        }

        $code = Str::lower(trim((string) $option->code));
        if (Str::startsWith($code, ['color', 'colour', 'boja'])) {
            return true;
        }

        foreach ($option->translations ?? [] as $translation) {
            $name = $this->normalizeSwatchKey((string) ($translation->name ?? ''));
            if (in_array($name, ['color', 'colour', 'boja'], true)) {
                return true;
            }
        }

        return false;
    }

    private function productTranslation(Product $product, string $locale, string $fallbackLocale): mixed
    {
        $product->loadMissing([
            'translations' => fn ($q) => $q
                ->select(['id', 'product_id', 'locale', 'slug', 'name', 'description'])
                ->whereIn('locale', [$locale, $fallbackLocale]),
        ]);

        return $product->translations->firstWhere('locale', $locale)
            ?? $product->translations->firstWhere('locale', $fallbackLocale)
            ?? $product->translations->first();
    }

    private function valueLabel(OptionValue $value, string $locale, string $fallbackLocale): string
    {
        $translation = $value->translations?->firstWhere('locale', $locale)
            ?? $value->translations?->firstWhere('locale', $fallbackLocale)
            ?? $value->translations?->first();

        return trim((string) ($translation?->name ?? $value->code));
    }

    private function swatchImageUrl(OptionValue $value): ?string
    {
        $path = trim((string) data_get($value->payload, 'swatch_image_path', ''));

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function normalizeSwatchKey(string $value): string
    {
        $normalized = Str::lower(Str::ascii(trim($value)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        return trim($normalized);
    }
}
