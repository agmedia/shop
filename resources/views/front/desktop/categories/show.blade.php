@extends('front.desktop.layouts.store')

@php
    $showManufacturers = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
    $translation = $category->translations->firstWhere('locale', $locale)
        ?? $category->translations->firstWhere('locale', $fallbackLocale);
    $hasSubcategories = ($subcategories ?? collect())->isNotEmpty();
    $mobileDefaultCols = in_array((int) ($storeSettings['product']['mobile_default_cols'] ?? 2), [1, 2], true)
        ? (int) ($storeSettings['product']['mobile_default_cols'] ?? 2)
        : 2;
    $desktopDefaultCols = in_array((int) ($storeSettings['product']['desktop_default_cols'] ?? 4), [4, 5], true)
        ? (int) ($storeSettings['product']['desktop_default_cols'] ?? 4)
        : 4;
    $showCategoryFilters = (bool) ($showCategoryFilters ?? true);
    $showCategoryProducts = (bool) ($showCategoryProducts ?? true);
    $currentCols = (int) ($filters['cols'] ?? $desktopDefaultCols);
    $mobileCols = in_array($currentCols, [1, 2], true) ? $currentCols : $mobileDefaultCols;
    $paginationMode = (string) ($storeSettings['product']['catalog_pagination_mode'] ?? 'pagination');
    $useAsyncPagination = in_array($paginationMode, ['load_more', 'infinite'], true);
    $isInfinitePagination = $paginationMode === 'infinite';
    $desktopFilterSelectCount = ($hasSubcategories ? 1 : 0) + ($showManufacturers ? 1 : 0) + count($optionFilters ?? []) + count($attributeFilters ?? []) + 1;
    $hasActiveFilters = trim((string) ($filters['q'] ?? '')) !== ''
        || trim((string) ($filters['manufacturer'] ?? '')) !== ''
        || trim((string) ($filters['price_min'] ?? '')) !== ''
        || trim((string) ($filters['price_max'] ?? '')) !== ''
        || (bool) ($filters['promo_only'] ?? false)
        || collect(array_keys(request()->query()))
            ->contains(fn ($key): bool => str_starts_with((string) $key, 'opt_') || str_starts_with((string) $key, 'attr_'))
        || (string) ($filters['sort'] ?? 'default') !== 'default';
    $priceMinValue = trim((string) ($filters['price_min'] ?? ''));
    $priceMaxValue = trim((string) ($filters['price_max'] ?? ''));
    $promoOnlyEnabled = (bool) ($filters['promo_only'] ?? false);
    $promoToggleDisabled = ! (bool) ($promoFilterAvailable ?? false) && ! $promoOnlyEnabled;
    $hasPriceFilter = $priceMinValue !== '' || $priceMaxValue !== '';
    $hasPricePanelFilter = $hasPriceFilter || $promoOnlyEnabled;
    $priceTriggerLabel = __('ui.shop.filters.price');
    $resolvedPriceBoundsMin = isset($priceBounds['min']) && is_numeric($priceBounds['min'] ?? null)
        ? (float) $priceBounds['min']
        : null;
    $resolvedPriceBoundsMax = isset($priceBounds['max']) && is_numeric($priceBounds['max'] ?? null)
        ? (float) $priceBounds['max']
        : null;
    $priceSliderMin = $resolvedPriceBoundsMin !== null
        ? (int) floor($resolvedPriceBoundsMin)
        : ($priceMinValue !== '' ? (int) floor((float) $priceMinValue) : 0);
    $priceSliderMax = $resolvedPriceBoundsMax !== null
        ? (int) ceil($resolvedPriceBoundsMax)
        : ($priceMaxValue !== '' ? (int) ceil((float) $priceMaxValue) : max($priceSliderMin, 100));

    if ($priceSliderMax <= $priceSliderMin) {
        $priceSliderMax = $priceSliderMin + ($priceSliderMin > 0 ? 10 : 100);
    }

    $priceSliderSelectedMin = $priceMinValue !== ''
        ? max($priceSliderMin, min($priceSliderMax, (int) floor((float) $priceMinValue)))
        : $priceSliderMin;
    $priceSliderSelectedMax = $priceMaxValue !== ''
        ? max($priceSliderMin, min($priceSliderMax, (int) ceil((float) $priceMaxValue)))
        : $priceSliderMax;

    if ($priceSliderSelectedMin > $priceSliderSelectedMax) {
        [$priceSliderSelectedMin, $priceSliderSelectedMax] = [$priceSliderSelectedMax, $priceSliderSelectedMin];
    }

    $sizeFilterLabel = mb_strtolower(trim((string) __('ui.shop.filters.size')));
    $sizeOptionFilter = null;
    $compositionAttributeFilter = null;
    $orderedCategoryFilters = collect();

    foreach (($optionFilters ?? []) as $filterOption) {
        $queryKey = (string) ($filterOption['query_key'] ?? '');
        $label = mb_strtolower(trim((string) ($filterOption['label'] ?? '')));

        if ($queryKey === 'size' || $label === $sizeFilterLabel) {
            $sizeOptionFilter = $filterOption;
            continue;
        }

        $orderedCategoryFilters->push($filterOption);
    }

    foreach (($attributeFilters ?? []) as $attributeFilter) {
        if (in_array((string) ($attributeFilter['query_key'] ?? ''), ['attr_sastav', 'attr_material'], true)) {
            $compositionAttributeFilter = $attributeFilter;
            continue;
        }

        $orderedCategoryFilters->push($attributeFilter);
    }

    if ($compositionAttributeFilter !== null) {
        if ($sizeOptionFilter !== null) {
            $orderedCategoryFilters->prepend($compositionAttributeFilter);
            $orderedCategoryFilters->prepend($sizeOptionFilter);
        } else {
            $orderedCategoryFilters->prepend($compositionAttributeFilter);
        }
    } elseif ($sizeOptionFilter !== null) {
        $orderedCategoryFilters->prepend($sizeOptionFilter);
    }

    $gridClass = match ($currentCols) {
        1 => 'grid grid-cols-1 gap-y-5',
        2 => 'grid grid-cols-2 gap-x-4 gap-y-5',
        3 => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 xl:grid-cols-3',
        5 => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5',
        default => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
    };
@endphp

@section('title', ($translation?->name ?? __('ui.category.fallback_name')).' '.__('ui.category.products_suffix'))
@section('main_class', 'w-full px-0 pt-3 pb-4 sm:pt-3 sm:pb-6')

