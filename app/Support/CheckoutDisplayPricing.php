<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CheckoutDisplayPricing
{
    /**
     * Consumer-facing prices only; checkout and stored order totals remain net plus tax.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    public static function make(Collection $lines, array $summary, array $totals): array
    {
        $subtotal = round((float) $lines->sum(fn (array $line): float => (float) $line['display_base_unit_price'] * (int) $line['quantity']), 2);
        $goodsAfterDiscount = round((float) $summary['subtotal_after_discount'] + (float) $summary['tax_total'], 2);
        $rates = $lines->pluck('tax_rate')->map(fn ($rate): float => (float) $rate)->unique()->sort()->values();
        $taxLabel = $rates->isNotEmpty()
            ? __('compliance.tax_included_rate', ['rate' => $rates->map(fn (float $rate): string => rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.'))->implode(' % / ')])
            : __('compliance.tax_included');
        $values = [
            'subtotal' => $subtotal,
            'discount_total' => round(max(0.0, $subtotal - $goodsAfterDiscount), 2),
            'tax_total' => round((float) $totals['tax_total'], 2),
            'shipping_total' => round((float) ($totals['shipping_gross'] ?? $totals['shipping_total']), 2),
            'payment_fee_total' => round((float) ($totals['payment_fee_gross'] ?? $totals['payment_fee_total']), 2),
            'grand_total' => round((float) $totals['grand_total'], 2),
        ];

        foreach ($values as $key => $amount) {
            $values[$key.'_formatted'] = Currency::format($amount);
        }

        return $values + ['tax_label' => $taxLabel];
    }
}
