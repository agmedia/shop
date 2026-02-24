<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Http\Controllers\Front\Concerns\ResolvesGridColumns;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Services\Content\ContentBlockResolver;
use App\Services\Front\WishlistService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CatalogController extends Controller
{
    use ResolvesFrontendView;
    use ResolvesGridColumns;

    public function index(Request $request): Response|RedirectResponse
    {
        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));
        $manufacturerSlug = trim((string) $request->query('manufacturer', ''));
        $sizeId = (int) $request->query('size', 0);
        $sort = (string) $request->query('sort', 'newest');
        $gridCols = $this->resolveGridCols($request, 4);
        $this->queueGridColsCookie($gridCols);
        if ($request->query->has('cols')) {
            return $this->redirectWithoutCols($request);
        }

        $query = Product::query()
            ->select(['id', 'code', 'sku', 'base_price', 'stock_qty', 'tax_rate_id', 'manufacturer_id', 'is_active'])
            ->where('is_active', true)
            ->with([
                'taxRate:id,rate,rate_type,is_active',
                'translations' => fn ($q) => $q
                    ->select(['id', 'product_id', 'locale', 'slug', 'name', 'excerpt'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'categories.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'manufacturer.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'media' => fn ($q) => $q
                    ->whereIn('collection_name', ['product_main', 'product_gallery'])
                    ->orderBy('order_column')
                    ->orderBy('id'),
                'optionValues' => fn ($q) => $q
                    ->select(['id', 'product_id', 'option_value_id', 'parent_option_value_id', 'is_active', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'optionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                        'parentOptionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                    ]),
            ]);

        if ($search !== '') {
            $query->whereHas('translations', function ($q) use ($locale, $fallbackLocale, $search): void {
                $q->whereIn('locale', [$locale, $fallbackLocale])
                    ->where(function ($searchQuery) use ($search): void {
                        $searchQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('excerpt', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($categorySlug !== '') {
            $query->whereHas('categories', function ($categoryQuery) use ($locale, $fallbackLocale, $categorySlug): void {
                $categoryQuery
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->where('is_active', true)
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

        if ($sizeId > 0) {
            $query->whereHas('optionValues', function ($optionQuery) use ($sizeId): void {
                $optionQuery
                    ->where('is_active', true)
                    ->where(function ($sizeQuery) use ($sizeId): void {
                        $sizeQuery
                            ->where('option_value_id', $sizeId)
                            ->orWhere('parent_option_value_id', $sizeId);
                    });
            });
        }

        match ($sort) {
            'price_low' => $query->orderBy('base_price'),
            'price_high' => $query->orderByDesc('base_price'),
            'stock_high' => $query->orderByDesc('stock_qty')->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        $products = $query
            ->paginate($this->shopPerPage($request, $gridCols))
            ->withQueryString();

        $categories = $this->cachedCatalogCategories($locale, $fallbackLocale);
        $manufacturers = $this->cachedCatalogManufacturers($locale, $fallbackLocale);
        $sizes = $this->cachedCatalogSizes($locale, $fallbackLocale);

        $response = response()->view($this->frontendView($request, 'shop.index'), [
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'sizes' => $sizes,
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'manufacturer' => $manufacturerSlug,
                'size' => $sizeId > 0 ? $sizeId : '',
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
            ->where('is_active', true)
            ->with(['translations' => fn ($q) => $q
                ->where('scope', Category::SCOPE_CATALOG)
                ->whereIn('locale', [$locale, $fallbackLocale])])
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
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
        $sizeId = (int) $request->query('size', 0);
        $sort = (string) $request->query('sort', 'newest');
        $gridCols = $this->resolveGridCols($request, 4);
        $this->queueGridColsCookie($gridCols);
        if ($request->query->has('cols')) {
            return $this->redirectWithoutCols($request);
        }

        $category = Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->where('is_active', true)
            ->whereHas('translations', function ($q) use ($locale, $fallbackLocale, $categorySlug): void {
                $q->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale])
                    ->where('slug', $categorySlug);
            })
            ->with([
                'translations' => fn ($q) => $q->where('scope', Category::SCOPE_CATALOG)->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->firstOrFail();

        $categoryTreeIds = $category->descendants()
            ->where('scope', Category::SCOPE_CATALOG)
            ->where('is_active', true)
            ->pluck('id');
        $categoryTreeIds->prepend($category->id);

        $productsQuery = Product::query()
            ->select(['id', 'code', 'sku', 'base_price', 'stock_qty', 'tax_rate_id', 'manufacturer_id', 'is_active'])
            ->where('is_active', true)
            ->with([
                'taxRate:id,rate,rate_type,is_active',
                'translations' => fn ($q) => $q
                    ->select(['id', 'product_id', 'locale', 'slug', 'name', 'excerpt'])
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'categories.translations' => fn ($q) => $q
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale]),
                'manufacturer.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'media' => fn ($q) => $q
                    ->whereIn('collection_name', ['product_main', 'product_gallery'])
                    ->orderBy('order_column')
                    ->orderBy('id'),
                'optionValues' => fn ($q) => $q
                    ->select(['id', 'product_id', 'option_value_id', 'parent_option_value_id', 'is_active', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'optionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                        'parentOptionValue.translations' => fn ($tq) => $tq
                            ->select(['id', 'option_value_id', 'locale', 'name'])
                            ->whereIn('locale', [$locale, $fallbackLocale]),
                    ]),
            ])
            ->whereHas('categories', function ($categoryQuery) use ($categoryTreeIds): void {
                $categoryQuery
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->where('is_active', true)
                    ->whereIn('categories.id', $categoryTreeIds);
            });

        if ($search !== '') {
            $productsQuery->whereHas('translations', function ($q) use ($locale, $fallbackLocale, $search): void {
                $q->whereIn('locale', [$locale, $fallbackLocale])
                    ->where(function ($searchQuery) use ($search): void {
                        $searchQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('excerpt', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
            });
        }

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

        if ($sizeId > 0) {
            $productsQuery->whereHas('optionValues', function ($optionQuery) use ($sizeId): void {
                $optionQuery
                    ->where('is_active', true)
                    ->where(function ($sizeQuery) use ($sizeId): void {
                        $sizeQuery
                            ->where('option_value_id', $sizeId)
                            ->orWhere('parent_option_value_id', $sizeId);
                    });
            });
        }

        match ($sort) {
            'price_low' => $productsQuery->orderBy('base_price'),
            'price_high' => $productsQuery->orderByDesc('base_price'),
            'stock_high' => $productsQuery->orderByDesc('stock_qty')->orderByDesc('id'),
            'oldest' => $productsQuery->orderBy('id'),
            default => $productsQuery->orderByDesc('id'),
        };

        $products = $productsQuery
            ->paginate($this->shopPerPage($request, $gridCols))
            ->withQueryString();

        $categories = $this->cachedCatalogCategories($locale, $fallbackLocale);
        $manufacturers = $this->cachedCatalogManufacturers($locale, $fallbackLocale);
        $sizes = $this->cachedCatalogSizes($locale, $fallbackLocale);

        $subcategories = $category->children()
            ->where('scope', Category::SCOPE_CATALOG)
            ->where('is_active', true)
            ->with(['translations' => fn ($q) => $q
                ->where('scope', Category::SCOPE_CATALOG)
                ->whereIn('locale', [$locale, $fallbackLocale])])
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $breadcrumbCategories = $category->ancestors()
            ->where('scope', Category::SCOPE_CATALOG)
            ->where('is_active', true)
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
            targetRef: $categorySlug,
            frontendVariant: $variant
        );

        $bottomBlocks = app(ContentBlockResolver::class)->forPlacement(
            placement: 'category.bottom',
            locale: $locale,
            targetType: 'category',
            targetRef: $categorySlug,
            frontendVariant: $variant
        );

        $response = response()->view($this->frontendView($request, 'categories.show'), [
            'category' => $category,
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'sizes' => $sizes,
            'subcategories' => $subcategories,
            'breadcrumbCategories' => $breadcrumbCategories,
            'topBlocks' => $topBlocks,
            'bottomBlocks' => $bottomBlocks,
            'filters' => [
                'q' => $search,
                'manufacturer' => $manufacturerSlug,
                'size' => $sizeId > 0 ? $sizeId : '',
                'sort' => $sort,
                'cols' => $gridCols,
            ],
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);

        return $this->withDesktopCacheHeaders($request, $response, 'category:'.$categorySlug);
    }

    private function cachedCatalogCategories(string $locale, string $fallbackLocale)
    {
        $cacheKey = sprintf('front:catalog:categories:%s:%s', $locale, $fallbackLocale);

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale) {
            return Category::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('scope', Category::SCOPE_CATALOG)
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q
                    ->select(['id', 'category_id', 'scope', 'locale', 'name', 'slug'])
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    private function cachedCatalogManufacturers(string $locale, string $fallbackLocale)
    {
        $cacheKey = sprintf('front:catalog:manufacturers:%s:%s', $locale, $fallbackLocale);

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale) {
            return Manufacturer::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q
                    ->select(['id', 'manufacturer_id', 'locale', 'name', 'slug'])
                    ->whereIn('locale', [$locale, $fallbackLocale])])
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    private function cachedCatalogSizes(string $locale, string $fallbackLocale)
    {
        $cacheKey = sprintf('front:catalog:sizes:%s:%s', $locale, $fallbackLocale);

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($locale, $fallbackLocale) {
            return OptionValue::query()
                ->select(['id', 'code', 'sort_order'])
                ->where('is_active', true)
                ->whereHas('productOptionValues', function ($q): void {
                    $q->where('is_active', true)
                        ->whereHas('product', fn ($productQuery) => $productQuery->where('is_active', true));
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

    private function shopPerPage(Request $request, int $gridCols): int
    {
        $basePerPage = $this->productPerPage($request, false);
        $variant = $this->frontendVariant($request);

        if ($variant === 'mobile') {
            $mobileCols = in_array($gridCols, [1, 2], true) ? $gridCols : 1;
            $rows = (int) ceil($basePerPage / max(1, $mobileCols));

            return max($mobileCols, $rows * $mobileCols);
        }

        $desktopCols = in_array($gridCols, [2, 3, 4, 5], true) ? $gridCols : 4;
        $rows = (int) ceil($basePerPage / $desktopCols);

        return max($desktopCols, $rows * $desktopCols);
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

        return '"'.sha1(implode('|', [
            'desktop-catalog',
            $scope,
            app()->getLocale(),
            $request->getRequestUri(),
            (string) $lastModifiedTs,
            $wishlistHash,
        ])).'"';
    }
}
