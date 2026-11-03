<?php

namespace App\Services\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Content\Page\InfoPage;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Support\Facades\Storage;

class NavigationMenuService
{
    public const SETTINGS_KEY = 'front_navigation_main';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $resolvedCache = [];

    public function __construct(private readonly SystemSettingsService $settings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forLocale(string $locale): array
    {
        $locale = strtolower(trim($locale));
        $fallbackLocale = strtolower((string) config('app.locale', 'en'));
        $cacheKey = $locale.'|'.$fallbackLocale;

        if (isset($this->resolvedCache[$cacheKey])) {
            return $this->resolvedCache[$cacheKey];
        }

        $items = collect($this->configuredItems())
            ->filter(fn ($item): bool => (bool) ($item['is_active'] ?? true))
            ->sortBy(fn ($item): int => (int) ($item['sort_order'] ?? 0))
            ->values();

        if ($items->isEmpty()) {
            return $this->resolvedCache[$cacheKey] = [];
        }

        $categoryIds = $items
            ->where('type', 'category')
            ->pluck('category_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $pageIds = $items
            ->where('type', 'page')
            ->pluck('page_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $categories = Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->currentlyVisible()
            ->with([
                'translations' => fn ($q) => $q
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->orderBy('_lft')
            ->get();

        $categoriesById = $categories->keyBy('id');
        $childrenByParentId = $categories->groupBy(fn ($category) => (int) ($category->parent_id ?? 0));

        $pagesById = InfoPage::query()
            ->where('is_active', true)
            ->whereIn('id', $pageIds)
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($items as $index => $item) {
            $type = (string) ($item['type'] ?? 'custom');
            $entry = null;

            if ($type === 'category') {
                $categoryId = (int) ($item['category_id'] ?? 0);
                $category = $categoryId > 0 ? $categoriesById->get($categoryId) : null;

                if ($category instanceof Category) {
                    $entry = $this->resolveCategoryItem($category, $item, $childrenByParentId, $locale, $fallbackLocale);
                }
            } elseif ($type === 'page') {
                $pageId = (int) ($item['page_id'] ?? 0);
                $page = $pageId > 0 ? $pagesById->get($pageId) : null;

                if ($page instanceof InfoPage) {
                    $translation = $this->pickPageTranslation($page, $locale, $fallbackLocale);
                    $slug = trim((string) ($translation?->slug ?? ''));

                    if ($slug !== '') {
                        $label = $this->labelForItem(
                            $item,
                            (string) ($translation?->title ?? $page->code),
                            $locale,
                            $fallbackLocale
                        );
                        $entry = [
                            'key' => 'page-'.$page->id.'-'.$index,
                            'type' => 'page',
                            'label' => $label,
                            'url' => route('pages.show', ['slug' => $slug]),
                            'children' => [],
                            'open_in_new_tab' => false,
                            'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
                        ];
                    }
                }
            } elseif ($type === 'blog') {
                $entry = [
                    'key' => 'blog-'.$index,
                    'type' => 'blog',
                    'label' => $this->labelForItem($item, 'Blog', $locale, $fallbackLocale),
                    'url' => route('blog.index'),
                    'children' => [],
                    'open_in_new_tab' => false,
                    'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
                ];
            } elseif ($type === 'contact') {
                $entry = [
                    'key' => 'contact-'.$index,
                    'type' => 'contact',
                    'label' => $this->labelForItem($item, 'Kontakt', $locale, $fallbackLocale),
                    'url' => route('contact.create'),
                    'children' => [],
                    'open_in_new_tab' => false,
                    'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
                ];
            } elseif ($type === 'faq') {
                $entry = [
                    'key' => 'faq-'.$index,
                    'type' => 'faq',
                    'label' => $this->labelForItem($item, 'FAQ', $locale, $fallbackLocale),
                    'url' => route('faq.index'),
                    'children' => [],
                    'open_in_new_tab' => false,
                    'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
                ];
            } else {
                $url = $this->urlForItem($item, $locale, $fallbackLocale);
                $label = $this->labelForItem($item, '', $locale, $fallbackLocale);

                if ($url !== '' && $label !== '') {
                    $entry = [
                        'key' => 'custom-'.$index,
                        'type' => 'custom',
                        'label' => $label,
                        'url' => $url,
                        'children' => [],
                        'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
                        'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
                    ];
                }
            }

            if (is_array($entry) && trim((string) ($entry['url'] ?? '')) !== '') {
                $resolved[] = $entry;
            }
        }

        return $this->resolvedCache[$cacheKey] = $resolved;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function configuredItems(): array
    {
        $raw = $this->settings->get(self::SETTINGS_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $labelTranslations = $this->normalizeTranslations($item['label_translations'] ?? []);
            $urlTranslations = $this->normalizeTranslations($item['url_translations'] ?? []);
            $promoTitleTranslations = $this->normalizeTranslations($item['desktop_promo_title_translations'] ?? []);
            $promoSubtitleTranslations = $this->normalizeTranslations($item['desktop_promo_subtitle_translations'] ?? []);
            $promoCtaLabelTranslations = $this->normalizeTranslations($item['desktop_promo_cta_label_translations'] ?? []);
            $promoCtaUrlTranslations = $this->normalizeTranslations($item['desktop_promo_cta_url_translations'] ?? []);
            $fallbackLocale = strtolower((string) config('app.locale', 'en'));

            $legacyLabel = trim((string) ($item['label'] ?? ''));
            if ($legacyLabel !== '' && $labelTranslations === []) {
                $labelTranslations[strtolower((string) config('app.locale', 'en'))] = $legacyLabel;
            }

            $legacyUrl = trim((string) ($item['url'] ?? ''));
            if ($legacyUrl !== '' && $urlTranslations === []) {
                $urlTranslations[$fallbackLocale] = $legacyUrl;
            }

            $legacyPromoTitle = trim((string) ($item['desktop_promo_title'] ?? ''));
            $legacyPromoSubtitle = trim((string) ($item['desktop_promo_subtitle'] ?? ''));
            $legacyPromoCtaLabel = trim((string) ($item['desktop_promo_cta_label'] ?? ''));
            $legacyPromoCtaUrl = trim((string) ($item['desktop_promo_cta_url'] ?? ''));

            if ($legacyPromoTitle !== '' && $promoTitleTranslations === []) {
                $promoTitleTranslations[$fallbackLocale] = $legacyPromoTitle;
            }
            if ($legacyPromoSubtitle !== '' && $promoSubtitleTranslations === []) {
                $promoSubtitleTranslations[$fallbackLocale] = $legacyPromoSubtitle;
            }
            if ($legacyPromoCtaLabel !== '' && $promoCtaLabelTranslations === []) {
                $promoCtaLabelTranslations[$fallbackLocale] = $legacyPromoCtaLabel;
            }
            if ($legacyPromoCtaUrl !== '' && $promoCtaUrlTranslations === []) {
                $promoCtaUrlTranslations[$fallbackLocale] = $legacyPromoCtaUrl;
            }

            $storedLabel = $this->pickLocalizedValue(
                $labelTranslations,
                $fallbackLocale
            );
            $storedUrl = $this->pickLocalizedValue(
                $urlTranslations,
                $fallbackLocale
            );

            $items[] = [
                'type' => (string) ($item['type'] ?? 'custom'),
                'label' => $storedLabel,
                'label_translations' => $labelTranslations,
                'category_id' => (int) ($item['category_id'] ?? 0),
                'page_id' => (int) ($item['page_id'] ?? 0),
                'url' => $storedUrl,
                'url_translations' => $urlTranslations,
                'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
                'show_dropdown' => (bool) ($item['show_dropdown'] ?? true),
                'is_active' => (bool) ($item['is_active'] ?? true),
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'desktop_promo_image_path' => trim((string) ($item['desktop_promo_image_path'] ?? ($item['desktop_promo_image_url'] ?? ''))),
                'desktop_promo_title' => $this->pickLocalizedValue($promoTitleTranslations, $fallbackLocale),
                'desktop_promo_title_translations' => $promoTitleTranslations,
                'desktop_promo_subtitle' => $this->pickLocalizedValue($promoSubtitleTranslations, $fallbackLocale),
                'desktop_promo_subtitle_translations' => $promoSubtitleTranslations,
                'desktop_promo_cta_label' => $this->pickLocalizedValue($promoCtaLabelTranslations, $fallbackLocale),
                'desktop_promo_cta_label_translations' => $promoCtaLabelTranslations,
                'desktop_promo_cta_url' => $this->pickLocalizedValue($promoCtaUrlTranslations, $fallbackLocale),
                'desktop_promo_cta_url_translations' => $promoCtaUrlTranslations,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Category>>  $childrenByParentId
     * @return array<string, mixed>|null
     */
    private function resolveCategoryItem(
        Category $category,
        array $item,
        $childrenByParentId,
        string $locale,
        string $fallbackLocale
    ): ?array {
        $translation = $this->pickCategoryTranslation($category, $locale, $fallbackLocale);
        $slug = trim((string) ($translation?->slug ?? ''));

        if ($slug === '') {
            return null;
        }

        $children = [];
        if ((bool) ($item['show_dropdown'] ?? true)) {
            $children = $this->buildCategoryChildren($category->id, $childrenByParentId, $locale, $fallbackLocale);
        }

        return [
            'key' => 'category-'.$category->id,
            'type' => 'category',
            'label' => $this->labelForItem(
                $item,
                (string) ($translation?->name ?? $category->code),
                $locale,
                $fallbackLocale
            ),
            'url' => route('categories.show', ['slug' => $slug]),
            'color' => $category->navigationColor(),
            'children' => $children,
            'open_in_new_tab' => false,
            'mega_promo' => $this->resolveMegaPromo($item, $locale, $fallbackLocale),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function resolveMegaPromo(array $item, string $locale, string $fallbackLocale): array
    {
        $imagePath = trim((string) ($item['desktop_promo_image_path'] ?? ''));
        $imageUrl = '';

        if ($imagePath !== '') {
            if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://') || str_starts_with($imagePath, '/')) {
                $imageUrl = $imagePath;
            } else {
                $imageUrl = Storage::disk('public')->url($imagePath);
            }
        }

        return [
            'image_url' => $imageUrl,
            'title' => $this->localizedPromoValue($item, 'desktop_promo_title', $locale, $fallbackLocale),
            'subtitle' => $this->localizedPromoValue($item, 'desktop_promo_subtitle', $locale, $fallbackLocale),
            'cta_label' => $this->localizedPromoValue($item, 'desktop_promo_cta_label', $locale, $fallbackLocale),
            'cta_url' => $this->localizedPromoValue($item, 'desktop_promo_cta_url', $locale, $fallbackLocale),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function localizedPromoValue(array $item, string $field, string $locale, string $fallbackLocale): string
    {
        $translations = $this->normalizeTranslations($item[$field.'_translations'] ?? []);
        $value = $this->pickLocalizedValue($translations, $locale, $fallbackLocale);

        return $value !== '' ? $value : trim((string) ($item[$field] ?? ''));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Category>>  $childrenByParentId
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryChildren(int $parentId, $childrenByParentId, string $locale, string $fallbackLocale): array
    {
        $children = [];
        $childrenRows = $childrenByParentId->get($parentId, collect())
            ->sortBy(fn (Category $category): array => [(int) $category->sort_order, (int) $category->id])
            ->values();

        foreach ($childrenRows as $child) {
            $translation = $this->pickCategoryTranslation($child, $locale, $fallbackLocale);
            $slug = trim((string) ($translation?->slug ?? ''));

            if ($slug === '') {
                continue;
            }

            $children[] = [
                'label' => (string) ($translation?->name ?? $child->code),
                'url' => route('categories.show', ['slug' => $slug]),
                'color' => $child->navigationColor(),
                'children' => $this->buildCategoryChildren((int) $child->id, $childrenByParentId, $locale, $fallbackLocale),
            ];
        }

        return $children;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function labelForItem(array $item, string $fallback, string $locale, string $fallbackLocale): string
    {
        $translations = $this->normalizeTranslations($item['label_translations'] ?? []);
        $label = $this->pickLocalizedValue($translations, $locale, $fallbackLocale);
        if ($label === '') {
            $label = trim((string) ($item['label'] ?? ''));
        }

        return $label !== '' ? $label : $fallback;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function urlForItem(array $item, string $locale, string $fallbackLocale): string
    {
        $translations = $this->normalizeTranslations($item['url_translations'] ?? []);
        $url = $this->pickLocalizedValue($translations, $locale, $fallbackLocale);
        if ($url === '') {
            $url = trim((string) ($item['url'] ?? ''));
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeTranslations(mixed $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $locale => $value) {
            $key = strtolower(trim((string) $locale));
            if ($key === '') {
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $normalized[$key] = $text;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function pickLocalizedValue(array $translations, string ...$preferredLocales): string
    {
        foreach ($preferredLocales as $locale) {
            $key = strtolower(trim($locale));
            if ($key !== '' && isset($translations[$key]) && trim((string) $translations[$key]) !== '') {
                return trim((string) $translations[$key]);
            }
        }

        foreach ($translations as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function pickCategoryTranslation(Category $category, string $locale, string $fallbackLocale): mixed
    {
        return $category->translations->firstWhere('locale', $locale)
            ?? $category->translations->firstWhere('locale', $fallbackLocale)
            ?? $category->translations->first();
    }

    private function pickPageTranslation(InfoPage $page, string $locale, string $fallbackLocale): mixed
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', $fallbackLocale)
            ?? $page->translations->first();
    }
}
