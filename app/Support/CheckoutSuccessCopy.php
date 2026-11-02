<?php

namespace App\Support;

final class CheckoutSuccessCopy
{
    public static function status(?string $name): string
    {
        if ($name === null) {
            return (string) __('ui.checkout.success.status_fallback_new');
        }

        if (app()->getLocale() === 'de' && in_array(mb_strtolower(trim($name)), ['novo', 'nova', 'new'], true)) {
            return (string) __('ui.checkout.success.status_fallback_new');
        }

        return $name;
    }

    public static function bankDescription(?string $description): string
    {
        if ($description === null) {
            return '-';
        }

        if (app()->getLocale() !== 'de') {
            return $description;
        }

        // Translate only the generic display prefix, keeping the payment snapshot and QR payload unchanged.
        return preg_replace_callback(
            '/^Web narud[zž]ba(?=\s|$)/iu',
            static fn (): string => (string) __('ui.checkout.success.bank_description_order'),
            $description
        ) ?? $description;
    }
}
