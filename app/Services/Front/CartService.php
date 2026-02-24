<?php

namespace App\Services\Front;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\Catalog\Product\Product;
use App\Services\Catalog\ActionResolverService;
use App\Services\Pricing\TaxPricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'front.cart.items';
    private const COUPON_SESSION_KEY = 'front.cart.coupon_code';

    public function __construct(
        private readonly ActionResolverService $actionResolver,
        private readonly TaxPricingService $taxPricing
    ) {
    }

    /**
     * @return array<string, array{product_id:int,product_option_value_id:int|null,quantity:int}>
     */
    public function raw(): array
    {
        $items = Session::get(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $key => $item) {
            if (is_array($item) && isset($item['product_id'])) {
                $productId = (int) ($item['product_id'] ?? 0);
                $optionValueId = (int) ($item['product_option_value_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
            } else {
                // Backward compatibility with previous storage format: [product_id => quantity]
                $productId = (int) $key;
                $optionValueId = 0;
                $quantity = (int) $item;
            }

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $resolvedKey = $this->lineKey($productId, $optionValueId > 0 ? $optionValueId : null);
            $normalized[$resolvedKey] = [
                'product_id' => $productId,
                'product_option_value_id' => $optionValueId > 0 ? $optionValueId : null,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(?string $locale = null): Collection
    {
        $items = $this->raw();

        if ($items === []) {
            return collect();
        }

        $locale = $locale ?: app()->getLocale();
        $fallbackLocale = (string) config('app.locale');
        $couponCode = $this->couponCode();
        $user = auth()->user();

        $productIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['product_id'],
            $items
        )));
        $productOptionValueIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['product_option_value_id'] ?? 0),
            $items
        ))));

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with([
                'media',
                'taxRate',
                'translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'manufacturer.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->get()
            ->keyBy('id');

        $optionRows = ProductOptionValue::query()
            ->whereIn('id', $productOptionValueIds)
            ->with([
                'optionValue.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'optionValue.option.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'parentOptionValue.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
                'parentOptionValue.option.translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->get()
            ->keyBy('id');

        $lines = collect();

        foreach ($items as $key => $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $optionValueId = (int) ($item['product_option_value_id'] ?? 0);

            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $optionRow = $optionValueId > 0 ? $optionRows->get($optionValueId) : null;
            if ($optionRow && (int) $optionRow->product_id !== $productId) {
                $optionRow = null;
            }

            $maxStock = $optionRow ? (int) $optionRow->stock_qty : (int) $product->stock_qty;
            if ($maxStock <= 0) {
                continue;
            }

            $qty = min(max(1, $quantity), $maxStock);
            $storedBaseUnitPrice = $optionRow && $optionRow->price_override !== null
                ? (float) $optionRow->price_override
                : (float) $product->base_price;
            $resolvedAction = $this->actionResolver->resolveProductAction($product, $user, $couponCode);
            $storedDiscountedUnitPrice = $resolvedAction
                ? $this->actionResolver->applyToPrice($storedBaseUnitPrice, $resolvedAction)
                : $storedBaseUnitPrice;
            $baseUnitPrice = $this->taxPricing->normalizeNetPrice($storedBaseUnitPrice, $product);
            $unitPrice = $this->taxPricing->normalizeNetPrice($storedDiscountedUnitPrice, $product);
            $unitDiscount = round(max(0, $baseUnitPrice - $unitPrice), 2);
            $lineDiscountTotal = round($unitDiscount * $qty, 2);
            $lineTotal = round($unitPrice * $qty, 2);
            $unitTaxAmount = $this->taxPricing->taxFromNet($unitPrice, $product);
            $baseUnitTaxAmount = $this->taxPricing->taxFromNet($baseUnitPrice, $product);
            $lineTaxTotal = round($unitTaxAmount * $qty, 2);
            $displayUnitPrice = round($unitPrice + $unitTaxAmount, 2);
            $displayBaseUnitPrice = round($baseUnitPrice + $baseUnitTaxAmount, 2);
            $displayLineTotal = round($lineTotal + $lineTaxTotal, 2);
            $taxRateValue = (float) ($this->taxPricing->resolveRateForProduct($product)?->rate ?? 0);
            $translation = $product->translations->firstWhere('locale', $locale)
                ?? $product->translations->firstWhere('locale', $fallbackLocale);
            $optionMeta = $this->optionMeta($optionRow, $locale, $fallbackLocale);

            $lines->push([
                'key' => $key,
                'product' => $product,
                'translation' => $translation,
                'product_option_value_id' => $optionRow?->id,
                'option_label' => $optionMeta['label'],
                'option_name' => $optionMeta['name'],
                'option_value_label' => $optionMeta['value'],
                'sku' => (string) ($optionRow?->sku ?: $product->sku ?: ''),
                'quantity' => $qty,
                'base_unit_price' => $baseUnitPrice,
                'unit_price' => $unitPrice,
                'display_unit_price' => $displayUnitPrice,
                'display_base_unit_price' => $displayBaseUnitPrice,
                'unit_discount' => $unitDiscount,
                'line_discount_total' => $lineDiscountTotal,
                'line_total' => $lineTotal,
                'display_line_total' => $displayLineTotal,
                'line_tax_total' => $lineTaxTotal,
                'tax_rate' => $taxRateValue,
                'action_code' => $resolvedAction?->code,
            ]);
        }

        return $lines->values();
    }

    /**
     * @return array{
     *  line_count:int,
     *  item_qty:int,
     *  subtotal:float,
     *  discount_total:float,
     *  subtotal_after_discount:float,
     *  tax_rate:float|null,
     *  tax_rate_type:string,
     *  tax_total:float,
     *  grand_total:float,
     *  coupon_code:string
     * }
     */
    public function summary(?string $locale = null): array
    {
        $lines = $this->lines($locale);
        $subtotal = round((float) $lines->sum(static fn (array $line): float => (float) ($line['base_unit_price'] ?? 0) * (int) ($line['quantity'] ?? 0)), 2);
        $discountTotal = round((float) $lines->sum('line_discount_total'), 2);
        $subtotalAfterDiscount = round(max(0.0, $subtotal - $discountTotal), 2);
        $taxTotal = round((float) $lines->sum('line_tax_total'), 2);
        $taxRates = $lines->pluck('tax_rate')
            ->map(static fn ($rate): float => round((float) $rate, 4))
            ->unique()
            ->values();
        $taxRateValue = $taxRates->count() === 1 ? (float) $taxRates->first() : null;
        $grandTotal = round($subtotalAfterDiscount + $taxTotal, 2);

        return [
            'line_count' => $lines->count(),
            'item_qty' => (int) $lines->sum('quantity'),
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'tax_rate' => $taxRateValue,
            'tax_rate_type' => 'percent',
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'coupon_code' => $this->couponCode(),
        ];
    }

    public function hasItems(): bool
    {
        return $this->summary()['line_count'] > 0;
    }

    public function add(Product $product, int $quantity = 1, ?int $productOptionValueId = null): bool
    {
        if (! $product->is_active) {
            return false;
        }

        $optionRow = $this->resolveProductOptionValue($product, $productOptionValueId);
        if ($this->requiresOptionSelection($product) && ! $optionRow) {
            return false;
        }

        $items = $this->raw();
        $lineKey = $this->lineKey($product->id, $optionRow?->id);
        $existing = (int) ($items[$lineKey]['quantity'] ?? 0);
        $requested = max(1, min($quantity, 999));
        $target = $existing + $requested;

        $stock = $optionRow ? (int) $optionRow->stock_qty : (int) $product->stock_qty;
        if ($stock <= 0) {
            return false;
        }

        $items[$lineKey] = [
            'product_id' => (int) $product->id,
            'product_option_value_id' => $optionRow?->id,
            'quantity' => min($target, $stock),
        ];
        Session::put(self::SESSION_KEY, $items);

        return true;
    }

    public function set(Product $product, int $quantity, ?int $productOptionValueId = null): bool
    {
        $items = $this->raw();
        $lineKey = $this->lineKey((int) $product->id, $productOptionValueId);

        if ($quantity <= 0) {
            unset($items[$lineKey]);
            Session::put(self::SESSION_KEY, $items);

            return true;
        }

        if (! $product->is_active) {
            return false;
        }

        $optionRow = $this->resolveProductOptionValue($product, $productOptionValueId);
        $stock = $optionRow ? (int) $optionRow->stock_qty : (int) $product->stock_qty;
        if ($stock <= 0) {
            unset($items[$lineKey]);
            Session::put(self::SESSION_KEY, $items);

            return false;
        }

        $items[$lineKey] = [
            'product_id' => (int) $product->id,
            'product_option_value_id' => $optionRow?->id,
            'quantity' => min(max(1, $quantity), $stock),
        ];
        Session::put(self::SESSION_KEY, $items);

        return true;
    }

    public function remove(int $productId, ?int $productOptionValueId = null): void
    {
        $items = $this->raw();

        if ($productOptionValueId !== null) {
            unset($items[$this->lineKey($productId, $productOptionValueId)]);
        } else {
            foreach ($items as $key => $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    unset($items[$key]);
                }
            }
        }

        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget([self::SESSION_KEY, self::COUPON_SESSION_KEY]);
    }

    public function couponCode(): string
    {
        return strtoupper(trim((string) Session::get(self::COUPON_SESSION_KEY, '')));
    }

    public function applyCoupon(string $couponCode): bool
    {
        $couponCode = strtoupper(trim($couponCode));
        if ($couponCode === '') {
            return false;
        }

        $hasCoupon = CatalogAction::query()
            ->active()
            ->where('scope', CatalogAction::SCOPE_PRODUCT)
            ->where('coupon_code', $couponCode)
            ->exists();

        if (! $hasCoupon) {
            return false;
        }

        Session::put(self::COUPON_SESSION_KEY, $couponCode);

        return true;
    }

    public function clearCoupon(): void
    {
        Session::forget(self::COUPON_SESSION_KEY);
    }

    /**
     * @param array<int, array{product_id:int,product_option_value_id:int|null,quantity:int}> $lines
     */
    public function replaceRaw(array $lines, ?string $couponCode = null): void
    {
        $normalized = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $optionValueId = (int) ($line['product_option_value_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $this->lineKey($productId, $optionValueId > 0 ? $optionValueId : null);
            $current = (int) ($normalized[$key]['quantity'] ?? 0);
            $normalized[$key] = [
                'product_id' => $productId,
                'product_option_value_id' => $optionValueId > 0 ? $optionValueId : null,
                'quantity' => min(999, $current + $quantity),
            ];
        }

        Session::put(self::SESSION_KEY, $normalized);

        $coupon = strtoupper(trim((string) ($couponCode ?? '')));
        if ($coupon !== '') {
            Session::put(self::COUPON_SESSION_KEY, $coupon);
        } else {
            Session::forget(self::COUPON_SESSION_KEY);
        }
    }

    private function lineKey(int $productId, ?int $productOptionValueId): string
    {
        return $productId.':'.(int) ($productOptionValueId ?? 0);
    }

    private function resolveProductOptionValue(Product $product, ?int $productOptionValueId): ?ProductOptionValue
    {
        $id = (int) ($productOptionValueId ?? 0);
        if ($id <= 0) {
            return null;
        }

        return ProductOptionValue::query()
            ->where('id', $id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();
    }

    private function requiresOptionSelection(Product $product): bool
    {
        return ProductOptionValue::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array{name:?string,value:?string,label:?string}
     */
    private function optionMeta(?ProductOptionValue $optionRow, string $locale, string $fallbackLocale): array
    {
        if (! $optionRow) {
            return ['name' => null, 'value' => null, 'label' => null];
        }

        $child = $optionRow->optionValue?->translations?->firstWhere('locale', $locale)
            ?? $optionRow->optionValue?->translations?->firstWhere('locale', $fallbackLocale)
            ?? $optionRow->optionValue?->translations?->first();
        $parent = $optionRow->parentOptionValue?->translations?->firstWhere('locale', $locale)
            ?? $optionRow->parentOptionValue?->translations?->firstWhere('locale', $fallbackLocale)
            ?? $optionRow->parentOptionValue?->translations?->first();

        $childLabel = trim((string) ($child?->name ?? $optionRow->optionValue?->code ?? ''));
        $parentLabel = trim((string) ($parent?->name ?? $optionRow->parentOptionValue?->code ?? ''));
        $optionTranslation = $optionRow->optionValue?->option?->translations?->firstWhere('locale', $locale)
            ?? $optionRow->optionValue?->option?->translations?->firstWhere('locale', $fallbackLocale)
            ?? $optionRow->optionValue?->option?->translations?->first();
        $optionName = trim((string) ($optionTranslation?->name ?? ''));
        $valueLabel = $childLabel !== '' ? $childLabel : $parentLabel;

        if ($optionName !== '' && $valueLabel !== '') {
            return [
                'name' => $optionName,
                'value' => $valueLabel,
                'label' => $optionName.': '.$valueLabel,
            ];
        }

        if ($parentLabel !== '' && $childLabel !== '') {
            return [
                'name' => null,
                'value' => null,
                'label' => $parentLabel.' / '.$childLabel,
            ];
        }

        if ($childLabel !== '') {
            return [
                'name' => null,
                'value' => $childLabel,
                'label' => $childLabel,
            ];
        }

        return [
            'name' => null,
            'value' => $parentLabel !== '' ? $parentLabel : null,
            'label' => $parentLabel !== '' ? $parentLabel : null,
        ];
    }

}
