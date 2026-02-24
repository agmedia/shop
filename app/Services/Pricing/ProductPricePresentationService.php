<?php

namespace App\Services\Pricing;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\Product;
use App\Models\User;
use App\Services\Catalog\ActionResolverService;
use Illuminate\Database\Eloquent\Builder;

class ProductPricePresentationService
{
    public function __construct(
        private readonly ActionResolverService $actionResolver,
        private readonly TaxPricingService $taxPricing,
    ) {
    }

    /**
     * @return array{
     *   current_gross: float,
     *   base_gross: float,
     *   old_gross: float|null,
     *   has_discount: bool,
     *   discount_percent: int|null,
     *   lowest_30_days_gross: float|null
     * }
     */
    public function forProduct(Product $product, ?User $user = null): array
    {
        $storedBase = (float) $product->base_price;
        $resolvedAction = $this->actionResolver->resolveProductAction($product, $user);
        $storedCurrent = $resolvedAction
            ? $this->actionResolver->applyToPrice($storedBase, $resolvedAction)
            : $storedBase;
        $baseNet = $this->taxPricing->normalizeNetPrice($storedBase, $product);
        $currentNet = $this->taxPricing->normalizeNetPrice($storedCurrent, $product);

        $baseGross = (float) $this->taxPricing->grossFromNet($baseNet, $product);
        $currentGross = (float) $this->taxPricing->grossFromNet($currentNet, $product);
        $hasDiscount = $currentGross < ($baseGross - 0.0001);

        $lowest30DaysGross = null;
        if ($hasDiscount) {
            $lowest30DaysNet = $this->lowestNetPriceInLast30Days($product, $storedBase, $user);
            $lowest30DaysGross = (float) $this->taxPricing->grossFromNet($lowest30DaysNet, $product);
        }

        return [
            'current_gross' => $currentGross,
            'base_gross' => $baseGross,
            'old_gross' => $hasDiscount ? $baseGross : null,
            'has_discount' => $hasDiscount,
            'discount_percent' => $hasDiscount && $baseGross > 0
                ? (int) round((($baseGross - $currentGross) / $baseGross) * 100)
                : null,
            'lowest_30_days_gross' => $lowest30DaysGross,
        ];
    }

    private function lowestNetPriceInLast30Days(Product $product, float $storedBasePrice, ?User $user): float
    {
        $periodStart = now()->subDays(30);
        $periodEnd = now();

        $categoryIds = $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->map(static fn ($id): int => (int) $id)->all()
            : $product->categories()->pluck('categories.id')->map(static fn ($id): int => (int) $id)->all();
        $manufacturerId = $product->manufacturer_id ? (int) $product->manufacturer_id : null;

        $actions = CatalogAction::query()
            ->where('scope', CatalogAction::SCOPE_PRODUCT)
            ->whereIn('type', [CatalogAction::TYPE_PERCENTAGE, CatalogAction::TYPE_FIXED])
            ->availableForAudience($user)
            ->where(function (Builder $q): void {
                $q->whereNull('coupon_code')
                    ->orWhere('coupon_code', '');
            })
            ->where(function (Builder $q) use ($periodEnd): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $periodEnd);
            })
            ->where(function (Builder $q) use ($periodStart): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $periodStart);
            })
            ->where(function (Builder $q) use ($product, $categoryIds, $manufacturerId): void {
                $q->where('target_type', CatalogAction::TARGET_ALL)
                    ->orWhere(function (Builder $qq) use ($product): void {
                        $qq->where('target_type', CatalogAction::TARGET_PRODUCT)
                            ->whereHas('targets', function (Builder $tq) use ($product): void {
                                $tq->where('target_type', CatalogAction::TARGET_PRODUCT)
                                    ->where('target_id', $product->id);
                            });
                    })
                    ->orWhere(function (Builder $qq) use ($categoryIds): void {
                        if ($categoryIds === []) {
                            return;
                        }

                        $qq->where('target_type', CatalogAction::TARGET_CATEGORY)
                            ->whereHas('targets', function (Builder $tq) use ($categoryIds): void {
                                $tq->where('target_type', CatalogAction::TARGET_CATEGORY)
                                    ->whereIn('target_id', $categoryIds);
                            });
                    })
                    ->orWhere(function (Builder $qq) use ($manufacturerId): void {
                        if (! $manufacturerId) {
                            return;
                        }

                        $qq->where('target_type', CatalogAction::TARGET_MANUFACTURER)
                            ->whereHas('targets', function (Builder $tq) use ($manufacturerId): void {
                                $tq->where('target_type', CatalogAction::TARGET_MANUFACTURER)
                                    ->where('target_id', $manufacturerId);
                            });
                    });
            })
            ->get();

        $best = $storedBasePrice;
        foreach ($actions as $action) {
            $candidate = $this->actionResolver->applyToPrice($storedBasePrice, $action);
            if ($candidate < $best) {
                $best = $candidate;
            }
        }

        return max(0.0, $this->taxPricing->normalizeNetPrice($best, $product));
    }
}
