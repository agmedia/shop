<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Content\ContentBlockResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    use ResolvesFrontendView;

    public function __construct(
        private readonly CatalogFeatureService $catalogFeatures
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureEnabled();

        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $variant = $this->frontendVariant($request);

        $posts = BlogPost::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'media',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12);

        $topBlocks = app(ContentBlockResolver::class)->forPlacement('blog.top', $locale, null, null, $variant);
        $bottomBlocks = app(ContentBlockResolver::class)->forPlacement('blog.bottom', $locale, null, null, $variant);

        return view($this->frontendView($request, 'blog.index'), [
            'posts' => $posts,
            'topBlocks' => $topBlocks,
            'bottomBlocks' => $bottomBlocks,
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $this->ensureEnabled();

        $locale = app()->getLocale();
        $fallbackLocale = (string) config('app.locale');

        $post = BlogPost::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->whereHas('translations', function ($q) use ($locale, $fallbackLocale, $slug): void {
                $q->whereIn('locale', [$locale, $fallbackLocale])
                    ->where('slug', $slug);
            })
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'categories.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'creator:id,name',
                'media',
            ])
            ->firstOrFail();

        $related = BlogPost::query()
            ->where('is_active', true)
            ->where('id', '!=', $post->id)
            ->where(function ($q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'media',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $relatedProductIds = collect($post->payload['related_product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $relatedProducts = collect();
        if ($relatedProductIds !== []) {
            $relatedProducts = Product::query()
                ->where('is_active', true)
                ->whereIn('id', $relatedProductIds)
                ->with([
                    'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                    'media',
                    'optionValues.optionValue.translations',
                    'optionValues.parentOptionValue.translations',
                    'manufacturer.translations',
                    'categories.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                ])
                ->get()
                ->sortBy(fn ($row) => array_search((int) $row->id, $relatedProductIds, true))
                ->values();
        }

        return view($this->frontendView($request, 'blog.show'), [
            'post' => $post,
            'related' => $related,
            'relatedProducts' => $relatedProducts,
            'locale' => $locale,
            'fallbackLocale' => $fallbackLocale,
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->catalogFeatures->useBlog(), 404);
    }
}
