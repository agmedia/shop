@extends('front.desktop.layouts.store')

@section('title', __('ui.shop.page_title'))
@section('main_class', 'w-full px-0 pt-3 pb-4 sm:pt-3 sm:pb-6')

@section('content')
    @php
        $showManufacturers = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
        $mobileDefaultCols = in_array((int) ($storeSettings['product']['mobile_default_cols'] ?? 2), [1, 2], true)
            ? (int) ($storeSettings['product']['mobile_default_cols'] ?? 2)
            : 2;
        $desktopDefaultCols = in_array((int) ($storeSettings['product']['desktop_default_cols'] ?? 4), [4, 5], true)
            ? (int) ($storeSettings['product']['desktop_default_cols'] ?? 4)
            : 4;
        $currentCols = (int) ($filters['cols'] ?? $desktopDefaultCols);
        $mobileCols = in_array($currentCols, [1, 2], true) ? $currentCols : $mobileDefaultCols;
        $paginationMode = (string) ($storeSettings['product']['catalog_pagination_mode'] ?? 'pagination');
        $useAsyncPagination = in_array($paginationMode, ['load_more', 'infinite'], true);
        $isInfinitePagination = $paginationMode === 'infinite';
        $desktopFilterSelectCount = 1 + ($showManufacturers ? 1 : 0) + count($optionFilters ?? []) + count($attributeFilters ?? []) + 1;
        $hasActiveFilters = trim((string) ($filters['q'] ?? '')) !== ''
            || trim((string) ($filters['category'] ?? '')) !== ''
            || trim((string) ($filters['manufacturer'] ?? '')) !== ''
            || collect(array_keys(request()->query()))
                ->contains(fn ($key): bool => str_starts_with((string) $key, 'opt_') || str_starts_with((string) $key, 'attr_'))
            || (string) ($filters['sort'] ?? 'newest') !== 'newest';
        $gridClass = match ($currentCols) {
            1 => 'grid grid-cols-1 gap-y-5',
            2 => 'grid grid-cols-2 gap-x-4 gap-y-5',
            3 => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 xl:grid-cols-3',
            5 => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5',
            default => 'grid gap-x-4 gap-y-5 '.($mobileDefaultCols === 2 ? 'grid-cols-2 ' : '').'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
        };
    @endphp

    <style>
        .catalog-filter-sticky-shell {
            position: relative;
            z-index: 30;
        }

        .catalog-filter-sticky-shell.is-pinned {
            min-height: var(--catalog-sticky-height, auto);
        }

        .catalog-filter-sticky-shell.is-pinned .catalog-filter-sticky-bar {
            position: fixed;
            top: var(--catalog-sticky-top, var(--site-header-bottom, 60px));
            left: var(--catalog-sticky-left, 0px);
            width: var(--catalog-sticky-width, 100%);
            z-index: 35;
            box-sizing: border-box;
            background: #fff;
            padding-top: 0;
            padding-bottom: .75rem !important;
            padding-left: 0;
            padding-right: 0;
            border-bottom-color: rgba(226, 232, 240, .9) !important;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
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

            .catalog-filter-sticky-shell {
                margin-left: 0;
                margin-right: 0;
            }

            .catalog-mobile-filter-rail {
                width: 100%;
                max-width: 100%;
            }

            .catalog-filter-sticky-bar {
                background: #fff;
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

            .catalog-mobile-filter-panel {
                flex: 1 1 auto;
                overflow-y: auto;
                margin-top: 0;
                border: 0;
                background: #fff;
                padding: 1rem;
                align-content: flex-start;
            }
        }

        @media (min-width: 768px) {
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

            .catalog-filter-custom-item:hover {
                background: #f8fafc;
            }

            .catalog-filter-custom-item.is-selected {
                background: #edf2f8;
                color: #0f172a;
                font-weight: 600;
                box-shadow: inset 3px 0 0 #0f172a;
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
        }
    </style>

    <section class="px-3 sm:px-4 lg:px-6">
        <div class="front-soft-hero px-4 py-4 text-center sm:px-6 sm:py-5">
        <nav aria-label="Breadcrumb" class="mb-2">
            <ol class="inline-flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                <li>
                    <a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a>
                </li>
                <li class="text-slate-400">/</li>
                <li class="text-slate-700">{{ __('ui.shop.page_title') }}</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('ui.shop.title') }}</h1>
        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">{{ (int) $products->total() }} {{ __('ui.cart.summary.total') }}</p>
        </div>
    </section>

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
                    aria-controls="shop-mobile-filter-drawer"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 7h16M7 12h10M10 17h4"></path>
                    </svg>
                    <span class="catalog-mobile-filter-trigger-label">{{ __('ui.shop.filters.open') }}</span>
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('shop.index') }}" class="catalog-mobile-filter-reset" aria-label="{{ __('ui.shop.filters.reset') }}">
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5"></path>
                        </svg>
                        <span>{{ __('ui.shop.filters.reset') }}</span>
                    </a>
                @endif
                <div class="catalog-mobile-grid-group">
                @foreach ([1, 2] as $cols)
                    <a
                        href="{{ route('shop.index', array_merge(request()->query(), ['cols' => $cols])) }}"
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
            <div class="catalog-mobile-filter-drawer hidden" data-mobile-filter-drawer id="shop-mobile-filter-drawer">
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
                    <form method="GET" action="{{ route('shop.index') }}" class="catalog-mobile-filter-panel grid auto-rows-min gap-3" data-mobile-filter-panel id="shop-mobile-filter-panel">
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <div>
                            <label for="shop-category-mobile" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.shop.filters.category') }}</label>
                            <select
                                id="shop-category-mobile"
                                class="h-[42px] w-full rounded-none border-slate-300 text-sm"
                                data-category-redirect
                                data-default-url="{{ route('shop.index') }}"
                            >
                                <option value="" data-url="{{ route('shop.index') }}" @selected(($filters['category'] ?? '') === '')>{{ __('ui.shop.filters.all_categories') }}</option>
                                @foreach ($categories as $category)
                                    @php
                                        $translation = $category->translations->firstWhere('locale', $locale)
                                            ?? $category->translations->firstWhere('locale', $fallbackLocale);
                                    @endphp
                                    <option value="{{ $translation?->slug }}" data-url="{{ $translation?->slug ? route('categories.show', ['slug' => $translation->slug]) : route('shop.index') }}" @selected(($filters['category'] ?? '') === ($translation?->slug ?? ''))>
                                        {{ $translation?->name ?? $category->code }} ({{ $category->products_count }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($showManufacturers)
                            <div>
                                <label for="shop-manufacturer-mobile" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.shop.filters.manufacturer') }}</label>
                                <select id="shop-manufacturer-mobile" name="manufacturer" class="h-[42px] w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                                    <option value="">{{ __('ui.shop.filters.all_manufacturers') }}</option>
                                    @foreach ($manufacturers as $manufacturer)
                                        @php
                                            $translation = $manufacturer->translations->firstWhere('locale', $locale)
                                                ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                                        @endphp
                                        <option value="{{ $translation?->slug }}" @selected($filters['manufacturer'] === ($translation?->slug ?? ''))>
                                            {{ $translation?->name ?? $manufacturer->code }} ({{ $manufacturer->products_count }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @foreach (($optionFilters ?? []) as $filterOption)
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $filterOption['label'] }}</label>
                                <select name="{{ $filterOption['query_key'] }}" class="h-[42px] w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                                    <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                    @foreach (($filterOption['values'] ?? []) as $value)
                                        <option value="{{ $value['id'] }}" @selected((string) ($filterOption['selected'] ?? '') === (string) $value['id'])>
                                            {{ $value['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        @foreach (($attributeFilters ?? []) as $attributeFilter)
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $attributeFilter['label'] }}</label>
                                <select name="{{ $attributeFilter['query_key'] }}" class="h-[42px] w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                                    <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                                    @foreach (($attributeFilter['values'] ?? []) as $value)
                                        <option value="{{ $value['id'] }}" @selected((string) ($attributeFilter['selected'] ?? '') === (string) $value['id'])>
                                            {{ $value['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div>
                            <label for="shop-sort-mobile" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.shop.filters.sort') }}</label>
                            <select id="shop-sort-mobile" name="sort" class="catalog-filter-sort h-[42px] w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                                <option value="newest" @selected($filters['sort'] === 'newest')>{{ __('ui.shop.filters.newest') }}</option>
                                <option value="oldest" @selected($filters['sort'] === 'oldest')>{{ __('ui.shop.filters.oldest') }}</option>
                                <option value="price_low" @selected($filters['sort'] === 'price_low')>{{ __('ui.shop.filters.price_low') }}</option>
                                <option value="price_high" @selected($filters['sort'] === 'price_high')>{{ __('ui.shop.filters.price_high') }}</option>
                                <option value="stock_high" @selected($filters['sort'] === 'stock_high')>{{ __('ui.shop.filters.stock_high') }}</option>
                            </select>
                        </div>
                        <input type="hidden" name="cols" value="{{ $mobileCols }}">
                        @if ($hasActiveFilters)
                            <div class="flex items-end justify-end">
                                <a href="{{ route('shop.index') }}" class="inline-flex h-[42px] items-center justify-center gap-2 whitespace-nowrap border border-rose-600 px-4 text-sm font-semibold text-rose-600 hover:bg-rose-50">
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

        <form method="GET" action="{{ route('shop.index') }}" class="hidden gap-x-3 gap-y-2.5 min-[1025px]:flex min-[1025px]:flex-wrap min-[1025px]:items-end min-[1025px]:justify-start" data-desktop-filter-form>
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <div class="w-[190px] xl:w-[210px]">
                <label for="shop-category" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ui.shop.filters.category') }}</label>
                <select
                    id="shop-category"
                    class="catalog-filter-select h-9 w-full rounded-none border-slate-300 text-sm"
                    data-category-redirect
                    data-default-url="{{ route('shop.index') }}"
                >
                    <option value="" data-url="{{ route('shop.index') }}" @selected(($filters['category'] ?? '') === '')>{{ __('ui.shop.filters.all_categories') }}</option>
                    @foreach ($categories as $category)
                        @php
                            $translation = $category->translations->firstWhere('locale', $locale)
                                ?? $category->translations->firstWhere('locale', $fallbackLocale);
                        @endphp
                        <option value="{{ $translation?->slug }}" data-url="{{ $translation?->slug ? route('categories.show', ['slug' => $translation->slug]) : route('shop.index') }}" @selected(($filters['category'] ?? '') === ($translation?->slug ?? ''))>
                            {{ $translation?->name ?? $category->code }} ({{ $category->products_count }})
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($showManufacturers)
                <div class="w-[190px] xl:w-[210px]">
                    <label for="shop-manufacturer" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ui.shop.filters.manufacturer') }}</label>
                    <select id="shop-manufacturer" name="manufacturer" class="catalog-filter-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                        <option value="">{{ __('ui.shop.filters.all_manufacturers') }}</option>
                        @foreach ($manufacturers as $manufacturer)
                            @php
                                $translation = $manufacturer->translations->firstWhere('locale', $locale)
                                    ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                            @endphp
                            <option value="{{ $translation?->slug }}" @selected($filters['manufacturer'] === ($translation?->slug ?? ''))>
                                {{ $translation?->name ?? $manufacturer->code }} ({{ $manufacturer->products_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @foreach (($optionFilters ?? []) as $filterOption)
                <div class="w-[190px] xl:w-[210px]">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $filterOption['label'] }}</label>
                    <select name="{{ $filterOption['query_key'] }}" class="catalog-filter-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter @if(($filterOption['kind'] ?? 'default') === 'color') data-filter-kind="color" @endif>
                        <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                        @foreach (($filterOption['values'] ?? []) as $value)
                            <option value="{{ $value['id'] }}" @selected((string) ($filterOption['selected'] ?? '') === (string) $value['id']) @if(($filterOption['kind'] ?? 'default') === 'color') data-filter-count="{{ (int) ($value['count'] ?? 0) }}" data-filter-swatch-style="{{ $value['swatch_style'] ?? '' }}" @if(!empty($value['swatch_image_url'])) data-filter-swatch="{{ $value['swatch_image_url'] }}" @endif @endif>
                                {{ $value['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            @foreach (($attributeFilters ?? []) as $attributeFilter)
                <div class="w-[190px] xl:w-[210px]">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $attributeFilter['label'] }}</label>
                    <select name="{{ $attributeFilter['query_key'] }}" class="catalog-filter-select h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                        <option value="">{{ __('ui.shop.filters.select_option') }}</option>
                        @foreach (($attributeFilter['values'] ?? []) as $value)
                            <option value="{{ $value['id'] }}" @selected((string) ($attributeFilter['selected'] ?? '') === (string) $value['id'])>
                                {{ $value['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <input type="hidden" name="cols" value="{{ (int) ($filters['cols'] ?? 4) }}">
            <div class="min-[1025px]:ml-auto flex items-end gap-3">
                <div class="w-[190px] xl:w-[210px]">
                    <label for="shop-sort" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ui.shop.filters.sort') }}</label>
                    <select id="shop-sort" name="sort" class="catalog-filter-select catalog-filter-sort h-9 w-full rounded-none border-slate-300 text-sm" data-auto-submit-filter>
                        <option value="newest" @selected($filters['sort'] === 'newest')>{{ __('ui.shop.filters.newest') }}</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>{{ __('ui.shop.filters.oldest') }}</option>
                        <option value="price_low" @selected($filters['sort'] === 'price_low')>{{ __('ui.shop.filters.price_low') }}</option>
                        <option value="price_high" @selected($filters['sort'] === 'price_high')>{{ __('ui.shop.filters.price_high') }}</option>
                        <option value="stock_high" @selected($filters['sort'] === 'stock_high')>{{ __('ui.shop.filters.stock_high') }}</option>
                    </select>
                </div>
                <div class="w-auto shrink-0">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ui.shop.filters.grid') }}</label>
                    <div class="flex h-9">
                        @foreach ([3, 4, 5] as $cols)
                            <a
                                href="{{ route('shop.index', array_merge(request()->query(), ['cols' => $cols])) }}"
                                class="{{ $cols === 5 ? 'hidden 2xl:inline-flex' : 'inline-flex' }} h-full w-9 items-center justify-center border border-slate-300 {{ (int) ($filters['cols'] ?? 4) === $cols ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-100' }}"
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
                    <div class="flex items-end">
                        <a href="{{ route('shop.index') }}" class="inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap border border-rose-600 px-3.5 text-xs font-semibold uppercase tracking-[0.12em] text-rose-600 hover:bg-rose-50">
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
    </section>

    <section class="px-3 pt-3 pb-6 sm:px-4 lg:px-6">
        @if ($products->isEmpty())
            <div class="border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                {{ __('ui.shop.empty') }}
            </div>
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
@endsection

@push('scripts')
    <script defer src="{{ asset('front-theme/scripts/category-select-redirect.js') }}?v={{ filemtime(public_path('front-theme/scripts/category-select-redirect.js')) }}"></script>
    <script defer src="{{ asset('front-theme/scripts/catalog-custom-select.js') }}?v={{ filemtime(public_path('front-theme/scripts/catalog-custom-select.js')) }}"></script>
    @if ($useAsyncPagination)
        <script defer src="{{ asset('front-theme/scripts/catalog-load-more.js') }}?v={{ filemtime(public_path('front-theme/scripts/catalog-load-more.js')) }}"></script>
    @endif
    <script>
        (() => {
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

                    if (!window.matchMedia('(max-width: 1024px)').matches) {
                        shell.classList.remove('is-pinned');
                        shell.style.removeProperty('--catalog-sticky-height');
                        shell.style.removeProperty('--catalog-sticky-top');
                        shell.style.removeProperty('--catalog-sticky-left');
                        shell.style.removeProperty('--catalog-sticky-width');
                        return;
                    }

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
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                            return;
                        }
                        form.submit();
                    });
                });

                initStickyFilterBar();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init, { once: true });
                return;
            }
            init();
        })();
    </script>
@endpush
