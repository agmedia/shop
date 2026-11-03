<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActionResolverService
{
    public function resolveProductAction(
        Product $product,
        ?User $user = null,
        ?string $couponCode = null
    ): ?CatalogAction {
        return $this->bestPriceAction($product, $this->eligibleProductActions($product, $user, $couponCode));
    }

    public function resolveProductPromotion(
        Product $product,
        ?User $user = null,
        ?string $couponCode = null
    ): ?CatalogAction {
        $actions = $this->eligibleProductActions($product, $user, $couponCode);
        $priceAction = $this->bestPriceAction($product, $actions);
        $quantityAction = $actions->first(fn (CatalogAction $action): bool => $this->quantityPromotionAvailable($action));

        $promotion = $quantityAction && ($quantityAction->is_exclusive || $priceAction === null)
            ? $quantityAction
            : $priceAction;

        return $promotion?->loadMissing('translations');
    }

    /**
     * @return Collection<int, CatalogAction>
     */
    public function quantityPromotionsForProduct(Product $product, ?User $user = null, ?string $couponCode = null): Collection
    {
        return $this->eligibleProductActions($product, $user, $couponCode)
            ->filter(fn (CatalogAction $action): bool => $this->quantityPromotionAvailable($action))
            ->values();
    }

    private function quantityPromotionAvailable(CatalogAction $action): bool
    {
        return $action->type === CatalogAction::TYPE_BUY_X_GET_Y
            && (int) $action->buy_qty >= 2
            && (int) $action->get_qty > 0
            && (int) $action->get_qty < (int) $action->buy_qty
            && ($action->usage_limit === null || (int) $action->used_count < (int) $action->usage_limit);
    }

    /**
     * @return Collection<int, CatalogAction>
     */
    private function eligibleProductActions(Product $product, ?User $user, ?string $couponCode): Collection
    {
        $couponCode = trim((string) $couponCode);
        $categoryIds = $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
        $manufacturerId = $product->manufacturer_id ? (int) $product->manufacturer_id : null;

        return CatalogAction::query()
            ->active()
            ->where('scope', CatalogAction::SCOPE_PRODUCT)
            ->availableForAudience($user)
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
            ->where(function (Builder $q) use ($couponCode): void {
                $q->whereNull('coupon_code')
                    ->orWhere('coupon_code', '')
                    ->orWhereRaw('UPPER(coupon_code) = ?', [$couponCode]);
            })
            ->orderByDesc('is_exclusive')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, CatalogAction>  $actions
     */
    private function bestPriceAction(Product $product, Collection $actions): ?CatalogAction
    {
        $bestAction = null;
        $bestPrice = (float) $product->base_price;

        foreach ($actions as $action) {
            $candidate = $this->applyToPrice((float) $product->base_price, $action);
            if ($candidate < $bestPrice) {
                $bestPrice = $candidate;
                $bestAction = $action;
            }
        }

        return $bestAction;
    }

    public function applyToPrice(float $basePrice, CatalogAction $action): float
    {
        $value = (float) ($action->discount_value ?? 0);

        if ($action->type === CatalogAction::TYPE_PERCENTAGE) {
            return max(0.0, $basePrice - (($basePrice * $value) / 100));
        }

        if ($action->type === CatalogAction::TYPE_FIXED) {
            return max(0.0, $basePrice - $value);
        }

        return $basePrice;
    }
}
