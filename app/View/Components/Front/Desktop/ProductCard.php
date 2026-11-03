<?php

namespace App\View\Components\Front\Desktop;

use App\Models\Catalog\Product\Product;
use App\Services\Front\WishlistService;
use App\Services\Pricing\ProductPricePresentationService;
use App\Services\Settings\SystemSettingsService;
use App\Support\Media\MediaUrl;
use App\Support\ProductMaterialLabel;
use Illuminate\View\Component;
use Illuminate\View\View;

class ProductCard extends Component
{
    public function __construct(
        public Product $product,
        public ?string $locale = null,
        public ?string $fallbackLocale = null,
        public bool $flat = false,
    ) {}

    public function render(): View
    {
        $locale = $this->locale ?: app()->getLocale();
        $fallbackLocale = $this->fallbackLocale ?: (string) config('app.locale');

        $translation = $this->product->translations->firstWhere('locale', $locale)
            ?? $this->product->translations->firstWhere('locale', $fallbackLocale);

        $mediaItems = $this->product->relationLoaded('media')
            ? $this->product->media->whereIn('collection_name', ['product_main', 'product_gallery'])->values()
            : collect();
        $usableMediaItems = $mediaItems
            ->filter(fn ($media) => MediaUrl::hasUsableSource($media, ['card_720w', 'card_480w', 'card_320w']))
            ->values();

        $mainMedia = $usableMediaItems->firstWhere('collection_name', 'product_main')
            ?? $usableMediaItems->firstWhere('collection_name', 'product_gallery')
            ?? $this->product->getMedia('*')
                ->whereIn('collection_name', ['product_main', 'product_gallery'])
                ->first(fn ($media) => MediaUrl::hasUsableSource($media, ['card_720w', 'card_480w', 'card_320w']));

        $hoverMedia = $usableMediaItems->first(
            static fn ($media): bool => $media->collection_name === 'product_gallery'
                && (! $mainMedia || (int) $media->id !== (int) $mainMedia->id)
        );
        if (! $hoverMedia) {
            $hoverMedia = $this->product->getMedia('product_gallery')->first(
                static fn ($media): bool => MediaUrl::hasUsableSource($media, ['card_720w', 'card_480w', 'card_320w'])
                    && (! $mainMedia || (int) $media->id !== (int) $mainMedia->id)
            );
        }
        if (! $hoverMedia) {
            $hoverMedia = $this->product
                ->getMedia('*')
                ->whereIn('collection_name', ['product_main', 'product_gallery'])
                ->first(static fn ($media): bool => MediaUrl::hasUsableSource($media, ['card_720w', 'card_480w', 'card_320w'])
                    && (! $mainMedia || (int) $media->id !== (int) $mainMedia->id));
        }
        $preferWebp = (bool) app(SystemSettingsService::class)->get('store_images_use_webp', false);

        $imageUrl720 = MediaUrl::conversionOrNull($mainMedia, 'card_720w', $preferWebp);
        $imageUrl480 = MediaUrl::conversionOrNull($mainMedia, 'card_480w', $preferWebp);
        $imageUrl320 = MediaUrl::conversionOrNull($mainMedia, 'card_320w', $preferWebp);
        $hoverImageUrl720 = MediaUrl::conversionOrNull($hoverMedia, 'card_720w', $preferWebp);
        $hoverImageUrl480 = MediaUrl::conversionOrNull($hoverMedia, 'card_480w', $preferWebp);
        $hoverImageUrl320 = MediaUrl::conversionOrNull($hoverMedia, 'card_320w', $preferWebp);

        $imageUrl = $imageUrl720 ?? $imageUrl480 ?? $imageUrl320 ?? ($mainMedia ? (string) $mainMedia->getUrl() : null);
        $hoverImageUrl = $hoverImageUrl720 ?? $hoverImageUrl480 ?? $hoverImageUrl320 ?? ($hoverMedia ? (string) $hoverMedia->getUrl() : null);
        $imageOriginalUrl = $mainMedia ? (string) $mainMedia->getUrl() : null;
        $hoverImageOriginalUrl = $hoverMedia ? (string) $hoverMedia->getUrl() : null;

        $imageSrcset = collect([
            $imageUrl320 ? $imageUrl320.' 320w' : null,
            $imageUrl480 ? $imageUrl480.' 480w' : null,
            $imageUrl720 ? $imageUrl720.' 720w' : null,
            $imageOriginalUrl ? $imageOriginalUrl.' '.max(1, (int) ($mainMedia?->width ?? 1000)).'w' : null,
        ])->filter()->unique()->implode(', ');

        $hoverImageSrcset = collect([
            $hoverImageUrl320 ? $hoverImageUrl320.' 320w' : null,
            $hoverImageUrl480 ? $hoverImageUrl480.' 480w' : null,
            $hoverImageUrl720 ? $hoverImageUrl720.' 720w' : null,
            $hoverImageOriginalUrl ? $hoverImageOriginalUrl.' '.max(1, (int) ($hoverMedia?->width ?? 1000)).'w' : null,
        ])->filter()->unique()->implode(', ');
        $imageWidth = max(1, (int) ($mainMedia?->width ?? 480));
        $imageHeight = max(1, (int) ($mainMedia?->height ?? 640));
        $hoverImageWidth = max(1, (int) ($hoverMedia?->width ?? $imageWidth));
        $hoverImageHeight = max(1, (int) ($hoverMedia?->height ?? $imageHeight));

        $visibleOptionRows = $this->product->visibleOptionRows();
        $availableOptionRows = $this->product->availableOptionRows();

        $optionRows = $availableOptionRows
            ->values()
            ->map(function ($row) use ($locale, $fallbackLocale): array {
                $rowId = (int) $row->id;
                $valueTranslation = $row->optionValue?->translations?->firstWhere('locale', $locale)
                    ?? $row->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
                    ?? $row->optionValue?->translations?->first();
                $parentTranslation = $row->parentOptionValue?->translations?->firstWhere('locale', $locale)
                    ?? $row->parentOptionValue?->translations?->firstWhere('locale', $fallbackLocale)
                    ?? $row->parentOptionValue?->translations?->first();
                $valueLabel = trim((string) ($valueTranslation?->name ?? $row->optionValue?->code ?? ''));
                $parentLabel = trim((string) ($parentTranslation?->name ?? $row->parentOptionValue?->code ?? ''));
                $label = $parentLabel !== '' && $valueLabel !== ''
                    ? $parentLabel.' / '.$valueLabel
                    : ($valueLabel !== '' ? $valueLabel : $parentLabel);

                return [
                    'id' => $rowId,
                    'input_id' => 'pov-'.$this->product->id.'-'.$rowId,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();

        $authUser = auth()->user();
        $priceCacheKey = (string) $this->product->id.'|'.(string) ($authUser?->id ?? 0).'|'.$locale.'|'.$fallbackLocale;
        $priceCache = request()->attributes->get('front.product_card_prices', []);
        if (! isset($priceCache[$priceCacheKey])) {
            $priceData = app(ProductPricePresentationService::class)->forProduct($this->product, $authUser, $locale, $fallbackLocale);

            $priceCache[$priceCacheKey] = [
                'current_gross' => (float) $priceData['current_gross'],
                'current_price' => number_format((float) $priceData['current_gross'], 2).' €',
                'old_price' => $priceData['old_gross'] !== null ? number_format((float) $priceData['old_gross'], 2).' €' : null,
                'discount_percent' => $priceData['discount_percent'],
                'promotion_badge' => $priceData['promotion_badge'],
                'lowest_30_days_price' => $priceData['lowest_30_days_gross'] !== null
                    ? number_format((float) $priceData['lowest_30_days_gross'], 2).' €'
                    : null,
            ];
            request()->attributes->set('front.product_card_prices', $priceCache);
        }

        $priceData = $priceCache[$priceCacheKey];
        $manufacturerName = '';
        if ($this->product->relationLoaded('manufacturer')) {
            $manufacturerName = (string) ($this->product->manufacturer?->translations?->firstWhere('locale', $locale)?->name
                ?? $this->product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale)?->name
                ?? '');
        }

        $categoryName = '';
        if ($this->product->relationLoaded('categories')) {
            $categoryName = (string) ($this->product->categories?->first()?->translations?->firstWhere('locale', $locale)?->name
                ?? $this->product->categories?->first()?->translations?->firstWhere('locale', $fallbackLocale)?->name
                ?? '');
        }

        return view('components.front.desktop.product-card', [
            'cardDomId' => 'pc-'.(int) $this->product->id.'-'.substr(str_replace('.', '', uniqid('', true)), -10),
            'productId' => (int) $this->product->id,
            'productUrl' => route('products.show', ['slug' => $translation?->slug ?? $this->product->id]),
            'productName' => $translation?->name ?? $this->product->code,
            'materialLabel' => ProductMaterialLabel::resolve($this->product, $locale, $fallbackLocale),
            'productSku' => (string) ($this->product->sku ?: $this->product->id),
            'productPriceValue' => round((float) ($priceData['current_gross'] ?? 0), 2),
            'productBrand' => $manufacturerName,
            'productCategory' => $categoryName,
            'imageUrl' => $imageUrl,
            'imageUrl320' => $imageUrl320,
            'imageSrcset' => $imageSrcset,
            'hoverImageUrl' => $hoverImageUrl,
            'hoverImageUrl320' => $hoverImageUrl320,
            'hoverImageSrcset' => $hoverImageSrcset,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
            'hoverImageWidth' => $hoverImageWidth,
            'hoverImageHeight' => $hoverImageHeight,
            'optionRows' => $optionRows,
            'hasVisibleOptionRows' => $visibleOptionRows->isNotEmpty(),
            'hasAvailableOptionRows' => $availableOptionRows->isNotEmpty(),
            'isPurchasable' => $this->product->storefrontIsPurchasable(),
            'isWishlisted' => app(WishlistService::class)->has((int) $this->product->id),
            'price' => $priceData['current_price'],
            'oldPrice' => $priceData['old_price'],
            'discountPercent' => $priceData['discount_percent'],
            'promotionBadge' => $priceData['promotion_badge'],
            'lowest30DaysPrice' => $priceData['lowest_30_days_price'],
            'reviewSummary' => $this->product->approvedCommentSummary([$locale, $fallbackLocale]),
            'flat' => $this->flat,
        ]);
    }
}
