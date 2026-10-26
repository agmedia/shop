<?php

namespace App\Services\Integrations\Klaviyo;

use App\Models\Catalog\Product\Product;
use App\Services\Catalog\ActionResolverService;
use App\Services\Pricing\TaxPricingService;
use App\Support\Media\MediaUrl;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class KlaviyoCatalogService
{
    private const LOCALES_BY_COUNTRY = ['DE' => 'de', 'AT' => 'de', 'HR' => 'hr'];

    public function __construct(
        private readonly TaxPricingService $pricing,
        private readonly ActionResolverService $actions,
    ) {}

    public function items(string $locale, string $country, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Provide the public HTTPS shop origin with --base-url.');
        }
        if (! in_array($locale, ['hr', 'en', 'de'], true) || ! in_array($country, ['HR', 'DE', 'AT'], true)) {
            throw new InvalidArgumentException('Unsupported catalog locale or destination.');
        }

        $items = [];
        $products = Product::query()->where('is_active', true)
            ->with(['translations', 'media', 'taxRate', 'categories.translations'])
            ->withCount(['optionValues as active_variant_count' => fn ($query) => $query->where('is_active', true)])
            ->withSum(['optionValues as active_variant_stock' => fn ($query) => $query->where('is_active', true)], 'stock_qty')
            ->orderBy('id')->lazy(200);

        foreach ($products as $product) {
            $translation = $product->translations->firstWhere('locale', $locale);
            $media = $product->getFirstMedia('product_main') ?: $product->getFirstMedia('product_gallery');
            if (! $translation || ! $translation->slug || ! $media) {
                continue;
            }
            // The public image may live on the production server while a local copy is absent.
            $imageUrl = MediaUrl::conversion($media, 'card_720w') ?: $media->getUrl();
            if (! parse_url($imageUrl, PHP_URL_HOST) || parse_url($imageUrl, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST)) {
                $imageUrl = $baseUrl.'/'.ltrim((string) parse_url($imageUrl, PHP_URL_PATH), '/');
            }
            if (parse_url($imageUrl, PHP_URL_SCHEME) !== 'https') {
                continue;
            }
            $action = $this->actions->resolveProductAction($product);
            $storedPrice = $action
                ? $this->actions->applyToPrice((float) $product->base_price, $action)
                : (float) $product->base_price;
            $net = $this->pricing->normalizeNetPrice($storedPrice, $product);
            $inventory = (int) $product->active_variant_count > 0
                ? (int) $product->active_variant_stock
                : (int) $product->stock_qty;

            $items[] = [
                'id' => (string) $product->id,
                'title' => $translation->name ?: $product->code,
                'link' => $baseUrl.'/product/'.rawurlencode($translation->slug).'?lang='.rawurlencode($locale),
                'image_link' => $imageUrl,
                'description' => trim(html_entity_decode(strip_tags((string) ($translation->description ?: $translation->excerpt)))) ?: $translation->name ?: $product->code,
                'price' => $this->pricing->grossFromNet($net, $product, $this->pricing->rateForDestination($product, $country)),
                'currency' => 'EUR',
                'inventory_quantity' => max(0, $inventory),
                'inventory_policy' => 1,
                'categories' => $product->categories->map(fn ($category) => $category->translations->firstWhere('locale', $locale)?->name)->filter()->implode(', '),
                'sku' => (string) $product->sku,
            ];
        }

        return $items;
    }

    public function cachedItems(string $country): array
    {
        $locale = self::LOCALES_BY_COUNTRY[$country]
            ?? throw new InvalidArgumentException('Unsupported catalog destination.');

        return Cache::remember($this->cacheKey($country), now()->addHours($this->refreshHours()),
            fn () => $this->items($locale, $country, $this->baseUrl()));
    }

    public function refresh(): array
    {
        $counts = [];
        foreach (self::LOCALES_BY_COUNTRY as $country => $locale) {
            $items = $this->items($locale, $country, $this->baseUrl());
            Cache::put($this->cacheKey($country), $items, now()->addHours($this->refreshHours()));
            $counts[$country] = count($items);
        }

        return $counts;
    }

    private function cacheKey(string $country): string
    {
        return 'klaviyo.catalog.v1:'.$country.':'.hash('sha256', $this->baseUrl());
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('storefront_compliance.catalog_base_url', 'https://bali.hr'), '/');
    }

    private function refreshHours(): int
    {
        return max(1, (int) config('storefront_compliance.catalog_refresh_hours', 3));
    }
}
