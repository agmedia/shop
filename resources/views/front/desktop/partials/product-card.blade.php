<x-front.desktop.product-card
    :product="$product"
    :locale="$locale ?? null"
    :fallback-locale="$fallbackLocale ?? null"
    :flat="(bool) ($flat ?? false)"
/>