@section('content')
    <style>
        .catalog-price-range-card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            padding: .95rem;
        }

        .catalog-price-range-values {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .catalog-price-range-value {
            min-width: 0;
        }

        .catalog-price-range-value-label {
            display: block;
            margin-bottom: .2rem;
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .catalog-price-range-value-amount {
            display: inline-flex;
            align-items: center;
            gap: .2rem;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .catalog-price-range-slider {
            position: relative;
            margin-top: .9rem;
            height: 30px;
        }

        .catalog-price-range-track,
        .catalog-price-range-progress {
            position: absolute;
            top: 50%;
            height: 4px;
            transform: translateY(-50%);
            border-radius: 999px;
        }

        .catalog-price-range-track {
            left: 0;
            right: 0;
            background: #dbe4ee;
        }

        .catalog-price-range-progress {
            background: #11896d;
        }

        .catalog-price-range-slider input[type="range"] {
            pointer-events: none;
            position: absolute;
            inset: 0;
            width: 100%;
            height: 30px;
            margin: 0;
            appearance: none;
            background: transparent;
        }

        .catalog-price-range-slider input[type="range"]::-webkit-slider-runnable-track {
            height: 4px;
            background: transparent;
        }

        .catalog-price-range-slider input[type="range"]::-webkit-slider-thumb {
            pointer-events: auto;
            appearance: none;
            width: 20px;
            height: 20px;
            margin-top: -8px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 6px 14px -10px rgba(15, 23, 42, .6);
            cursor: pointer;
        }

        .catalog-price-range-slider input[type="range"]::-moz-range-track {
            height: 4px;
            background: transparent;
        }

        .catalog-price-range-slider input[type="range"]::-moz-range-thumb {
            pointer-events: auto;
            width: 20px;
            height: 20px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 6px 14px -10px rgba(15, 23, 42, .6);
            cursor: pointer;
        }

        .catalog-price-range-slider input[type="range"]:focus {
            outline: none;
        }

        .catalog-price-range-slider input[type="range"]:focus::-webkit-slider-thumb,
        .catalog-price-range-slider input[type="range"]:focus::-moz-range-thumb {
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, .12);
        }

        .catalog-price-range-scale {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-top: .3rem;
            font-size: .78rem;
            font-weight: 600;
            color: #64748b;
        }

        .catalog-price-promo-toggle {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .9rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .catalog-price-promo-copy {
            min-width: 0;
        }

        .catalog-price-promo-label {
            display: block;
            font-size: .9rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }

        .catalog-price-promo-hint {
            display: block;
            margin-top: .18rem;
            font-size: .8rem;
            color: #64748b;
            line-height: 1.4;
        }

        .catalog-filter-sticky-shell {
            position: relative;
            z-index: 30;
            --catalog-sticky-bleed: 12px;
        }

        @media (min-width: 640px) {
            .catalog-filter-sticky-shell {
                --catalog-sticky-bleed: 16px;
            }
        }

        @media (min-width: 1024px) {
            .catalog-filter-sticky-shell {
                --catalog-sticky-bleed: 24px;
            }
        }

        .catalog-filter-sticky-bar {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
        }

        .catalog-filter-sticky-shell.is-pinned {
            min-height: var(--catalog-sticky-height, auto);
        }

        .catalog-filter-sticky-shell.is-pinned .catalog-filter-sticky-bar {
            position: fixed;
            top: var(--catalog-sticky-top, var(--site-header-bottom, 60px));
            left: calc(var(--catalog-sticky-left, 0px) - var(--catalog-sticky-bleed, 0px));
            width: calc(var(--catalog-sticky-width, 100%) + (var(--catalog-sticky-bleed, 0px) * 2));
            z-index: 35;
            box-sizing: border-box;
            background: #fff;
            backdrop-filter: none;
            padding-top: .5rem;
            padding-bottom: .5rem !important;
            padding-left: var(--catalog-sticky-bleed, 0px);
            padding-right: var(--catalog-sticky-bleed, 0px);
            border-bottom-color: transparent !important;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .08);
        }

        @supports not ((backdrop-filter: blur(10px))) {
            .catalog-filter-sticky-bar {
                background: rgba(255, 255, 255, 0.99);
            }
        }

        .catalog-switch {
            position: relative;
            flex-shrink: 0;
            display: inline-flex;
            width: 44px;
            height: 26px;
            align-items: center;
        }

        .catalog-switch input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .catalog-switch-track {
            position: relative;
            display: inline-flex;
            width: 44px;
            height: 26px;
            border-radius: 999px;
            background: #d1d5db;
            transition: background-color .18s ease;
        }

        .catalog-switch-track::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .15);
            transition: transform .18s ease;
        }

        .catalog-switch input:checked + .catalog-switch-track {
            background: #0f172a;
        }

        .catalog-switch input:checked + .catalog-switch-track::after {
            transform: translateX(18px);
        }

        .catalog-switch input:focus + .catalog-switch-track {
            box-shadow: 0 0 0 3px rgba(15, 23, 42, .12);
        }

        .catalog-switch input:disabled + .catalog-switch-track {
            background: #e5e7eb;
        }

        .catalog-switch input:disabled + .catalog-switch-track::after {
            box-shadow: none;
        }

        .catalog-switch input:disabled,
        .catalog-switch input:disabled + .catalog-switch-track {
            cursor: not-allowed;
        }

        .catalog-price-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            margin-top: 1rem;
            border: 0;
            background: transparent;
            padding: 0;
            font-size: .8rem;
            font-weight: 700;
            color: #0f172a;
            transition: color .15s ease;
        }

        .catalog-price-reset:hover {
            color: #334155;
        }

        .catalog-price-reset[disabled] {
            opacity: .45;
            cursor: default;
            pointer-events: none;
        }

        @media (max-width: 1024px) {
            body.desktop-mobile-filter-open {
                overflow: hidden;
            }

            body.desktop-mobile-filter-open #cookie-consent-floating-button {
                display: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            .catalog-mobile-filter-rail {
                width: 100%;
                max-width: 100%;
            }

            .catalog-filter-sticky-bar {
                background: #fff;
                backdrop-filter: none;
                position: relative;
                z-index: 1;
                box-sizing: border-box;
                padding-top: .6rem !important;
                padding-bottom: .55rem !important;
                overflow: visible;
                border-bottom-color: rgba(226, 232, 240, .9) !important;
            }

            .catalog-filter-sticky-shell.is-pinned .catalog-filter-sticky-bar {
                left: 50%;
                width: 100vw;
                transform: translateX(-50%);
                padding-top: .6rem !important;
                padding-bottom: .55rem !important;
                padding-left: max(.75rem, env(safe-area-inset-left, 0px));
                padding-right: max(.75rem, env(safe-area-inset-right, 0px));
                box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
            }

            .catalog-filter-sticky-shell.is-pinned .catalog-mobile-filter-rail {
                width: min(100%, var(--catalog-sticky-width, 100%));
                margin-left: auto;
                margin-right: auto;
            }

            .catalog-mobile-filter-toolbar {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto auto;
                align-items: center;
                gap: .5rem;
                width: 100%;
            }

            .catalog-mobile-filter-trigger {
                display: inline-flex;
                min-width: 0;
                height: 32px;
                width: 100%;
                align-items: center;
                justify-content: flex-start;
                gap: .45rem;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #fff;
                padding: 0 .8rem;
                font-size: .84rem;
                font-weight: 600;
                color: #111827;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-mobile-filter-trigger-label {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .catalog-mobile-filter-trigger:hover {
                border-color: #9ca3af;
                background: #f8fafc;
                color: #334155;
            }

            .catalog-mobile-filter-trigger[aria-expanded="true"] {
                border-color: #0f172a;
                background: #f8fafc;
                color: #111827;
            }

            .catalog-mobile-filter-reset {
                display: inline-flex;
                height: 32px;
                flex-shrink: 0;
                align-items: center;
                justify-content: center;
                gap: .45rem;
                border: 1px solid #fda4af;
                border-radius: 4px;
                background: #fff;
                padding: 0 .85rem;
                font-size: .74rem;
                font-weight: 700;
                letter-spacing: .08em;
                text-transform: uppercase;
                color: #b91c1c;
                white-space: nowrap;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-mobile-filter-reset:hover {
                border-color: #fb7185;
                background: #fff1f2;
                color: #be123c;
            }

            .catalog-mobile-grid-group {
                display: inline-flex;
                align-items: stretch;
                gap: .5rem;
                flex-shrink: 0;
            }

            .catalog-mobile-grid-toggle {
                display: inline-flex;
                height: 32px;
                width: 32px;
                align-items: center;
                justify-content: center;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #fff;
                color: #64748b;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-mobile-grid-toggle:hover {
                border-color: #9ca3af;
                background: #f8fafc;
                color: #334155;
            }

            .catalog-mobile-grid-toggle.is-active {
                border-color: #0f172a;
                background: #0f172a;
                color: #fff;
            }

            @media (max-width: 430px) {
                .catalog-mobile-filter-toolbar {
                    gap: .45rem;
                }

                .catalog-mobile-filter-trigger {
                    height: 30px;
                    gap: .35rem;
                    padding: 0 .65rem;
                    font-size: .78rem;
                }

                .catalog-mobile-filter-reset {
                    height: 30px;
                    gap: .3rem;
                    padding: 0 .65rem;
                    font-size: .68rem;
                }

                .catalog-mobile-grid-toggle {
                    height: 30px;
                    width: 30px;
                }
            }

            .catalog-mobile-filter-panel {
                flex: 1 1 auto;
                gap: 1rem;
                margin-top: 0;
                border: 0;
                background: #fff;
                padding: 1rem;
                overflow-y: auto;
                align-content: flex-start;
            }

            .catalog-mobile-filter-group {
                display: block;
            }

            .catalog-mobile-filter-select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                height: 42px;
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 0;
                background-color: #fff;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%2364758b' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right .8rem center;
                background-size: 12px 12px;
                padding: 0 .95rem;
                padding-right: 2.2rem;
                font-size: .95rem;
                color: #334155;
            }

            .catalog-mobile-filter-select:focus {
                border-color: #0f172a;
                box-shadow: 0 0 0 2px rgba(15, 23, 42, .08);
            }

            .catalog-mobile-price-heading {
                display: block;
                margin-bottom: .9rem;
                font-size: .82rem;
                font-weight: 700;
                letter-spacing: .04em;
                text-transform: uppercase;
                color: #64748b;
            }

            .catalog-mobile-price-card {
                border: 1px solid #cbd5e1;
                border-radius: 0;
                background: #fff;
                padding: 1rem;
            }

            .catalog-mobile-price-card .catalog-price-range-value-amount {
                font-size: .95rem;
            }

            .catalog-mobile-price-card .catalog-price-promo-label {
                font-size: .88rem;
            }

            .catalog-mobile-price-card .catalog-price-promo-hint {
                font-size: .78rem;
            }

            .catalog-mobile-reset-wrap {
                padding-top: .15rem;
            }

            .catalog-mobile-reset-link {
                display: inline-flex;
                height: 42px;
                width: 100%;
                align-items: center;
                justify-content: center;
                gap: .55rem;
                border: 1px solid #cbd5e1;
                background: #fff;
                padding: 0 1rem;
                font-size: .88rem;
                font-weight: 700;
                color: #0f172a;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-mobile-reset-link:hover {
                border-color: #94a3b8;
                background: #f8fafc;
                color: #334155;
            }

            .catalog-mobile-filter-drawer {
                position: fixed;
                inset: 0;
                z-index: 9750;
                align-items: stretch;
                justify-content: flex-end;
            }

            .catalog-mobile-filter-drawer-backdrop {
                position: absolute;
                inset: 0;
                border: 0;
                background: rgba(15, 23, 42, .48);
            }

            .catalog-mobile-filter-drawer-panel {
                position: relative;
                display: flex;
                width: 100dvw;
                min-width: 100dvw;
                height: 100dvh;
                max-height: 100dvh;
                flex-direction: column;
                background: #fff;
                box-shadow: -18px 0 40px rgba(15, 23, 42, .16);
            }

            .catalog-mobile-filter-drawer-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                border-bottom: 1px solid #e2e8f0;
                padding: 1rem;
            }

            .catalog-mobile-filter-drawer-title {
                font-size: 1rem;
                font-weight: 800;
                letter-spacing: .05em;
                text-transform: uppercase;
                color: #0f172a;
            }

            .catalog-mobile-filter-drawer-close {
                display: inline-flex;
                height: 44px;
                width: 44px;
                flex-shrink: 0;
                align-items: center;
                justify-content: center;
                border: 1px solid #cbd5e1;
                background: #fff;
                color: #334155;
                transition: background-color .15s ease, border-color .15s ease, color .15s ease;
            }

            .catalog-mobile-filter-drawer-close:hover {
                border-color: #94a3b8;
                background: #f8fafc;
                color: #0f172a;
            }

        }

        @media (min-width: 1025px) {
            .catalog-filter-select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                height: 36px;
                width: 100%;
                border: 1px solid #b8c7da;
                border-radius: 2px;
                background-color: #fff;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23475569' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right .65rem center;
                background-size: 12px 12px;
                padding: 0 .7rem;
                padding-right: 1.95rem;
                font-size: .78rem;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: .02em;
                transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
            }

            .catalog-filter-select:hover {
                border-color: #94a3b8;
                background-color: #fcfdff;
            }

            .catalog-filter-select:focus {
                outline: none;
                border-color: #0f172a;
                box-shadow: 0 0 0 2px rgba(15, 23, 42, .12);
            }

            .catalog-filter-select.catalog-filter-native-hidden {
                display: none;
            }

            .catalog-filter-custom {
                position: relative;
            }

            .catalog-filter-custom-button {
                display: block;
                height: 36px;
                width: 100%;
                border: 1px solid #b8c7da;
                background: #fff;
                padding: 0 .7rem;
                padding-right: 1.95rem;
                text-align: left;
                font-size: .78rem;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: .02em;
                transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23475569' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right .65rem center;
                background-size: 12px 12px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 34px;
            }

            .catalog-filter-custom-button.is-placeholder {
                font-size: .74rem;
                letter-spacing: .03em;
                color: #475569;
            }

            .catalog-filter-custom-button:hover {
                border-color: #94a3b8;
                background-color: #fcfdff;
            }

            .catalog-filter-custom-button:focus {
                outline: none;
                border-color: #0f172a;
                box-shadow: 0 0 0 2px rgba(15, 23, 42, .12);
            }

            .catalog-filter-custom-list {
                position: absolute;
                z-index: 120;
                top: calc(100% + 4px);
                left: 0;
                right: 0;
                max-height: 240px;
                overflow: auto;
                border: 1px solid #b8c7da;
                background: #fff;
                box-shadow: 0 10px 20px -18px rgba(15, 23, 42, 0.5);
                display: none;
            }

            .catalog-filter-custom.is-open .catalog-filter-custom-list {
                display: block;
            }

            .catalog-filter-custom-item {
                display: block;
                width: 100%;
                border: 0;
                background: #fff;
                padding: .45rem .7rem;
                text-align: left;
                font-size: .78rem;
                color: #1e293b;
                text-transform: uppercase;
                letter-spacing: .02em;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .catalog-filter-custom-item.is-placeholder {
                font-size: .74rem;
                letter-spacing: .03em;
                color: #475569;
            }

            .catalog-filter-select.catalog-filter-sort {
                font-size: .76rem;
                letter-spacing: .03em;
                color: #475569;
            }

            .catalog-filter-custom.is-sort .catalog-filter-custom-button,
            .catalog-filter-custom.is-sort .catalog-filter-custom-item {
                font-size: .76rem;
                letter-spacing: .03em;
                color: #475569;
            }

            .catalog-filter-select.catalog-filter-inline-select {
                height: 32px;
                border-color: #d1d5db;
                border-radius: 4px;
                padding: 0 .8rem;
                padding-right: 1.85rem;
                font-size: .84rem;
                font-weight: 600;
                color: #111827;
                text-transform: none;
                letter-spacing: 0;
                background-position: right .55rem center;
                background-size: 10px 10px;
            }

            .catalog-filter-custom.is-inline-label .catalog-filter-custom-button,
            .catalog-filter-custom.is-inline-label .catalog-filter-custom-item {
                font-size: .84rem;
                font-weight: 600;
                text-transform: none;
                letter-spacing: 0;
            }

            .catalog-filter-custom.is-inline-label .catalog-filter-custom-button {
                height: 32px;
                border-color: #d1d5db;
                border-radius: 4px;
                padding: 0 .8rem;
                padding-right: 1.85rem;
                color: #111827;
                background-position: right .55rem center;
                background-size: 10px 10px;
                line-height: 30px;
            }

            .catalog-filter-custom.is-inline-label .catalog-filter-custom-button.is-placeholder,
            .catalog-filter-custom.is-inline-label .catalog-filter-custom-item.is-placeholder {
                font-size: .84rem;
                font-weight: 600;
                color: #111827;
                letter-spacing: 0;
            }

            .catalog-filter-custom.is-inline-label .catalog-filter-custom-list {
                border-color: #d1d5db;
                border-radius: 4px;
                right: auto;
                width: max-content;
                min-width: 100%;
                max-width: min(340px, calc(100vw - 40px));
            }

            .catalog-filter-custom.is-inline-label .catalog-filter-custom-item {
                padding: .55rem .8rem;
            }

            .catalog-filter-sort-wrap .catalog-filter-custom.is-inline-label .catalog-filter-custom-list {
                left: auto;
                right: 0;
            }

            .catalog-grid-toggle {
                display: inline-flex;
                height: 32px;
                width: 32px;
                align-items: center;
                justify-content: center;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #fff;
                color: #64748b;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-grid-toggle:hover {
                border-color: #9ca3af;
                background: #f8fafc;
                color: #334155;
            }

            .catalog-grid-toggle.is-active {
                border-color: #0f172a;
                background: #0f172a;
                color: #fff;
            }

            .catalog-reset-button {
                display: inline-flex;
                height: 32px;
                align-items: center;
                justify-content: center;
                gap: .45rem;
                border: 1px solid #fda4af;
                border-radius: 4px;
                padding: 0 .85rem;
                font-size: .74rem;
                font-weight: 700;
                letter-spacing: .08em;
                text-transform: uppercase;
                color: #e11d48;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .catalog-reset-button:hover {
                border-color: #fb7185;
                background: #fff1f2;
                color: #be123c;
            }

            .catalog-price-filter {
                position: relative;
            }

            .catalog-price-filter-toggle {
                display: inline-flex;
                height: 32px;
                min-width: 82px;
                align-items: center;
                justify-content: space-between;
                gap: .6rem;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #fff;
                padding: 0 .8rem;
                font-size: .84rem;
                font-weight: 600;
                color: #111827;
                transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
            }

            .catalog-price-filter-toggle:hover {
                border-color: #9ca3af;
                background: #f8fafc;
            }

            .catalog-price-filter.is-open .catalog-price-filter-toggle,
            .catalog-price-filter-toggle:focus {
                outline: none;
                border-color: #0f172a;
                box-shadow: 0 0 0 2px rgba(15, 23, 42, .12);
            }

            .catalog-price-filter.is-open .catalog-price-filter-toggle svg {
                transform: rotate(180deg);
            }

            .catalog-price-filter-toggle.is-active {
                border-color: #94a3b8;
                color: #0f172a;
            }

            .catalog-price-filter-panel {
                position: absolute;
                top: calc(100% + 6px);
                left: 0;
                z-index: 140;
                display: none;
                width: min(320px, calc(100vw - 48px));
                box-shadow: 0 18px 35px -24px rgba(15, 23, 42, .5);
            }

            .catalog-price-filter.is-open .catalog-price-filter-panel {
                display: block;
            }

            .catalog-filter-custom-item:hover {
                background: #f8fafc;
            }

            .catalog-filter-custom-item.is-selected {
                background: #edf2f8;
                color: #0f172a;
                font-weight: 600;
                box-shadow: inset 3px 0 0 #0f172a;
            }

            .catalog-filter-composition {
                width: 112px;
            }

            .catalog-filter-composition .catalog-filter-select,
            .catalog-filter-custom.is-composition .catalog-filter-custom-button,
            .catalog-filter-custom.is-composition .catalog-filter-custom-item {
                font-size: .75rem;
            }

            .catalog-filter-color {
                width: 112px;
            }

            .catalog-filter-custom.is-color .catalog-filter-custom-button,
            .catalog-filter-custom.is-color .catalog-filter-custom-item {
                text-transform: none;
                letter-spacing: 0;
            }

            .catalog-filter-custom.is-color .catalog-filter-custom-list {
                width: min(290px, calc(100vw - 40px));
                max-width: min(290px, calc(100vw - 40px));
                max-height: 340px;
                padding: .35rem;
                border-radius: 12px;
            }

            .catalog-filter-custom.is-color .catalog-filter-custom-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .75rem;
                padding: .45rem;
                border-radius: 10px;
                font-size: .95rem;
            }

            .catalog-filter-color-item-content {
                display: flex;
                min-width: 0;
                flex: 1 1 auto;
                align-items: center;
                gap: .8rem;
            }

            .catalog-filter-color-swatch {
                display: inline-flex;
                width: 32px;
                height: 32px;
                flex-shrink: 0;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background: #fff;
                background-position: center;
                background-size: cover;
            }

            .catalog-filter-color-label {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: .95rem;
                font-weight: 500;
                color: #111827;
            }

            .catalog-filter-color-count {
                display: inline-flex;
                min-width: 28px;
                height: 28px;
                flex-shrink: 0;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                background: #f1f5f9;
                padding: 0 .5rem;
                font-size: .82rem;
                font-weight: 700;
                color: #6b7280;
            }

            .catalog-filter-custom.is-color .catalog-filter-custom-item.is-selected {
                background: #f8fafc;
                box-shadow: inset 0 0 0 1px #d1d5db;
                font-weight: 500;
            }

            .catalog-filter-custom.is-composition .catalog-filter-custom-list {
                max-width: min(420px, calc(100vw - 40px));
            }

            @media (min-width: 1280px) {
                .catalog-filter-composition {
                    width: 124px;
                }

                .catalog-filter-color {
                    width: 124px;
                }
            }
        }
    </style>

    <section class="px-3 sm:px-4 lg:px-6">
        <div class="front-soft-hero px-4 py-4 text-center sm:px-6 sm:py-5">
        <nav aria-label="Breadcrumb" class="mb-2">
            <ol class="flex flex-wrap items-center justify-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:gap-2">
                <li>
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center text-slate-500 hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a>
                </li>
                @foreach (($breadcrumbCategories ?? collect()) as $breadcrumbCategory)
                    @php
                        $breadcrumbTranslation = $breadcrumbCategory->translations->firstWhere('locale', $locale)
                            ?? $breadcrumbCategory->translations->firstWhere('locale', $fallbackLocale);
                        $breadcrumbLabel = $breadcrumbTranslation?->name ?? $breadcrumbCategory->code;
                    @endphp
                    <li class="text-slate-400">/</li>
                    @if ($loop->last)
                        <li class="text-slate-700">{{ $breadcrumbLabel }}</li>
                    @else
                        <li>
                            <a href="{{ route('categories.show', ['slug' => $breadcrumbTranslation?->slug ?? $breadcrumbCategory->id]) }}" class="hover:text-slate-700">{{ $breadcrumbLabel }}</a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $translation?->name ?? $category->code }}</h1>
        @if ($showCategoryProducts)
            <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">{{ (int) $products->total() }} {{ __('ui.cart.summary.total') }}</p>
        @endif
        </div>
    </section>

    @if ($topBlocks->isNotEmpty())
        <section class="mb-8 px-3 sm:px-4 lg:px-6">
            @include('components.content-placement', ['items' => $topBlocks])
        </section>
    @endif

    @if ($showCategoryFilters)
        <section class="relative z-20 px-3 pt-3 pb-4 sm:px-4 lg:px-6">
            <div class="catalog-filter-sticky-shell" data-sticky-filter-shell>
            <div class="catalog-filter-sticky-bar border-b border-slate-200/90 pb-4" data-sticky-filter-bar>
        <div class="catalog-mobile-filter-rail max-[1024px]:block min-[1025px]:hidden" data-mobile-filter-root>
            <div class="catalog-mobile-filter-toolbar">
                <button
                    type="button"
                    class="catalog-mobile-filter-trigger"
                    data-mobile-filter-toggle
                    aria-expanded="false"
                    aria-controls="category-mobile-filter-drawer"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 7h16M7 12h10M10 17h4"></path>
                    </svg>
                    <span class="catalog-mobile-filter-trigger-label">{{ __('ui.shop.filters.open') }}</span>
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="catalog-mobile-filter-reset" aria-label="{{ __('ui.shop.filters.reset') }}">
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5"></path>
                        </svg>
                        <span>{{ __('ui.shop.filters.reset') }}</span>
                    </a>
                @endif
                <div class="catalog-mobile-grid-group">
                @foreach ([1, 2] as $cols)
                    <a
                        href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id] + array_merge(request()->query(), ['cols' => $cols])) }}"
                        class="catalog-mobile-grid-toggle {{ $mobileCols === $cols ? 'is-active' : '' }}"
                        aria-label="{{ __('ui.shop.filters.grid') }} {{ $cols }}"
                    >
                        <span class="flex h-4 items-stretch gap-[2px]">
                            @for ($i = 0; $i < $cols; $i++)
                                <span class="h-4 w-[3px] border border-current/80"></span>
                            @endfor
                        </span>
                    </a>
                @endforeach
                </div>
            </div>
            <div class="catalog-mobile-filter-drawer hidden" data-mobile-filter-drawer id="category-mobile-filter-drawer">
                <button type="button" class="catalog-mobile-filter-drawer-backdrop" data-mobile-filter-close aria-label="{{ __('ui.front.desktop.close_navigation') }}"></button>
                <div class="catalog-mobile-filter-drawer-panel">
                    <div class="catalog-mobile-filter-drawer-header">
                        <h2 class="catalog-mobile-filter-drawer-title">{{ __('ui.shop.filters.open') }}</h2>
                        <button type="button" class="catalog-mobile-filter-drawer-close" data-mobile-filter-close aria-label="{{ __('ui.front.desktop.close_navigation') }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18"></path>
                            </svg>
                        </button>
                    </div>
                    <form method="GET" action="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="catalog-mobile-filter-panel grid auto-rows-min gap-4" data-mobile-filter-panel id="category-mobile-filter-panel">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        @if ($hasSubcategories)
                            <div class="catalog-mobile-filter-group">
                                <select
                                    id="shop-category-mobile"
                                    class="catalog-mobile-filter-select"
                                    aria-label="{{ __('ui.shop.filters.category') }}"
                                    data-category-redirect
                                    data-default-url="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}"
                                >
                                    <option value="" data-url="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" @selected(true)>{{ __('ui.shop.filters.category') }}</option>
                                    @foreach (($subcategories ?? collect()) as $subCategory)
                                        @php
                                            $subCategoryTranslation = $subCategory->translations->firstWhere('locale', $locale)
                                                ?? $subCategory->translations->firstWhere('locale', $fallbackLocale);
                                        @endphp
                                        <option value="{{ $subCategoryTranslation?->slug }}" data-url="{{ $subCategoryTranslation?->slug ? route('categories.show', ['slug' => $subCategoryTranslation->slug]) : route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}">
                                            {{ $subCategoryTranslation?->name ?? $subCategory->code }} ({{ $subCategory->products_count }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if ($showManufacturers)
                            <div class="catalog-mobile-filter-group">
                                <select id="shop-manufacturer-mobile" name="manufacturer" class="catalog-mobile-filter-select" aria-label="{{ __('ui.shop.filters.manufacturer') }}" data-auto-submit-filter>
                                    <option value="">{{ __('ui.shop.filters.manufacturer') }}</option>
                                    @foreach ($manufacturers as $manufacturer)
                                        @php
                                            $manufacturerTranslation = $manufacturer->translations->firstWhere('locale', $locale)
                                                ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                                        @endphp
                                        <option value="{{ $manufacturerTranslation?->slug }}" @selected(($filters['manufacturer'] ?? '') === ($manufacturerTranslation?->slug ?? ''))>
                                            {{ $manufacturerTranslation?->name ?? $manufacturer->code }} ({{ $manufacturer->products_count }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @foreach ($orderedCategoryFilters as $filterOption)
                            <div class="catalog-mobile-filter-group">
                                <select name="{{ $filterOption['query_key'] }}" class="catalog-mobile-filter-select" aria-label="{{ $filterOption['label'] }}" data-auto-submit-filter>
                                    <option value="">{{ $filterOption['label'] }}</option>
                                    @foreach (($filterOption['values'] ?? []) as $value)
                                        <option value="{{ $value['id'] }}" @selected((string) ($filterOption['selected'] ?? '') === (string) $value['id'])>
                                            {{ $value['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="catalog-mobile-filter-group">
                            <div class="catalog-mobile-price-card">
                                <span class="catalog-mobile-price-heading">{{ __('ui.shop.filters.price') }}</span>
                                <div
                                    data-price-range-root
                                    data-price-min-bound="{{ $priceSliderMin }}"
                                    data-price-max-bound="{{ $priceSliderMax }}"
                                >
                                    <input type="hidden" name="price_min" value="{{ $priceMinValue }}" data-price-range-hidden-min>
                                    <input type="hidden" name="price_max" value="{{ $priceMaxValue }}" data-price-range-hidden-max>
                                    <div class="catalog-price-range-values">
                                        <div class="catalog-price-range-value">
                                            <span class="catalog-price-range-value-label">{{ __('ui.shop.filters.price_from') }}</span>
                                            <span class="catalog-price-range-value-amount" data-price-range-current-min>{{ $priceSliderSelectedMin }} €</span>
                                        </div>
                                        <div class="catalog-price-range-value text-right">
                                            <span class="catalog-price-range-value-label">{{ __('ui.shop.filters.price_to') }}</span>
                                            <span class="catalog-price-range-value-amount" data-price-range-current-max>{{ $priceSliderSelectedMax }} €</span>
                                        </div>
                                    </div>
                                    <div class="catalog-price-range-slider">
                                        <div class="catalog-price-range-track"></div>
                                        <div class="catalog-price-range-progress" data-price-range-progress></div>
                                        <input type="range" min="{{ $priceSliderMin }}" max="{{ $priceSliderMax }}" step="1" value="{{ $priceSliderSelectedMin }}" data-price-range-min>
                                        <input type="range" min="{{ $priceSliderMin }}" max="{{ $priceSliderMax }}" step="1" value="{{ $priceSliderSelectedMax }}" data-price-range-max>
                                    </div>
                                    <div class="catalog-price-range-scale">
                                        <span>{{ $priceSliderMin }} €</span>
                                        <span>{{ $priceSliderMax }} €</span>
                                    </div>
                                    <div class="catalog-price-promo-toggle">
                                        <div class="catalog-price-promo-copy">
                                            <span class="catalog-price-promo-label">{{ __('ui.shop.filters.promotion_only') }}</span>
                                            <span class="catalog-price-promo-hint">{{ __('ui.shop.filters.promotion_only_hint') }}</span>
                                        </div>
                                        <label class="catalog-switch" aria-label="{{ __('ui.shop.filters.promotion_only') }}">
                                            <input type="checkbox" name="promo_only" value="1" @checked($promoOnlyEnabled) @disabled($promoToggleDisabled) data-price-range-promo>
                                            <span class="catalog-switch-track" aria-hidden="true"></span>
                                        </label>
                                    </div>
                                    <button type="button" class="catalog-price-reset" data-price-filter-reset @disabled(! $hasPricePanelFilter)>
                                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                            <path d="M8 2.25V.75M8 2.25A5.75 5.75 0 1 1 2.93 5.28M8 2.25L5.8 4.45"></path>
                                        </svg>
                                        <span>{{ __('ui.shop.filters.reset') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="catalog-mobile-filter-group">
                            <select id="shop-sort-mobile" name="sort" class="catalog-mobile-filter-select" aria-label="{{ __('ui.shop.filters.sort') }}" data-auto-submit-filter>
                                <option value="default" @selected(($filters['sort'] ?? 'default') === 'default')>{{ __('ui.shop.filters.default') }}</option>
                                <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>{{ __('ui.shop.filters.newest') }}</option>
                                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>{{ __('ui.shop.filters.oldest') }}</option>
                                <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>{{ __('ui.shop.filters.price_low') }}</option>
                                <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>{{ __('ui.shop.filters.price_high') }}</option>
                                <option value="stock_high" @selected(($filters['sort'] ?? '') === 'stock_high')>{{ __('ui.shop.filters.stock_high') }}</option>
                            </select>
                        </div>
                        <input type="hidden" name="cols" value="{{ $mobileCols }}">
                        @if ($hasActiveFilters)
                            <div class="catalog-mobile-reset-wrap" data-global-filter-reset>
                                <a href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="catalog-mobile-reset-link">
                                    <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                        <path d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5"></path>
                                    </svg>
                                    <span>{{ __('ui.shop.filters.reset') }}</span>
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="hidden gap-x-3 gap-y-2.5 min-[1025px]:flex min-[1025px]:flex-wrap min-[1025px]:items-end min-[1025px]:justify-start" data-desktop-filter-form>
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <div class="catalog-price-filter" data-price-filter-root>
                <button
                    type="button"
                    class="catalog-price-filter-toggle {{ $hasPricePanelFilter ? 'is-active' : '' }}"
                    data-price-filter-toggle
                    aria-expanded="false"
                >
                    <span>{{ $priceTriggerLabel }}</span>
                    <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 7.5L10 12.5L15 7.5"></path>
                    </svg>
                </button>
                <div class="catalog-price-filter-panel" data-price-filter-panel>
                    <div class="catalog-price-range-card">
                        <div
                            data-price-range-root
                            data-price-min-bound="{{ $priceSliderMin }}"
                            data-price-max-bound="{{ $priceSliderMax }}"
                        >
                            <input type="hidden" name="price_min" value="{{ $priceMinValue }}" data-price-range-hidden-min>
                            <input type="hidden" name="price_max" value="{{ $priceMaxValue }}" data-price-range-hidden-max>
                            <div class="catalog-price-range-values">
                                <div class="catalog-price-range-value">
                                    <span class="catalog-price-range-value-label">{{ __('ui.shop.filters.price_from') }}</span>
                                    <span class="catalog-price-range-value-amount" data-price-range-current-min>{{ $priceSliderSelectedMin }} €</span>
                                </div>
                                <div class="catalog-price-range-value text-right">
                                    <span class="catalog-price-range-value-label">{{ __('ui.shop.filters.price_to') }}</span>
                                    <span class="catalog-price-range-value-amount" data-price-range-current-max>{{ $priceSliderSelectedMax }} €</span>
                                </div>
                            </div>
                            <div class="catalog-price-range-slider">
                                <div class="catalog-price-range-track"></div>
                                <div class="catalog-price-range-progress" data-price-range-progress></div>
                                <input type="range" min="{{ $priceSliderMin }}" max="{{ $priceSliderMax }}" step="1" value="{{ $priceSliderSelectedMin }}" data-price-range-min>
                                <input type="range" min="{{ $priceSliderMin }}" max="{{ $priceSliderMax }}" step="1" value="{{ $priceSliderSelectedMax }}" data-price-range-max>
                            </div>
                            <div class="catalog-price-range-scale">
                                <span>{{ $priceSliderMin }} €</span>
                                <span>{{ $priceSliderMax }} €</span>
                            </div>
                            <div class="catalog-price-promo-toggle">
                                <div class="catalog-price-promo-copy">
                                    <span class="catalog-price-promo-label">{{ __('ui.shop.filters.promotion_only') }}</span>
                                    <span class="catalog-price-promo-hint">{{ __('ui.shop.filters.promotion_only_hint') }}</span>
                                </div>
                                <label class="catalog-switch" aria-label="{{ __('ui.shop.filters.promotion_only') }}">
                                    <input type="checkbox" name="promo_only" value="1" @checked($promoOnlyEnabled) @disabled($promoToggleDisabled) data-price-range-promo>
                                    <span class="catalog-switch-track" aria-hidden="true"></span>
                                </label>
                            </div>
                            <button type="button" class="catalog-price-reset" data-price-filter-reset @disabled(! $hasPricePanelFilter)>
                                <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M8 2.25V.75M8 2.25A5.75 5.75 0 1 1 2.93 5.28M8 2.25L5.8 4.45"></path>
                                </svg>
                                <span>{{ __('ui.shop.filters.reset') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @if ($hasSubcategories)
                <div class="w-[108px] xl:w-[118px]">
                    <select
                        id="shop-category"
                        class="catalog-filter-select catalog-filter-inline-select h-9 w-full rounded-none border-slate-300 text-sm"
                        data-category-redirect
                        data-default-url="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}"
                    >
                        <option value="" data-url="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" @selected(true)>{{ __('ui.shop.filters.category') }}</option>
                        @foreach (($subcategories ?? collect()) as $subCategory)
                            @php
                                $subCategoryTranslation = $subCategory->translations->firstWhere('locale', $locale)
                                    ?? $subCategory->translations->firstWhere('locale', $fallbackLocale);
                            @endphp
                            <option value="{{ $subCategoryTranslation?->slug }}" data-url="{{ $subCategoryTranslation?->slug ? route('categories.show', ['slug' => $subCategoryTranslation->slug]) : route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}">
                                {{ $subCategoryTranslation?->name ?? $subCategory->code }} ({{ $subCategory->products_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($showManufacturers)
                <div class="w-[104px] xl:w-[116px]">
                    <select id="shop-manufacturer" name="manufacturer" class="catalog-filter-select catalog-filter-inline-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                        <option value="">{{ __('ui.shop.filters.manufacturer') }}</option>
                        @foreach ($manufacturers as $manufacturer)
                            @php
                                $manufacturerTranslation = $manufacturer->translations->firstWhere('locale', $locale)
                                    ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                            @endphp
                            <option value="{{ $manufacturerTranslation?->slug }}" @selected(($filters['manufacturer'] ?? '') === ($manufacturerTranslation?->slug ?? ''))>
                                {{ $manufacturerTranslation?->name ?? $manufacturer->code }} ({{ $manufacturer->products_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @foreach ($orderedCategoryFilters as $filterOption)
                @php
                    $isCompositionFilter = in_array((string) ($filterOption['query_key'] ?? ''), ['attr_sastav', 'attr_material'], true);
                    $isColorFilter = (string) ($filterOption['kind'] ?? 'default') === 'color';
                @endphp
                <div class="{{ $isCompositionFilter ? 'catalog-filter-composition' : ($isColorFilter ? 'catalog-filter-color' : 'w-[108px] xl:w-[118px]') }}">
                    <select name="{{ $filterOption['query_key'] }}" class="catalog-filter-select catalog-filter-inline-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter @if($isCompositionFilter) data-filter-kind="composition" @elseif($isColorFilter) data-filter-kind="color" @endif>
                        <option value="">{{ $filterOption['label'] }}</option>
                        @foreach (($filterOption['values'] ?? []) as $value)
                            <option value="{{ $value['id'] }}" @selected((string) ($filterOption['selected'] ?? '') === (string) $value['id']) @if($isColorFilter) data-filter-count="{{ (int) ($value['count'] ?? 0) }}" data-filter-swatch-style="{{ $value['swatch_style'] ?? '' }}" @if(!empty($value['swatch_image_url'])) data-filter-swatch="{{ $value['swatch_image_url'] }}" @endif @endif>
                                {{ $value['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <input type="hidden" name="cols" value="{{ (int) ($filters['cols'] ?? 4) }}">
            <div class="min-[1025px]:ml-auto flex items-center gap-2">
                <div class="catalog-filter-sort-wrap w-[132px] xl:w-[144px]">
                    <select id="shop-sort" name="sort" class="catalog-filter-select catalog-filter-inline-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                        <option value="default" @selected(($filters['sort'] ?? 'default') === 'default')>{{ __('ui.shop.filters.default') }}</option>
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>{{ __('ui.shop.filters.newest') }}</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>{{ __('ui.shop.filters.oldest') }}</option>
                        <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>{{ __('ui.shop.filters.price_low') }}</option>
                        <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>{{ __('ui.shop.filters.price_high') }}</option>
                        <option value="stock_high" @selected(($filters['sort'] ?? '') === 'stock_high')>{{ __('ui.shop.filters.stock_high') }}</option>
                    </select>
                </div>
                <div class="w-auto shrink-0">
                    <div class="flex items-center gap-2">
                        @foreach ([3, 4, 5] as $cols)
                            <a
                                href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id] + array_merge(request()->query(), ['cols' => $cols])) }}"
                                class="catalog-grid-toggle {{ $cols === 5 ? 'hidden 2xl:inline-flex' : 'inline-flex' }} {{ (int) ($filters['cols'] ?? 4) === $cols ? 'is-active' : '' }}"
                                aria-label="{{ __('ui.shop.filters.grid') }} {{ $cols }}"
                            >
                                <span class="flex h-4 items-stretch gap-[1px]">
                                    @for ($i = 0; $i < $cols; $i++)
                                        <span class="h-4 w-[2px] border border-current/80"></span>
                                    @endfor
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
                @if ($hasActiveFilters)
                    <div class="flex items-center" data-global-filter-reset>
                        <a href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="catalog-reset-button whitespace-nowrap">
                            <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5"></path>
                            </svg>
                            <span>{{ __('ui.shop.filters.reset') }}</span>
                        </a>
                    </div>
                @endif
            </div>
        </form>
            </div>
            </div>
        </section>
    @endif

    @if ($showCategoryProducts)
        <section class="px-3 pt-3 pb-6 sm:px-4 lg:px-6">
            @if ($products->isEmpty())
                <div class="border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('ui.category.empty') }}</div>
            @else
                <div class="{{ $gridClass }}" data-catalog-grid>
                    @foreach ($products as $product)
                        @include('front.desktop.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale, 'flat' => true])
                    @endforeach
                </div>

                @if ($useAsyncPagination)
                    <div class="mt-8 flex items-center justify-center" data-catalog-load-more-root data-catalog-load-mode="{{ $isInfinitePagination ? 'infinite' : 'load_more' }}">
                        @if ($products->hasMorePages())
                            <a href="{{ $products->nextPageUrl() }}" class="hidden" data-catalog-next-url>{{ __('ui.shop.load_more') }}</a>
                            <button type="button" class="{{ $isInfinitePagination ? 'hidden' : 'inline-flex' }} h-10 items-center justify-center border border-slate-900 bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-700" data-catalog-load-more-button>
                                {{ __('ui.shop.load_more') }}
                            </button>
                        @endif
                        <div class="h-8 flex items-center justify-center">
                            <div class="hidden inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500" data-catalog-load-more-loader data-loading-label="{{ __('ui.shop.loading') }}" data-end-label="{{ __('ui.shop.end_of_list') }}">
                                <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-400 border-t-transparent" data-catalog-load-more-spinner aria-hidden="true"></span>
                                <span data-catalog-load-more-loader-text>{{ __('ui.shop.loading') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sr-only" data-catalog-pagination-seo aria-hidden="true">
                        {{ $products->onEachSide(0)->links() }}
                    </div>
                    <noscript>
                        <div class="mt-14">
                            {{ $products->onEachSide(0)->links() }}
                        </div>
                    </noscript>
                @else
                    <div class="mt-14" data-catalog-pagination>
                        {{ $products->onEachSide(0)->links() }}
                    </div>
                @endif
            @endif
        </section>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10 px-3 sm:px-4 lg:px-6">
            @include('components.content-placement', ['items' => $bottomBlocks])
        </section>
    @endif
@endsection

@push('scripts')
    @if ($showCategoryFilters)
        <script defer src="{{ asset('front-theme/scripts/category-select-redirect.js') }}?v={{ filemtime(public_path('front-theme/scripts/category-select-redirect.js')) }}"></script>
        <script defer src="{{ asset('front-theme/scripts/catalog-custom-select.js') }}?v={{ filemtime(public_path('front-theme/scripts/catalog-custom-select.js')) }}"></script>
    @endif
    @if ($showCategoryProducts && $useAsyncPagination)
        <script defer src="{{ asset('front-theme/scripts/catalog-load-more.js') }}?v={{ filemtime(public_path('front-theme/scripts/catalog-load-more.js')) }}"></script>
    @endif
    <script>
        (() => {
            const buildCleanFormUrl = (form) => {
                const url = new URL(form.action, window.location.origin);
                const colsField = form.querySelector('[name="cols"]');
                const colsValue = String(colsField?.value || '').trim();

                if (colsValue !== '') {
                    url.searchParams.set('cols', colsValue);
                }

                return url.toString();
            };

            const hasActiveFilterFields = (form) => Array.from(form.elements).some((field) => {
                if (!field || !field.name || field.disabled) {
                    return false;
                }

                if (field.name === 'cols') {
                    return false;
                }

                if (field.type === 'checkbox' || field.type === 'radio') {
                    return field.checked;
                }

                const value = String(field.value || '').trim();
                if (value === '') {
                    return false;
                }

                if (field.name === 'sort') {
                    return value !== 'default';
                }

                return true;
            });

            const updateGlobalResetVisibility = (form) => {
                if (!form) {
                    return;
                }

                const hasActiveFilters = hasActiveFilterFields(form);
                form.querySelectorAll('[data-global-filter-reset]').forEach((node) => {
                    node.classList.toggle('hidden', !hasActiveFilters);
                });
            };

            const submitFilterForm = (form) => {
                if (!form) {
                    return;
                }

                updateGlobalResetVisibility(form);

                if (!hasActiveFilterFields(form)) {
                    window.location.assign(buildCleanFormUrl(form));
                    return;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            };

            const closePricePanel = (root) => {
                if (!root) {
                    return;
                }

                root.classList.remove('is-open');
                const toggle = root.querySelector('[data-price-filter-toggle]');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            };

            const closeAllPricePanels = (exceptRoot = null) => {
                document.querySelectorAll('[data-price-filter-root].is-open').forEach((root) => {
                    if (root !== exceptRoot) {
                        closePricePanel(root);
                    }
                });
            };

            const closeAllCustomSelects = () => {
                document.querySelectorAll('[data-custom-select].is-open').forEach((root) => {
                    root.classList.remove('is-open');
                    const button = root.querySelector('[data-custom-select-button]');
                    if (button) {
                        button.setAttribute('aria-expanded', 'false');
                    }
                });
            };

            const initPriceRange = (root) => {
                if (root.dataset.priceRangeInit === '1') {
                    return;
                }

                root.dataset.priceRangeInit = '1';

                const form = root.closest('form');
                const minRange = root.querySelector('[data-price-range-min]');
                const maxRange = root.querySelector('[data-price-range-max]');
                const hiddenMin = root.querySelector('[data-price-range-hidden-min]');
                const hiddenMax = root.querySelector('[data-price-range-hidden-max]');
                const currentMin = root.querySelector('[data-price-range-current-min]');
                const currentMax = root.querySelector('[data-price-range-current-max]');
                const progress = root.querySelector('[data-price-range-progress]');
                const promoToggle = root.querySelector('[data-price-range-promo]');
                const resetButton = root.querySelector('[data-price-filter-reset]');
                const desktopPriceFilter = root.closest('[data-price-filter-root]');
                const desktopPriceToggle = desktopPriceFilter?.querySelector('[data-price-filter-toggle]');

                if (!form || !minRange || !maxRange || !hiddenMin || !hiddenMax || !currentMin || !currentMax || !progress) {
                    return;
                }

                const minBound = Number(root.dataset.priceMinBound || minRange.min || 0);
                const maxBound = Number(root.dataset.priceMaxBound || maxRange.max || minBound);
                const totalRange = Math.max(1, maxBound - minBound);

                const formatPrice = (value) => `${Math.round(value)} €`;
                const setActiveState = () => {
                    const hasActivePriceFilter = hiddenMin.value !== '' || hiddenMax.value !== '' || Boolean(promoToggle?.checked);
                    if (desktopPriceToggle) {
                        desktopPriceToggle.classList.toggle('is-active', hasActivePriceFilter);
                    }
                    if (resetButton) {
                        resetButton.disabled = !hasActivePriceFilter;
                    }
                    updateGlobalResetVisibility(form);
                };

                const normalizePair = () => {
                    let minValue = Number(minRange.value || minBound);
                    let maxValue = Number(maxRange.value || maxBound);

                    minValue = Math.max(minBound, Math.min(maxBound, minValue));
                    maxValue = Math.max(minBound, Math.min(maxBound, maxValue));

                    if (minValue > maxValue) {
                        if (document.activeElement === minRange) {
                            maxValue = minValue;
                        } else {
                            minValue = maxValue;
                        }
                    }

                    minRange.value = String(minValue);
                    maxRange.value = String(maxValue);

                    return { minValue, maxValue };
                };

                const syncRangeState = () => {
                    const { minValue, maxValue } = normalizePair();
                    const left = ((minValue - minBound) / totalRange) * 100;
                    const width = ((maxValue - minValue) / totalRange) * 100;

                    hiddenMin.value = minValue <= minBound ? '' : String(minValue);
                    hiddenMax.value = maxValue >= maxBound ? '' : String(maxValue);
                    currentMin.textContent = formatPrice(minValue);
                    currentMax.textContent = formatPrice(maxValue);
                    progress.style.left = `${left}%`;
                    progress.style.width = `${width}%`;

                    setActiveState();
                };

                minRange.addEventListener('input', syncRangeState);
                maxRange.addEventListener('input', syncRangeState);
                minRange.addEventListener('change', () => {
                    syncRangeState();
                    submitFilterForm(form);
                });
                maxRange.addEventListener('change', () => {
                    syncRangeState();
                    submitFilterForm(form);
                });

                if (promoToggle) {
                    promoToggle.addEventListener('change', () => {
                        setActiveState();
                        submitFilterForm(form);
                    });
                }

                if (resetButton) {
                    resetButton.addEventListener('click', () => {
                        minRange.value = String(minBound);
                        maxRange.value = String(maxBound);
                        if (promoToggle) {
                            promoToggle.checked = false;
                        }
                        syncRangeState();
                        closePricePanel(root.closest('[data-price-filter-root]'));
                        submitFilterForm(form);
                    });
                }

                syncRangeState();
            };

            const initStickyFilterBar = () => {
                const shell = document.querySelector('[data-sticky-filter-shell]');
                const bar = shell?.querySelector('[data-sticky-filter-bar]');

                if (!shell || !bar || shell.dataset.stickyInit === '1') {
                    return;
                }

                shell.dataset.stickyInit = '1';

                let rafId = 0;
                const readStickyOffset = () => {
                    const rootStyles = window.getComputedStyle(document.documentElement);
                    const cssValue = parseFloat(rootStyles.getPropertyValue('--site-header-bottom'));
                    if (Number.isFinite(cssValue) && cssValue > 0) {
                        return cssValue;
                    }

                    const header = document.querySelector('.site-main-header');
                    if (header instanceof HTMLElement) {
                        return Math.max(0, header.getBoundingClientRect().bottom);
                    }

                    return 60;
                };

                const applyStickyState = () => {
                    rafId = 0;

                    const stickyOffset = readStickyOffset();
                    const shellRect = shell.getBoundingClientRect();
                    const barRect = bar.getBoundingClientRect();
                    const shouldPin = shellRect.top <= stickyOffset;

                    if (!shouldPin) {
                        shell.classList.remove('is-pinned');
                        shell.style.removeProperty('--catalog-sticky-height');
                        shell.style.removeProperty('--catalog-sticky-top');
                        shell.style.removeProperty('--catalog-sticky-left');
                        shell.style.removeProperty('--catalog-sticky-width');
                        return;
                    }

                    shell.style.setProperty('--catalog-sticky-height', `${Math.ceil(barRect.height)}px`);
                    shell.style.setProperty('--catalog-sticky-top', `${Math.round(stickyOffset)}px`);
                    shell.style.setProperty('--catalog-sticky-left', `${Math.round(shellRect.left)}px`);
                    shell.style.setProperty('--catalog-sticky-width', `${Math.round(shellRect.width)}px`);
                    shell.classList.add('is-pinned');
                };

                const requestApply = () => {
                    if (rafId) {
                        return;
                    }

                    rafId = window.requestAnimationFrame(applyStickyState);
                };

                requestApply();
                window.addEventListener('scroll', requestApply, { passive: true });
                window.addEventListener('resize', requestApply);
            };

            const init = () => {
                document.querySelectorAll('[data-mobile-filter-root]').forEach((root) => {
                    if (root.dataset.mobileFilterInit === '1') {
                        return;
                    }
                    root.dataset.mobileFilterInit = '1';
                    const toggle = root.querySelector('[data-mobile-filter-toggle]');
                    const drawer = root.querySelector('[data-mobile-filter-drawer]');
                    const closeButtons = root.querySelectorAll('[data-mobile-filter-close]');
                    if (!toggle || !drawer) {
                        return;
                    }

                    if (drawer.dataset.mobileFilterMounted !== '1') {
                        drawer.dataset.mobileFilterMounted = '1';
                        document.body.appendChild(drawer);
                    }

                    const openDrawer = () => {
                        drawer.classList.remove('hidden');
                        drawer.classList.add('flex');
                        toggle.setAttribute('aria-expanded', 'true');
                        root.classList.add('is-open');
                        document.body.classList.add('overflow-hidden', 'desktop-mobile-filter-open');
                    };

                    const closeDrawer = () => {
                        drawer.classList.add('hidden');
                        drawer.classList.remove('flex');
                        toggle.setAttribute('aria-expanded', 'false');
                        root.classList.remove('is-open');
                        document.body.classList.remove('desktop-mobile-filter-open');
                        if (!document.body.classList.contains('desktop-mobile-menu-open')) {
                            document.body.classList.remove('overflow-hidden');
                        }
                    };

                    toggle.addEventListener('click', () => {
                        if (drawer.classList.contains('hidden')) {
                            openDrawer();
                            return;
                        }

                        closeDrawer();
                    });

                    closeButtons.forEach((button) => {
                        button.addEventListener('click', closeDrawer);
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && !drawer.classList.contains('hidden')) {
                            closeDrawer();
                        }
                    });

                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1025 && !drawer.classList.contains('hidden')) {
                            closeDrawer();
                        }
                    });
                });

                document.querySelectorAll('[data-auto-submit-filter]').forEach((select) => {
                    if (select.dataset.autoSortInit === '1') {
                        return;
                    }
                    select.dataset.autoSortInit = '1';
                    select.addEventListener('change', () => {
                        const form = select.closest('form');
                        if (!form) {
                            return;
                        }
                        updateGlobalResetVisibility(form);
                        submitFilterForm(form);
                    });
                });

                document.querySelectorAll('form[data-desktop-filter-form], form[data-mobile-filter-panel]').forEach((form) => {
                    updateGlobalResetVisibility(form);
                });

                document.querySelectorAll('[data-price-range-root]').forEach((root) => {
                    initPriceRange(root);
                });

                document.querySelectorAll('[data-price-filter-root]').forEach((root) => {
                    if (root.dataset.priceFilterInit === '1') {
                        return;
                    }

                    root.dataset.priceFilterInit = '1';
                    const toggle = root.querySelector('[data-price-filter-toggle]');
                    const panel = root.querySelector('[data-price-filter-panel]');

                    if (!toggle || !panel) {
                        return;
                    }

                    const openPanel = () => {
                        root.classList.add('is-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    };

                    toggle.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        if (root.classList.contains('is-open')) {
                            closePricePanel(root);
                            return;
                        }
                        closeAllCustomSelects();
                        closeAllPricePanels(root);
                        openPanel();
                    });

                    panel.addEventListener('click', (event) => {
                        event.stopPropagation();
                    });
                });

                initStickyFilterBar();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init, { once: true });
                return;
            }
            init();

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-price-filter-root].is-open').forEach((root) => {
                    if (!root.contains(event.target)) {
                        closePricePanel(root);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }
                closeAllPricePanels();
            });
        })();
    </script>
@endpush
