<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Http\Controllers\Front\Concerns\ResolvesGridColumns;
use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Attribute\Attribute;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\User;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Content\ContentBlockResolver;
use App\Services\Front\WishlistService;
use App\Services\Pricing\ProductPricePresentationService;
use App\Services\Settings\SystemSettingsService;
use App\Support\Media\MediaUrl;
use App\Support\ProductMaterialLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    use ResolvesFrontendView;
    use ResolvesGridColumns;

    private ?bool $hideOutOfStockProductsCache = null;

    public function autocomplete(Request $request): JsonResponse
    {
        abort_unless(
            (bool) app(SystemSettingsService::class)->get('store_search_autocomplete_enabled', false),
            404
        );

        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json([
                'query' => $search,
                'total' => 0,
                'items' => [],
                'search_url' => route('shop.index', ['q' => $search]),
            ]);
        }

        $query = Product::query()
            ->visibleOnStorefront($this->hideOutOfStockProducts())
            ->with([
                'taxRate:id,rate,rate_type,is_active',
                'translations' => fn ($translationQuery) => $translationQuery
                    ->select(['id', 'product_id', 'locale', 'slug', 'name', 'excerpt'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'media',
            ]);

        $this->applyProductSearch($query, $locale, $fallbackLocale, $search);

        $total = (clone $query)->count('products.id');
        $viewer = $request->user();
        $pricing = app(ProductPricePresentationService::class);
        $preferWebp = (bool) app(SystemSettingsService::class)->get('store_images_use_webp', false);

        $products = $query
            ->orderByDesc('products.id')
            ->limit(8)
            ->get()
            ->map(function (Product $product) use ($locale, $fallbackLocale, $viewer, $pricing, $preferWebp): array {
                $translation = $product->translations->firstWhere('locale', $locale)
                    ?? $product->translations->firstWhere('locale', $fallbackLocale)
                    ?? $product->translations->first();

                $slug = (string) ($translation?->slug ?? $product->id);
                $price = $pricing->forProduct($product, $viewer);
                $mainMedia = $product->media->firstWhere('collection_name', 'product_main')
                    ?? $product->media->firstWhere('collection_name', 'product_gallery')
                    ?? $product->getFirstMedia('product_main')
                    ?? $product->getFirstMedia('product_gallery');
                $imageUrl = MediaUrl::conversionOrNull($mainMedia, 'card_320w', $preferWebp)
                    ?? MediaUrl::conversionOrNull($mainMedia, 'card_480w', $preferWebp)
                    ?? ($mainMedia ? (string) $mainMedia->getUrl() : null);
                $oldGross = $price['old_gross'] ?? null;

                return [
                    'id' => (int) $product->id,
                    'name' => (string) ($translation?->name ?? $product->code),
                    'sku' => (string) ($product->sku ?: $product->code),
                    'url' => route('products.show', ['slug' => $slug]),
                    'image_url' => $imageUrl,
                    'price' => number_format((float) ($price['current_gross'] ?? 0), 2).' €',
                    'old_price' => $oldGross !== null ? number_format((float) $oldGross, 2).' €' : null,
                    'has_discount' => $oldGross !== null && (float) $oldGross > (float) ($price['current_gross'] ?? 0),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'query' => $search,
            'total' => $total,
            'items' => $products,
            'search_url' => route('shop.index', ['q' => $search]),
        ]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));
        $manufacturerSlug = trim((string) $request->query('manufacturer', ''));
        $sort = (string) $request->query('sort', 'newest');
        $promoOnly = $this->normalizeBooleanFilterValue($request->query('promo_only'));
        [$priceMin, $priceMax] = $this->normalizedPriceRange(
            $request->query('price_min'),
            $request->query('price_max')
        );
        $configuredOptionIds = $this->configuredFilterOptionIds();
        $configuredAttributeGroups = $this->configuredFilterAttributeGroups();
        $optionFilters = $this->catalogOptionFilters($locale, $fallbackLocale, $configuredOptionIds);
        $attributeFilters = $this->catalogAttributeFilters($locale, $fallbackLocale, $configuredAttributeGroups);

        if ($optionFilters === []) {
            $optionFilters = $this->legacySizeFallbackFilter($locale, $fallbackLocale);
        }

        $selectedOptionFilters = [];
        foreach ($optionFilters as $filter) {
            $valueId = (int) $request->query((string) $filter['query_key'], 0);
            $selectedOptionFilters[(string) $filter['query_key']] = $valueId > 0 ? $valueId : null;
        }
        $selectedAttributeFilters = [];
        foreach ($attributeFilters as $filter) {
            $valueId = (int) $request->query((string) $filter['query_key'], 0);
            $selectedAttributeFilters[(string) $filter['query_key']] = $valueId > 0 ? $valueId : null;
        }
        $gridCols = $this->resolveGridCols($request, $this->defaultDesktopGridCols($request));
        $this->queueGridColsCookie($gridCols);

        $query = Product::query()
            ->select([
                'products.id',
                'products.code',
                'products.sku',
                'products.base_price',
                'products.stock_qty',
                'products.tax_rate_id',
                'products.manufacturer_id',
                'products.is_active',
            ])
            ->withApprovedCommentSummary([$locale, $fallbackLocale])
            ->visibleOnStorefront($this->hideOutOfStockProducts())
            ->with([
                'taxRate:id,rate,rate_type,is_active',
                'translations' => fn ($q) => $q
                    ->select(['id', 'product_id', 'locale', 'slug', 'name', 'excerpt'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'categories.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'manufacturer.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'attributes' => ProductMaterialLabel::eagerLoadAttributes($locale, $fallbackLocale),
                'media' => fn ($q) => $q
                    ->whereIn('collection_name', ['product_main', 'product_gallery'])
                    ->orderBy('order_column')
                    ->orderBy('id'),
                'optionValues' => fn ($q) => $q
                    ->select(['id', 'product_id', 'option_value_id', 'parent_option_value_id', 'sku', 'stock_qty', 'is_active', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'optionValue.option' => fn ($optionQuery) => $optionQuery
                            ->select(['id', 'payload']),
                        'optionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                        'parentOptionValue.option' => fn ($optionQuery) => $optionQuery
                            ->select(['id', 'payload']),
                        'parentOptionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                    ]),
            ]);

        $this->applyProductSearch($query, $locale, $fallbackLocale, $search);

        if ($categorySlug !== '') {
            $query->whereHas('categories', function ($categoryQuery) use ($locale, $fallbackLocale, $categorySlug): void {
                $categoryQuery
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->currentlyVisible()
                    ->whereHas('translations', function ($translationQuery) use ($locale, $fallbackLocale, $categorySlug): void {
                        $translationQuery
                            ->where('scope', Category::SCOPE_CATALOG)
                            ->whereIn('locale', [$locale, $fallbackLocale])
                            ->where('slug', $categorySlug);
                    });
            });
        }

        if ($manufacturerSlug !== '') {
            $query->whereHas('manufacturer', function ($manufacturerQuery) use ($locale, $fallbackLocale, $manufacturerSlug): void {
                $manufacturerQuery
                    ->where('is_active', true)
                    ->whereHas('translations', function ($translationQuery) use ($locale, $fallbackLocale, $manufacturerSlug): void {
                        $translationQuery
                            ->whereIn('locale', [$locale, $fallbackLocale])
                            ->where('slug', $manufacturerSlug);
                    });
            });
        }

        foreach ($selectedOptionFilters as $selectedOptionValueId) {
            if ($selectedOptionValueId && $selectedOptionValueId > 0) {
                $this->applyOptionValueFilter($query, $selectedOptionValueId);
            }
        }

        foreach ($attributeFilters as $attributeFilter) {
            $queryKey = (string) $attributeFilter['query_key'];
            $selectedAttributeId = (int) ($selectedAttributeFilters[$queryKey] ?? 0);
            if ($selectedAttributeId <= 0) {
                continue;
            }

            $query->whereHas('attributes', function ($attributeQuery) use ($selectedAttributeId): void {
                $attributeQuery->where('catalog_attributes.id', $selectedAttributeId);
            });
        }

        if ($promoOnly) {
            $this->applyPromotionFilter($query, $request->user());
        }

        $this->applyBasePriceFilter($query, $priceMin, $priceMax);

        match ($sort) {
            'price_low' => $this->applyPriceSort($query, 'asc'),
            'price_high' => $this->applyPriceSort($query, 'desc'),
            'stock_high' => $query->orderByDesc('products.stock_qty')->orderByDesc('products.id'),
            'oldest' => $query->orderBy('products.id'),
            default => $query->orderByDesc('products.id'),
        };

        $products = $query
            ->paginate($this->shopPerPage($request))
            ->withQueryString();

        $categories = $this->cachedCatalogCategories($locale, $fallbackLocale);
        $manufacturers = $this->cachedCatalogManufacturers($locale, $fallbackLocale);
        $response = response()->view($this->frontendView($request, 'shop.index'), [
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'optionFilters' => $this->withSelectedFilters($optionFilters, $selectedOptionFilters),
            'attributeFilters' => $this->withSelectedFilters($attributeFilters, $selectedAttributeFilters),
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'manufacturer' => $manufacturerSlug,
                'price_min' => $priceMin,
                'price_max' => $priceMax,
                'promo_only' => $promoOnly,
                'sort' => $sort,
                'cols' => $gridCols,
            ],
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);

        return $this->withDesktopCacheHeaders($request, $response, 'shop');
    }

    public function categories(Request $request): View
    {
        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $search = trim((string) $request->query('q', ''));

        $categoriesQuery = Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->currentlyVisible()
            ->with(['translations' => fn ($q) => $q
                ->where('scope', Category::SCOPE_CATALOG)
                ->whereIn('locale', [$locale, $fallbackLocale])])
            ->withCount(['products' => fn ($q) => $q->visibleOnStorefront($this->hideOutOfStockProducts())])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search !== '') {
            $categoriesQuery->whereHas('translations', function ($q) use ($locale, $fallbackLocale, $search): void {
                $q->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale])
                    ->where('name', 'like', '%'.$search.'%');
            });
        }

        $categories = $categoriesQuery->paginate(18)->withQueryString();

        return view($this->frontendView($request, 'categories.index'), [
            'categories' => $categories,
            'search' => $search,
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);
    }

    public function showCategory(Request $request, string $slug): Response|RedirectResponse
    {
        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $variant = $this->frontendVariant($request);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = $slug;
        $manufacturerSlug = trim((string) $request->query('manufacturer', ''));
        $sort = (string) $request->query('sort', 'default');
        $promoOnly = $this->normalizeBooleanFilterValue($request->query('promo_only'));
        [$priceMin, $priceMax] = $this->normalizedPriceRange(
            $request->query('price_min'),
            $request->query('price_max')
        );
        $gridCols = $this->resolveGridCols($request, $this->defaultDesktopGridCols($request));
        $this->queueGridColsCookie($gridCols);

        $categoryQuery = Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->currentlyVisible();

        $findCategoryByLocale = static function (Builder $query, string $translationLocale) use ($categorySlug): ?Category {
            return $query
                ->whereHas('translations', function ($translationQuery) use ($translationLocale, $categorySlug): void {
                    $translationQuery
                        ->where('scope', Category::SCOPE_CATALOG)
                        ->where('locale', $translationLocale)
                        ->where('slug', $categorySlug);
                })
                ->first();
        };

        $category = $findCategoryByLocale(clone $categoryQuery, $locale);

        if (! $category && $fallbackLocale !== $locale) {
            $category = $findCategoryByLocale(clone $categoryQuery, $fallbackLocale);
        }

        $category ??= (clone $categoryQuery)
            ->whereHas('translations', function ($q) use ($categorySlug): void {
                $q->where('scope', Category::SCOPE_CATALOG)
                    ->where('slug', $categorySlug);
            })
            ->firstOrFail();

        $category->load([
            'translations' => fn ($q) => $q->where('scope', Category::SCOPE_CATALOG),
        ]);

        $localizedCategorySlug = trim((string) (
            $category->translations->firstWhere('locale', $locale)?->slug
            ?? $category->translations->firstWhere('locale', $fallbackLocale)?->slug
            ?? ''
        ));

        if ($localizedCategorySlug !== '' && $localizedCategorySlug !== $categorySlug) {
            return redirect()->route(
                'categories.show',
                ['slug' => $localizedCategorySlug] + $request->query()
            );
        }

        $categoryTargetRefs = $category->translations
            ->pluck('slug')
            ->push($category->code)
            ->filter(static fn ($ref): bool => trim((string) $ref) !== '')
            ->map(static fn ($ref): string => trim((string) $ref))
            ->unique()
            ->values()
            ->all();

        $showCategoryProducts = $category->catalogPageShowsProducts();
        $showCategoryFilters = $category->catalogPageShowsFilters();

        $categoryTreeIds = $category->descendants()
            ->where('scope', Category::SCOPE_CATALOG)
            ->currentlyVisible()
            ->pluck('id');
        $categoryTreeIds->prepend($category->id);
        $categoryScopeIds = $categoryTreeIds
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $optionFilters = [];
        $attributeFilters = [];

        if ($showCategoryFilters) {
            $configuredOptionIds = $this->configuredFilterOptionIds();
            $configuredAttributeGroups = array_values(array_unique([
                ...$this->configuredFilterAttributeGroups(),
                'sastav',
                'material',
            ]));
            $optionFilters = $this->catalogOptionFilters($locale, $fallbackLocale, $configuredOptionIds, $categoryScopeIds);
            $attributeFilters = $this->catalogAttributeFilters($locale, $fallbackLocale, $configuredAttributeGroups, $categoryScopeIds);

            if ($optionFilters === []) {
                $optionFilters = $this->legacySizeFallbackFilter($locale, $fallbackLocale, $categoryScopeIds);
            }
        }

        $selectedOptionFilters = [];
        foreach ($optionFilters as $filter) {
            $valueId = (int) $request->query((string) $filter['query_key'], 0);
            $selectedOptionFilters[(string) $filter['query_key']] = $valueId > 0 ? $valueId : null;
        }
        $selectedAttributeFilters = [];
        foreach ($attributeFilters as $filter) {
            $valueId = (int) $request->query((string) $filter['query_key'], 0);
            $selectedAttributeFilters[(string) $filter['query_key']] = $valueId > 0 ? $valueId : null;
        }

        if ($showCategoryProducts) {
            $productsQuery = Product::query()
                ->visibleOnStorefront($this->hideOutOfStockProducts())
                ->whereHas('categories', function ($categoryQuery) use ($categoryTreeIds): void {
                    $categoryQuery
                        ->where('scope', Category::SCOPE_CATALOG)
                        ->currentlyVisible()
                        ->whereIn('categories.id', $categoryTreeIds);
                });

            $this->applyProductSearch($productsQuery, $locale, $fallbackLocale, $search);

            if ($manufacturerSlug !== '') {
                $productsQuery->whereHas('manufacturer', function ($manufacturerQuery) use ($locale, $fallbackLocale, $manufacturerSlug): void {
                    $manufacturerQuery
                        ->where('is_active', true)
                        ->whereHas('translations', function ($translationQuery) use ($locale, $fallbackLocale, $manufacturerSlug): void {
                            $translationQuery
                                ->whereIn('locale', [$locale, $fallbackLocale])
                                ->where('slug', $manufacturerSlug);
                        });
                });
            }

            foreach ($selectedOptionFilters as $selectedOptionValueId) {
                if ($selectedOptionValueId && $selectedOptionValueId > 0) {
                    $this->applyOptionValueFilter($productsQuery, $selectedOptionValueId);
                }
            }

            foreach ($attributeFilters as $attributeFilter) {
                $queryKey = (string) $attributeFilter['query_key'];
                $selectedAttributeId = (int) ($selectedAttributeFilters[$queryKey] ?? 0);
                if ($selectedAttributeId <= 0) {
                    continue;
                }

                $productsQuery->whereHas('attributes', function ($attributeQuery) use ($selectedAttributeId): void {
                    $attributeQuery->where('catalog_attributes.id', $selectedAttributeId);
                });
            }

            $promoAvailabilityQuery = clone $productsQuery;
            $this->applyBasePriceFilter($promoAvailabilityQuery, $priceMin, $priceMax);
            $this->applyPromotionFilter($promoAvailabilityQuery, $request->user());
            $promoFilterAvailable = (clone $promoAvailabilityQuery)->exists();

            if ($promoOnly) {
                $this->applyPromotionFilter($productsQuery, $request->user());
            }

            $priceBounds = $this->resolvePriceBounds($productsQuery, $priceMin, $priceMax);

            $this->applyBasePriceFilter($productsQuery, $priceMin, $priceMax);

            $productsQuery
                ->select([
                    'products.id',
                    'products.code',
                    'products.sku',
                    'products.base_price',
                    'products.stock_qty',
                    'products.tax_rate_id',
                    'products.manufacturer_id',
                    'products.is_active',
                ])
                ->withApprovedCommentSummary([$locale, $fallbackLocale])
                ->with([
                    'taxRate:id,rate,rate_type,is_active',
                    'translations' => fn ($q) => $q
                        ->select(['id', 'product_id', 'locale', 'slug', 'name', 'excerpt'])
                        ->whereIn('locale', [$locale, $fallbackLocale]),
                    'categories.translations' => fn ($q) => $q
                        ->where('scope', Category::SCOPE_CATALOG)
                        ->whereIn('locale', [$locale, $fallbackLocale]),
                    'manufacturer.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                    'attributes' => ProductMaterialLabel::eagerLoadAttributes($locale, $fallbackLocale),
                    'media' => fn ($q) => $q
                        ->whereIn('collection_name', ['product_main', 'product_gallery'])
                        ->orderBy('order_column')
                        ->orderBy('id'),
                    'optionValues' => fn ($q) => $q
                        ->select(['id', 'product_id', 'option_value_id', 'parent_option_value_id', 'sku', 'stock_qty', 'is_active', 'sort_order'])
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->with([
                            'optionValue.option' => fn ($optionQuery) => $optionQuery
                                ->select(['id', 'payload']),
                            'optionValue.translations' => fn ($tq) => $tq
                                ->select(['id', 'option_value_id', 'locale', 'name'])
                                ->whereIn('locale', [$locale, $fallbackLocale]),
                            'parentOptionValue.option' => fn ($optionQuery) => $optionQuery
                                ->select(['id', 'payload']),
                            'parentOptionValue.translations' => fn ($tq) => $tq
                                ->select(['id', 'option_value_id', 'locale', 'name'])
                                ->whereIn('locale', [$locale, $fallbackLocale]),
                        ]),
                ]);

            match ($sort) {
                'newest' => $productsQuery->orderByDesc('products.id'),
                'price_low' => $this->applyPriceSort($productsQuery, 'asc'),
                'price_high' => $this->applyPriceSort($productsQuery, 'desc'),
                'stock_high' => $productsQuery->orderByDesc('products.stock_qty')->orderByDesc('products.id'),
                'oldest' => $productsQuery->orderBy('products.id'),
                default => $this->applyCategoryDefaultProductSort($productsQuery, $categoryScopeIds, $locale, $fallbackLocale),
            };

            $products = $productsQuery
                ->paginate($this->shopPerPage($request))
                ->withQueryString();
        } else {
            $priceBounds = $this->resolvePriceBounds(null, $priceMin, $priceMax);
            $promoFilterAvailable = false;
            $products = (new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $this->shopPerPage($request),
                currentPage: max(1, (int) $request->query('page', 1)),
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            ))->withQueryString();
        }

        $categories = $showCategoryFilters
            ? $this->cachedCatalogCategories($locale, $fallbackLocale)
            : collect();
        $manufacturers = $showCategoryFilters
            ? $this->cachedCatalogManufacturers($locale, $fallbackLocale)
            : collect();

        $subcategories = $showCategoryFilters
            ? $category->children()
                ->where('scope', Category::SCOPE_CATALOG)
                ->currentlyVisible()
                ->with(['translations' => fn ($q) => $q
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->withCount(['products' => fn ($q) => $q->visibleOnStorefront($this->hideOutOfStockProducts())])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
        $subcategories->transform(function (Category $subCategory): Category {
            $subTreeIds = Category::query()
                ->descendantsAndSelf($subCategory->id)
                ->where('scope', Category::SCOPE_CATALOG)
                ->filter(fn (Category $category): bool => $category->isCurrentlyVisible())
                ->pluck('id');

            if ($subTreeIds->isEmpty()) {
                $subCategory->setAttribute('products_count', 0);

                return $subCategory;
            }

            $recursiveCount = Product::query()
                ->visibleOnStorefront($this->hideOutOfStockProducts())
                ->whereHas('categories', function ($categoryQuery) use ($subTreeIds): void {
                    $categoryQuery
                        ->where('scope', Category::SCOPE_CATALOG)
                        ->currentlyVisible()
                        ->whereIn('categories.id', $subTreeIds);
                })
                ->distinct('products.id')
                ->count('products.id');

            $subCategory->setAttribute('products_count', $recursiveCount);

            return $subCategory;
        });

        $breadcrumbCategories = $category->ancestors()
            ->where('scope', Category::SCOPE_CATALOG)
            ->currentlyVisible()
            ->with(['translations' => fn ($q) => $q
                ->where('scope', Category::SCOPE_CATALOG)
                ->whereIn('locale', [$locale, $fallbackLocale])])
            ->orderBy('_lft')
            ->get()
            ->push($category);

        $topBlocks = app(ContentBlockResolver::class)->forPlacement(
            placement: 'category.top',
            locale: $locale,
            targetType: 'category',
            targetRef: $categoryTargetRefs,
            frontendVariant: $variant
        );

        $bottomBlocks = app(ContentBlockResolver::class)->forPlacement(
            placement: 'category.bottom',
            locale: $locale,
            targetType: 'category',
            targetRef: $categoryTargetRefs,
            frontendVariant: $variant
        );

        $response = response()->view($this->frontendView($request, 'categories.show'), [
            'category' => $category,
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'optionFilters' => $this->withSelectedFilters($optionFilters, $selectedOptionFilters),
            'attributeFilters' => $this->withSelectedFilters($attributeFilters, $selectedAttributeFilters),
            'subcategories' => $subcategories,
            'breadcrumbCategories' => $breadcrumbCategories,
            'topBlocks' => $topBlocks,
            'bottomBlocks' => $bottomBlocks,
            'showCategoryFilters' => $showCategoryFilters,
            'showCategoryProducts' => $showCategoryProducts,
            'filters' => [
                'q' => $search,
                'manufacturer' => $manufacturerSlug,
                'price_min' => $priceMin,
                'price_max' => $priceMax,
                'promo_only' => $promoOnly,
                'sort' => $sort,
                'cols' => $gridCols,
            ],
            'priceBounds' => $priceBounds,
            'promoFilterAvailable' => $promoFilterAvailable ?? false,
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);

        return $this->withDesktopCacheHeaders($request, $response, 'category:'.$categorySlug);
    }

    private function cachedCatalogCategories(string $locale, string $fallbackLocale)
    {
        $hideOutOfStock = $this->hideOutOfStockProducts();
        $cacheKey = sprintf('front:catalog:categories:%s:%s:%s', $locale, $fallbackLocale, $hideOutOfStock ? 'hide-oos' : 'all-stock');

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale, $hideOutOfStock) {
            return Category::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('scope', Category::SCOPE_CATALOG)
                ->currentlyVisible()
                ->with(['translations' => fn ($q) => $q
                    ->select(['id', 'category_id', 'scope', 'locale', 'name', 'slug'])
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->withCount(['products' => fn ($q) => $q->visibleOnStorefront($hideOutOfStock)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    private function cachedCatalogManufacturers(string $locale, string $fallbackLocale)
    {
        $hideOutOfStock = $this->hideOutOfStockProducts();
        $cacheKey = sprintf('front:catalog:manufacturers:%s:%s:%s', $locale, $fallbackLocale, $hideOutOfStock ? 'hide-oos' : 'all-stock');

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale, $hideOutOfStock) {
            return Manufacturer::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q
                    ->select(['id', 'manufacturer_id', 'locale', 'name', 'slug'])
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->withCount(['products' => fn ($q) => $q->visibleOnStorefront($hideOutOfStock)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    private function hideOutOfStockProducts(): bool
    {
        if ($this->hideOutOfStockProductsCache === null) {
            $this->hideOutOfStockProductsCache = app(CatalogFeatureService::class)->hideOutOfStockProducts();
        }

        return $this->hideOutOfStockProductsCache;
    }

    private function applyProductStockVisibilityToBaseQuery(QueryBuilder $query, string $productTable = 'products'): void
    {
        if (! $this->hideOutOfStockProducts()) {
            return;
        }

        $query->where(function (QueryBuilder $stockQuery) use ($productTable): void {
            $stockQuery
                ->where($productTable.'.stock_qty', '>', 0)
                ->orWhereExists(function (QueryBuilder $existsQuery) use ($productTable): void {
                    $existsQuery
                        ->selectRaw('1')
                        ->from('catalog_product_option_values as storefront_stock_options')
                        ->whereColumn('storefront_stock_options.product_id', $productTable.'.id')
                        ->where('storefront_stock_options.is_active', true)
                        ->where('storefront_stock_options.stock_qty', '>', 0);
                });
        });
    }

    private function applyCategoryScheduleToBaseQuery(QueryBuilder $query, string $categoryTable = 'categories'): void
    {
        $query
            ->where(function (QueryBuilder $scheduleQuery) use ($categoryTable): void {
                $scheduleQuery
                    ->whereNull($categoryTable.'.starts_at')
                    ->orWhere($categoryTable.'.starts_at', '<=', now());
            })
            ->where(function (QueryBuilder $scheduleQuery) use ($categoryTable): void {
                $scheduleQuery
                    ->whereNull($categoryTable.'.ends_at')
                    ->orWhere($categoryTable.'.ends_at', '>=', now());
            });
    }

    /**
     * @return array<int, int>
     */
    private function configuredFilterOptionIds(): array
    {
        $raw = app(SystemSettingsService::class)->get('store_product_filter_option_ids', []);

        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function configuredFilterAttributeGroups(): array
    {
        $raw = app(SystemSettingsService::class)->get('store_product_filter_attribute_group_codes', []);

        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($code): string => trim((string) $code))
            ->filter(fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $optionIds
     * @return array<int, array{label:string,query_key:string,kind:string,values:array<int, array{id:int,label:string,count:int}>}>
     */
    private function catalogOptionFilters(string $locale, string $fallbackLocale, array $optionIds, ?array $categoryScopeIds = null): array
    {
        if ($optionIds === []) {
            return [];
        }

        $categoryKey = $categoryScopeIds !== null && $categoryScopeIds !== []
            ? sha1(implode(',', array_map(static fn ($id): int => (int) $id, $categoryScopeIds)))
            : 'all';
        $hideOutOfStock = $this->hideOutOfStockProducts();
        $cacheKey = sprintf('front:catalog:option-filters:v5:%s:%s:%s:%s:%s', $locale, $fallbackLocale, sha1(implode(',', $optionIds)), $categoryKey, $hideOutOfStock ? 'hide-oos' : 'all-stock');

        $rows = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($locale, $fallbackLocale, $optionIds, $categoryScopeIds, $hideOutOfStock): array {
            $scopeIds = collect($categoryScopeIds ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $valueCounts = $this->catalogOptionValueProductCounts($optionIds, $scopeIds);
            $options = Option::query()
                ->whereIn('id', $optionIds)
                ->where('is_active', true)
                ->with([
                    'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                    'values' => fn ($valueQuery) => $valueQuery
                        ->where('is_active', true)
                        ->whereHas('productOptionValues', function ($productOptionQuery) use ($scopeIds, $hideOutOfStock): void {
                            $productOptionQuery
                                ->where('is_active', true)
                                ->whereHas('product', function ($productQuery) use ($scopeIds, $hideOutOfStock): void {
                                    $productQuery->visibleOnStorefront($hideOutOfStock);
                                    if ($scopeIds !== []) {
                                        $productQuery->whereHas('categories', function ($categoryQuery) use ($scopeIds): void {
                                            $categoryQuery
                                                ->where('scope', Category::SCOPE_CATALOG)
                                                ->currentlyVisible()
                                                ->whereIn('categories.id', $scopeIds);
                                        });
                                    }
                                });
                        })
                        ->with(['translations' => fn ($tq) => $tq->whereIn('locale', [$locale, $fallbackLocale])]),
                ])
                ->get()
                ->keyBy('id');

            $filters = [];
            foreach ($optionIds as $optionId) {
                /** @var Option|null $option */
                $option = $options->get($optionId);
                if (!$option) {
                    continue;
                }

                $optionTranslation = $option->translations->firstWhere('locale', $locale)
                    ?? $option->translations->firstWhere('locale', $fallbackLocale)
                    ?? $option->translations->first();
                $label = trim((string) ($optionTranslation?->name ?? $option->code));
                if ($label === '') {
                    $label = __('ui.shop.filters.size');
                }

                $kind = $this->catalogOptionFilterKind($option);
                $values = $option->values
                    ->map(function (OptionValue $value) use ($locale, $fallbackLocale, $valueCounts): array {
                        $valueTranslation = $value->translations->firstWhere('locale', $locale)
                            ?? $value->translations->firstWhere('locale', $fallbackLocale)
                            ?? $value->translations->first();
                        $valueLabel = trim((string) ($valueTranslation?->name ?? $value->code));

                        return [
                            'id' => (int) $value->id,
                            'label' => $valueLabel !== '' ? $valueLabel : (string) $value->code,
                            'count' => (int) ($valueCounts[(int) $value->id] ?? 0),
                            'sort_rank' => $this->catalogColorSortRank((string) $value->code, $valueLabel),
                            'swatch_image_url' => $this->catalogOptionValueSwatchImageUrl($value),
                        ];
                    })
                    ->when($kind === 'color', fn ($values) => $values->sortBy([
                        ['sort_rank', 'asc'],
                        ['label', 'asc'],
                        ['id', 'asc'],
                    ]))
                    ->map(function (array $value): array {
                        unset($value['sort_rank']);

                        return $value;
                    })
                    ->values()
                    ->all();

                if ($values === []) {
                    continue;
                }

                $filters[] = [
                    'label' => $label,
                    'query_key' => 'opt_'.$option->id,
                    'kind' => $kind,
                    'values' => $values,
                ];
            }

            return $filters;
        });

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<int, string>  $groupCodes
     * @return array<int, array{label:string,query_key:string,values:array<int, array{id:int,label:string}>}>
     */
    private function catalogAttributeFilters(string $locale, string $fallbackLocale, array $groupCodes, ?array $categoryScopeIds = null): array
    {
        if ($groupCodes === []) {
            return [];
        }

        $categoryKey = $categoryScopeIds !== null && $categoryScopeIds !== []
            ? sha1(implode(',', array_map(static fn ($id): int => (int) $id, $categoryScopeIds)))
            : 'all';
        $hideOutOfStock = $this->hideOutOfStockProducts();
        $cacheKey = sprintf('front:catalog:attribute-filters:v2:%s:%s:%s:%s:%s', $locale, $fallbackLocale, sha1(implode(',', $groupCodes)), $categoryKey, $hideOutOfStock ? 'hide-oos' : 'all-stock');

        $rows = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($locale, $fallbackLocale, $groupCodes, $categoryScopeIds, $hideOutOfStock): array {
            $scopeIds = collect($categoryScopeIds ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $attributes = Attribute::query()
                ->whereIn('group_code', $groupCodes)
                ->where('is_active', true)
                ->whereHas('products', function ($productQuery) use ($scopeIds, $hideOutOfStock): void {
                    $productQuery->visibleOnStorefront($hideOutOfStock);
                    if ($scopeIds !== []) {
                        $productQuery->whereHas('categories', function ($categoryQuery) use ($scopeIds): void {
                            $categoryQuery
                                ->where('scope', Category::SCOPE_CATALOG)
                                ->currentlyVisible()
                                ->whereIn('categories.id', $scopeIds);
                        });
                    }
                })
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderBy('group_code')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('group_code');

            $filters = [];
            foreach ($groupCodes as $groupCode) {
                $groupRows = $attributes->get($groupCode);
                if (!$groupRows || $groupRows->isEmpty()) {
                    continue;
                }

                $firstTranslation = $groupRows
                    ->flatMap(fn (Attribute $attribute) => $attribute->translations)
                    ->firstWhere('locale', $locale)
                    ?? $groupRows->flatMap(fn (Attribute $attribute) => $attribute->translations)->firstWhere('locale', $fallbackLocale)
                    ?? $groupRows->flatMap(fn (Attribute $attribute) => $attribute->translations)->first();

                $label = match ($groupCode) {
                    'sastav' => in_array(strtolower($locale), ['hr', 'hr-hr'], true) ? 'Sastav' : 'Composition',
                    'material' => in_array(strtolower($locale), ['hr', 'hr-hr'], true) ? 'Sastav' : 'Composition',
                    default => trim((string) ($firstTranslation?->group_name ?? '')),
                };

                if ($label === '') {
                    $label = ucfirst(str_replace('_', ' ', $groupCode));
                }

                $values = $groupRows
                    ->map(function (Attribute $attribute) use ($locale, $fallbackLocale): array {
                        $translation = $attribute->translations->firstWhere('locale', $locale)
                            ?? $attribute->translations->firstWhere('locale', $fallbackLocale)
                            ?? $attribute->translations->first();
                        $valueLabel = trim((string) ($translation?->name ?? $attribute->code));

                        return [
                            'id' => (int) $attribute->id,
                            'label' => $valueLabel !== '' ? $valueLabel : (string) $attribute->code,
                        ];
                    })
                    ->values()
                    ->all();

                if ($values === []) {
                    continue;
                }

                $filters[] = [
                    'label' => $label,
                    'query_key' => 'attr_'.Str::slug($groupCode, '_'),
                    'values' => $values,
                ];
            }

            return $filters;
        });

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{label:string,query_key:string,values:array<int, array{id:int,label:string}>}>
     */
    private function legacySizeFallbackFilter(string $locale, string $fallbackLocale, ?array $categoryScopeIds = null): array
    {
        $sizes = $this->cachedCatalogSizes($locale, $fallbackLocale, $categoryScopeIds);

        $values = $sizes
            ->map(function (OptionValue $size) use ($locale, $fallbackLocale): array {
                $translation = $size->translations->firstWhere('locale', $locale)
                    ?? $size->translations->firstWhere('locale', $fallbackLocale)
                    ?? $size->translations->first();

                return [
                    'id' => (int) $size->id,
                    'label' => trim((string) ($translation?->name ?? $size->code)),
                ];
            })
            ->values()
            ->all();

        if ($values === []) {
            return [];
        }

        return [[
            'label' => __('ui.shop.filters.size'),
            'query_key' => 'size',
            'kind' => 'default',
            'values' => $values,
        ]];
    }

    private function applyOptionValueFilter($query, int $optionValueId): void
    {
        $query->whereHas('optionValues', function ($optionQuery) use ($optionValueId): void {
            $optionQuery
                ->where('is_active', true)
                ->where(function ($sizeQuery) use ($optionValueId): void {
                    $sizeQuery
                        ->where('option_value_id', $optionValueId)
                        ->orWhere('parent_option_value_id', $optionValueId);
                });
        });
    }

    /**
     * @param  array<int, array{label:string,query_key:string,kind?:string,values:array<int, array{id:int,label:string,count?:int}>}>  $filters
     * @param  array<string, int|null>  $selectedMap
     * @return array<int, array{label:string,query_key:string,kind:string,selected:int|null,values:array<int, array{id:int,label:string,count?:int}>}>
     */
    private function withSelectedFilters(array $filters, array $selectedMap): array
    {
        return array_map(function (array $filter) use ($selectedMap): array {
            $queryKey = (string) $filter['query_key'];
            $selected = (int) ($selectedMap[$queryKey] ?? 0);

            return [
                'label' => (string) $filter['label'],
                'query_key' => $queryKey,
                'kind' => (string) ($filter['kind'] ?? 'default'),
                'selected' => $selected > 0 ? $selected : null,
                'values' => $filter['values'],
            ];
        }, $filters);
    }

    /**
     * @param  array<int, int>  $optionIds
     * @param  array<int, int>  $scopeIds
     * @return array<int, int>
     */
    private function catalogOptionValueProductCounts(array $optionIds, array $scopeIds = []): array
    {
        if ($optionIds === []) {
            return [];
        }

        $query = DB::table('catalog_option_values as option_values')
            ->join('catalog_product_option_values as product_option_values', function ($join): void {
                $join->on('product_option_values.option_value_id', '=', 'option_values.id')
                    ->where('product_option_values.is_active', true);
            })
            ->join('products', function ($join): void {
                $join->on('products.id', '=', 'product_option_values.product_id')
                    ->where('products.is_active', true);
            })
            ->whereIn('option_values.option_id', $optionIds)
            ->where('option_values.is_active', true);

        $this->applyProductStockVisibilityToBaseQuery($query);

        if ($scopeIds !== []) {
            $query
                ->join('category_product', 'category_product.product_id', '=', 'products.id')
                ->join('categories', 'categories.id', '=', 'category_product.category_id')
                ->where('categories.scope', Category::SCOPE_CATALOG)
                ->where('categories.is_active', true)
                ->whereIn('categories.id', $scopeIds);
            $this->applyCategoryScheduleToBaseQuery($query);
        }

        return $query
            ->selectRaw('option_values.id as option_value_id, COUNT(DISTINCT products.id) as product_count')
            ->groupBy('option_values.id')
            ->pluck('product_count', 'option_value_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function catalogOptionFilterKind(Option $option): string
    {
        $code = Str::lower(trim((string) $option->code));

        return Str::startsWith($code, ['color', 'colour', 'boja'])
            ? 'color'
            : 'default';
    }

    private function catalogOptionValueSwatchImageUrl(OptionValue $value): ?string
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

    private function cachedCatalogSizes(string $locale, string $fallbackLocale, ?array $categoryScopeIds = null)
    {
        $categoryKey = $categoryScopeIds !== null && $categoryScopeIds !== []
            ? sha1(implode(',', array_map(static fn ($id): int => (int) $id, $categoryScopeIds)))
            : 'all';
        $hideOutOfStock = $this->hideOutOfStockProducts();
        $cacheKey = sprintf('front:catalog:sizes:v2:%s:%s:%s:%s', $locale, $fallbackLocale, $categoryKey, $hideOutOfStock ? 'hide-oos' : 'all-stock');

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale, $categoryScopeIds, $hideOutOfStock) {
            $scopeIds = collect($categoryScopeIds ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            return OptionValue::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('is_active', true)
                ->whereHas('productOptionValues', function ($q) use ($scopeIds, $hideOutOfStock): void {
                    $q->where('is_active', true)
                        ->whereHas('product', function ($productQuery) use ($scopeIds, $hideOutOfStock): void {
                            $productQuery->visibleOnStorefront($hideOutOfStock);
                            if ($scopeIds !== []) {
                                $productQuery->whereHas('categories', function ($categoryQuery) use ($scopeIds): void {
                                    $categoryQuery
                                        ->where('scope', Category::SCOPE_CATALOG)
                                        ->currentlyVisible()
                                        ->whereIn('categories.id', $scopeIds);
                                });
                            }
                        });
                })
                ->with(['translations' => fn ($q) => $q
                    ->select(['id', 'option_value_id', 'locale', 'name'])
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    private function productPerPage(Request $request, bool $manufacturerView): int
    {
        $variant = (string) $request->attributes->get('frontend_variant', 'desktop');
        $settings = app(SystemSettingsService::class);

        if ($manufacturerView) {
            return $settings->getInt(
                $variant === 'mobile'
                    ? 'front_manufacturer_products_per_page_mobile'
                    : 'front_manufacturer_products_per_page_desktop',
                (int) config(
                    $variant === 'mobile'
                        ? 'admin_ui.pagination.front_manufacturer_products_per_page_mobile'
                        : 'admin_ui.pagination.front_manufacturer_products_per_page_desktop',
                    12
                ),
                4,
                120
            );
        }

        return $settings->getInt(
            $variant === 'mobile'
                ? 'front_category_products_per_page_mobile'
                : 'front_category_products_per_page_desktop',
            (int) config(
                $variant === 'mobile'
                    ? 'admin_ui.pagination.front_category_products_per_page_mobile'
                    : 'admin_ui.pagination.front_category_products_per_page_desktop',
                12
            ),
            4,
            120
        );
    }

    private function shopPerPage(Request $request): int
    {
        return $this->productPerPage($request, false);
    }

    private function redirectWithoutCols(Request $request): RedirectResponse
    {
        $query = $request->query();
        unset($query['cols']);
        $target = $request->url();
        if ($query !== []) {
            $target .= '?'.http_build_query($query);
        }

        return redirect()->to($target);
    }

    private function applyProductSearch(Builder $query, string $locale, string $fallbackLocale, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($locale, $fallbackLocale, $search): void {
            $searchQuery
                ->where('sku', 'like', '%'.$search.'%')
                ->orWhereHas('optionValues', function (Builder $optionValueQuery) use ($search): void {
                    $optionValueQuery
                        ->where('is_active', true)
                        ->where('sku', 'like', '%'.$search.'%');
                })
                ->orWhereHas('translations', function ($translationQuery) use ($locale, $fallbackLocale, $search): void {
                    $translationQuery
                        ->whereIn('locale', [$locale, $fallbackLocale])
                        ->where(function ($textQuery) use ($search): void {
                            $textQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('excerpt', 'like', '%'.$search.'%')
                                ->orWhere('description', 'like', '%'.$search.'%');
                        });
                });
        });
    }

    /**
     * @return array{0:?float,1:?float}
     */
    private function normalizedPriceRange(mixed $priceMinInput, mixed $priceMaxInput): array
    {
        $priceMin = $this->normalizePriceFilterValue($priceMinInput);
        $priceMax = $this->normalizePriceFilterValue($priceMaxInput);

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        return [$priceMin, $priceMax];
    }

    private function normalizePriceFilterValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return max(0.0, round((float) $value, 2));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        if (! is_numeric($normalized)) {
            return null;
        }

        return max(0.0, round((float) $normalized, 2));
    }

    private function normalizeBooleanFilterValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function applyBasePriceFilter(Builder $query, ?float $priceMin, ?float $priceMax): void
    {
        if ($priceMin === null && $priceMax === null) {
            return;
        }

        $defaultRate = $this->defaultCatalogTaxRate();
        if ($this->usesStoredPriceColumn($defaultRate)) {
            if ($priceMin !== null) {
                $query->where('products.base_price', '>=', $priceMin);
            }

            if ($priceMax !== null) {
                $query->where('products.base_price', '<=', $priceMax);
            }

            return;
        }

        $expression = $this->displayedPriceSqlExpression($query);

        if ($priceMin !== null) {
            $query->whereRaw($expression.' >= ?', [$priceMin]);
        }

        if ($priceMax !== null) {
            $query->whereRaw($expression.' <= ?', [$priceMax]);
        }
    }

    private function applyPriceSort(Builder $query, string $direction = 'asc'): void
    {
        $defaultRate = $this->defaultCatalogTaxRate();
        if ($this->usesStoredPriceColumn($defaultRate)) {
            $query->orderBy('products.base_price', $direction === 'desc' ? 'desc' : 'asc');

            return;
        }

        $expression = $this->displayedPriceSqlExpression($query);
        $query->orderByRaw($expression.' '.($direction === 'desc' ? 'DESC' : 'ASC'));
    }

    /**
     * @param  array<int, int>  $categoryScopeIds
     */
    private function applyCategoryDefaultProductSort(Builder $query, array $categoryScopeIds, string $locale, string $fallbackLocale): void
    {
        $scopeIds = collect($categoryScopeIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($scopeIds !== []) {
            $categorySortSubquery = DB::table('category_product')
                ->selectRaw('product_id, MIN(sort_order) as category_sort_order')
                ->whereIn('category_id', $scopeIds)
                ->groupBy('product_id');

            $query->leftJoinSub($categorySortSubquery, 'category_product_sort', function ($join): void {
                $join->on('category_product_sort.product_id', '=', 'products.id');
            });
        }

        $colorSortSubquery = DB::table('catalog_product_option_values as product_colors')
            ->join('catalog_option_values as color_values', function ($join): void {
                $join->on('color_values.id', '=', 'product_colors.option_value_id')
                    ->orOn('color_values.id', '=', 'product_colors.parent_option_value_id');
            })
            ->join('catalog_options as color_options', 'color_options.id', '=', 'color_values.option_id')
            ->leftJoin('catalog_option_translations as color_option_translations', function ($join) use ($locale, $fallbackLocale): void {
                $join->on('color_option_translations.option_id', '=', 'color_options.id')
                    ->whereIn('color_option_translations.locale', [$locale, $fallbackLocale]);
            })
            ->leftJoin('catalog_option_value_translations as color_value_translations', function ($join) use ($locale, $fallbackLocale): void {
                $join->on('color_value_translations.option_value_id', '=', 'color_values.id')
                    ->whereIn('color_value_translations.locale', [$locale, $fallbackLocale]);
            })
            ->where('product_colors.is_active', true)
            ->where('color_values.is_active', true)
            ->where('color_options.is_active', true)
            ->where(function ($optionQuery): void {
                $optionText = $this->catalogNormalizedTextSql('color_options.code', 'color_option_translations.name');

                $optionQuery
                    ->whereRaw($optionText." LIKE 'color%'")
                    ->orWhereRaw($optionText." LIKE '% color%'")
                    ->orWhereRaw($optionText." LIKE 'colour%'")
                    ->orWhereRaw($optionText." LIKE '% colour%'")
                    ->orWhereRaw($optionText." LIKE 'boja%'")
                    ->orWhereRaw($optionText." LIKE '% boja%'");
            })
            ->selectRaw('product_colors.product_id, MIN('.$this->catalogColorSortCaseSql('color_values.code', 'color_value_translations.name').') as color_sort_order')
            ->groupBy('product_colors.product_id');

        $query
            ->leftJoinSub($colorSortSubquery, 'product_color_sort', function ($join): void {
                $join->on('product_color_sort.product_id', '=', 'products.id');
            })
            ->orderByRaw('COALESCE(product_color_sort.color_sort_order, 999) ASC');

        if ($scopeIds !== []) {
            $query->orderByRaw('COALESCE(category_product_sort.category_sort_order, 999999) ASC');
        }

        $query->orderByDesc('products.id');
    }

    private function catalogColorSortCaseSql(string $codeColumn, string $labelColumn): string
    {
        $text = $this->catalogNormalizedTextSql($codeColumn, $labelColumn);

        return "CASE
            WHEN {$text} LIKE '%karirano%' OR {$text} LIKE '%geometric%' OR {$text} LIKE '%squares%' OR {$text} LIKE '%web%' OR {$text} LIKE '%kokos%' OR {$text} LIKE '%flowers%' OR {$text} LIKE '%butterfly%' OR {$text} LIKE '%footprint%' OR {$text} LIKE '%roses%' OR {$text} LIKE '%stars%' OR {$text} LIKE '%uzor%' OR {$text} LIKE '%pattern%' THEN 70
            WHEN {$text} LIKE '%bijel%' OR {$text} LIKE '%white%' THEN 10
            WHEN {$text} LIKE '%boja-koze%' OR {$text} LIKE '%boja koze%' OR {$text} LIKE '%beige%' OR {$text} LIKE '%bez%' OR {$text} LIKE '%nude%' OR {$text} LIKE '%skin%' THEN 20
            WHEN {$text} LIKE '%siv%' OR {$text} LIKE '%gray%' OR {$text} LIKE '%grey%' THEN 30
            WHEN {$text} LIKE '%crven%' OR {$text} LIKE '%red%' THEN 40
            WHEN {$text} LIKE '%plav%' OR {$text} LIKE '%blue%' OR {$text} LIKE '%navy%' THEN 50
            WHEN {$text} LIKE '%crn%' OR {$text} LIKE '%black%' THEN 60
            ELSE 70
        END";
    }

    private function catalogNormalizedTextSql(string $codeColumn, string $labelColumn): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LOWER(REPLACE(REPLACE(REPLACE(({$codeColumn} || ' ' || COALESCE({$labelColumn}, '')), '_', '-'), 'ž', 'z'), 'Ž', 'z'))";
        }

        return "LOWER(REPLACE(REPLACE(REPLACE(CONCAT_WS(' ', {$codeColumn}, COALESCE({$labelColumn}, '')), '_', '-'), 'ž', 'z'), 'Ž', 'z'))";
    }

    private function catalogColorSortRank(string $code, string $label = ''): int
    {
        $text = Str::of($code.' '.$label)
            ->ascii()
            ->lower()
            ->replace('_', '-')
            ->value();

        if (Str::contains($text, ['karirano', 'geometric', 'squares', 'web', 'kokos', 'flowers', 'butterfly', 'footprint', 'roses', 'stars', 'uzor', 'pattern'])) {
            return 70;
        }

        if (Str::contains($text, ['bijel', 'white'])) {
            return 10;
        }

        if (Str::contains($text, ['boja-koze', 'boja koze', 'beige', 'bez', 'nude', 'skin'])) {
            return 20;
        }

        if (Str::contains($text, ['siv', 'gray', 'grey'])) {
            return 30;
        }

        if (Str::contains($text, ['crven', 'red'])) {
            return 40;
        }

        if (Str::contains($text, ['plav', 'blue', 'navy'])) {
            return 50;
        }

        if (Str::contains($text, ['crn', 'black'])) {
            return 60;
        }

        return 70;
    }

    private function applyPromotionFilter(Builder $query, ?User $user): void
    {
        $promotionQuery = CatalogAction::query()
            ->selectRaw('1')
            ->active()
            ->where('scope', CatalogAction::SCOPE_PRODUCT)
            ->whereIn('type', [CatalogAction::TYPE_PERCENTAGE, CatalogAction::TYPE_FIXED])
            ->where('discount_value', '>', 0)
            ->availableForAudience($user)
            ->where(function (Builder $couponQuery): void {
                $couponQuery
                    ->whereNull('coupon_code')
                    ->orWhere('coupon_code', '');
            })
            ->where(function (Builder $matchQuery): void {
                $matchQuery
                    ->where('target_type', CatalogAction::TARGET_ALL)
                    ->orWhere(function (Builder $productActionQuery): void {
                        $productActionQuery
                            ->where('target_type', CatalogAction::TARGET_PRODUCT)
                            ->whereExists(function ($targetQuery): void {
                                $targetQuery
                                    ->selectRaw('1')
                                    ->from('catalog_action_targets')
                                    ->whereColumn('catalog_action_targets.action_id', 'catalog_actions.id')
                                    ->where('catalog_action_targets.target_type', CatalogAction::TARGET_PRODUCT)
                                    ->whereColumn('catalog_action_targets.target_id', 'products.id');
                            });
                    })
                    ->orWhere(function (Builder $categoryActionQuery): void {
                        $categoryActionQuery
                            ->where('target_type', CatalogAction::TARGET_CATEGORY)
                            ->whereExists(function ($targetQuery): void {
                                $targetQuery
                                    ->selectRaw('1')
                                    ->from('catalog_action_targets')
                                    ->join('category_product', 'category_product.category_id', '=', 'catalog_action_targets.target_id')
                                    ->whereColumn('catalog_action_targets.action_id', 'catalog_actions.id')
                                    ->where('catalog_action_targets.target_type', CatalogAction::TARGET_CATEGORY)
                                    ->whereColumn('category_product.product_id', 'products.id');
                            });
                    })
                    ->orWhere(function (Builder $manufacturerActionQuery): void {
                        $manufacturerActionQuery
                            ->where('target_type', CatalogAction::TARGET_MANUFACTURER)
                            ->whereExists(function ($targetQuery): void {
                                $targetQuery
                                    ->selectRaw('1')
                                    ->from('catalog_action_targets')
                                    ->whereColumn('catalog_action_targets.action_id', 'catalog_actions.id')
                                    ->where('catalog_action_targets.target_type', CatalogAction::TARGET_MANUFACTURER)
                                    ->whereColumn('catalog_action_targets.target_id', 'products.manufacturer_id');
                            });
                    });
            });

        $query->whereExists($promotionQuery->getQuery());
    }

    /**
     * @return array{min:?float,max:?float}
     */
    private function resolvePriceBounds(?Builder $query, ?float $fallbackMin = null, ?float $fallbackMax = null): array
    {
        $min = null;
        $max = null;

        if ($query !== null) {
            $defaultRate = $this->defaultCatalogTaxRate();
            $baseQuery = clone $query->getQuery();

            $minQuery = clone $baseQuery;
            if ($this->usesStoredPriceColumn($defaultRate)) {
                $resolvedMin = $minQuery->min('products.base_price');
            } else {
                $minExpression = $this->displayedPriceSqlExpression($minQuery);
                $resolvedMin = $minQuery->min(DB::raw($minExpression));
            }

            $maxQuery = clone $baseQuery;
            if ($this->usesStoredPriceColumn($defaultRate)) {
                $resolvedMax = $maxQuery->max('products.base_price');
            } else {
                $maxExpression = $this->displayedPriceSqlExpression($maxQuery);
                $resolvedMax = $maxQuery->max(DB::raw($maxExpression));
            }

            $min = is_numeric($resolvedMin) ? round((float) $resolvedMin, 2) : null;
            $max = is_numeric($resolvedMax) ? round((float) $resolvedMax, 2) : null;
        }

        return [
            'min' => $min ?? $fallbackMin,
            'max' => $max ?? $fallbackMax,
        ];
    }

    private function displayedPriceSqlExpression(Builder|QueryBuilder $query): string
    {
        $baseQuery = $query instanceof Builder ? $query->getQuery() : $query;
        $defaultRate = $this->defaultCatalogTaxRate();

        if ($this->usesStoredPriceColumn($defaultRate)) {
            return 'ROUND(CASE WHEN products.base_price < 0 THEN 0 ELSE products.base_price END, 2)';
        }

        $taxAlias = 'catalog_price_tax_rates';
        $joins = $baseQuery->joins ?? [];
        foreach ($joins as $join) {
            if (($join->table ?? null) === 'tax_rates as '.$taxAlias) {
                return $this->taxAdjustedPriceExpression($taxAlias, $defaultRate);
            }
        }

        $query->leftJoin('tax_rates as '.$taxAlias, function ($join) use ($taxAlias): void {
            $join->on('products.tax_rate_id', '=', $taxAlias.'.id')
                ->where($taxAlias.'.is_active', true);
        });

        return $this->taxAdjustedPriceExpression($taxAlias, $defaultRate);
    }

    private function taxAdjustedPriceExpression(string $taxAlias, object $defaultRate): string
    {
        $defaultRateType = (($defaultRate->rate_type ?? 'percent') === 'fixed') ? 'fixed' : 'percent';
        $defaultRateValue = round((float) ($defaultRate->rate ?? 0), 4);
        $fixedExpression = sprintf(
            'CASE WHEN (products.base_price + COALESCE(%s.rate, %F)) < 0 THEN 0 ELSE (products.base_price + COALESCE(%s.rate, %F)) END',
            $taxAlias,
            $defaultRateValue,
            $taxAlias,
            $defaultRateValue
        );
        $percentExpression = sprintf(
            'CASE WHEN (products.base_price * (1 + (COALESCE(%s.rate, %F) / 100))) < 0 THEN 0 ELSE (products.base_price * (1 + (COALESCE(%s.rate, %F) / 100))) END',
            $taxAlias,
            $defaultRateValue,
            $taxAlias,
            $defaultRateValue
        );

        return sprintf(
            "ROUND(CASE WHEN COALESCE(%s.rate_type, '%s') = 'fixed' THEN %s ELSE %s END, 2)",
            $taxAlias,
            $defaultRateType,
            $fixedExpression,
            $percentExpression
        );
    }

    private function defaultCatalogTaxRate(): ?object
    {
        return DB::table('tax_rates')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->select(['rate_type', 'rate'])
            ->first();
    }

    private function usesStoredPriceColumn(?object $defaultRate = null): bool
    {
        $pricesIncludeTax = (bool) filter_var(
            app(SystemSettingsService::class)->get('store_pricing_prices_include_tax', false),
            FILTER_VALIDATE_BOOL
        );

        return $pricesIncludeTax || ! $defaultRate;
    }

    private function withDesktopCacheHeaders(Request $request, Response $response, string $scope): Response
    {
        if ($this->frontendVariant($request) !== 'desktop') {
            return $response;
        }

        if (auth()->check()) {
            return $response->header('Cache-Control', 'private, no-cache, must-revalidate');
        }

        $lastModifiedTs = $this->catalogLastModifiedTimestamp();
        $etag = $this->catalogEtag($request, $scope, $lastModifiedTs);

        $response->header('Cache-Control', 'private, max-age=120, stale-while-revalidate=60');
        $response->setEtag($etag);
        if ($lastModifiedTs > 0) {
            $response->setLastModified(\Carbon\CarbonImmutable::createFromTimestampUTC($lastModifiedTs));
        }

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    private function catalogLastModifiedTimestamp(): int
    {
        return (int) Cache::remember('front:catalog:last-modified-ts', now()->addMinutes(2), static function (): int {
            $modelType = Product::class;

            $timestamps = [
                DB::table('products')->max('updated_at'),
                DB::table('product_translations')->max('updated_at'),
                DB::table('categories')->max('updated_at'),
                DB::table('category_translations')->max('updated_at'),
                DB::table('catalog_manufacturers')->max('updated_at'),
                DB::table('catalog_manufacturer_translations')->max('updated_at'),
                DB::table('catalog_option_values')->max('updated_at'),
                DB::table('catalog_option_value_translations')->max('updated_at'),
                DB::table('media')->where('model_type', $modelType)->max('updated_at'),
                DB::table('system_settings')
                    ->whereIn('key', ['catalog_hide_out_of_stock_products'])
                    ->max('updated_at'),
            ];

            $max = 0;
            foreach ($timestamps as $timestamp) {
                $unix = $timestamp ? strtotime((string) $timestamp) : 0;
                if ($unix > $max) {
                    $max = $unix;
                }
            }

            return $max;
        });
    }

    private function catalogEtag(Request $request, string $scope, int $lastModifiedTs): string
    {
        $wishlistHash = sha1(implode(',', app(WishlistService::class)->ids()));
        $gridCols = (string) $this->resolveGridCols($request, $this->defaultDesktopGridCols($request));

        return '"'.sha1(implode('|', [
            'desktop-catalog',
            $scope,
            app()->getLocale(),
            $request->getRequestUri(),
            $gridCols,
            $this->hideOutOfStockProducts() ? 'hide-oos' : 'all-stock',
            (string) $lastModifiedTs,
            $wishlistHash,
        ])).'"';
    }
}
