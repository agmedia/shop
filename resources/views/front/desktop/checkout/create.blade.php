@extends('front.desktop.layouts.store')

@section('title', __('ui.checkout.page_title'))

@section('content')
    @php
        $showShippingAddress = old('ship_to_different_address') === '1' || old('use_billing_for_shipping') === '0';
        $selectedShippingCode = (string) old('shipping_method_code', (string) ($checkoutTotals['shipping_method_code'] ?? $shippingMethods->first()?->code ?? ''));
        $selectedPaymentCode = (string) old('payment_method_code', (string) ($checkoutTotals['payment_method_code'] ?? $paymentMethods->first()?->code ?? ''));
        $selectedBoxNowLockerId = (string) old('shipping_boxnow_locker_id', '');
        $selectedBoxNowLockerName = (string) old('shipping_boxnow_locker_name', '');
        $selectedBoxNowAddressLine1 = (string) old('shipping_boxnow_address_line_1', '');
        $selectedBoxNowPostalCode = (string) old('shipping_boxnow_postal_code', '');
        $selectedBoxNowCity = (string) old('shipping_boxnow_city', '');
        $showLoginForm = old('checkout_login') === '1';
        $showRegisterPanel = old('register_account') === '1';
        $showR1Fields = old('want_r1_invoice') === '1'
            || old('billing_company', $prefill['billing']['company']) !== ''
            || old('billing_oib', $prefill['billing']['oib']) !== '';
        $ga4Items = collect($lines)->map(function (array $line) {
            $locale = app()->getLocale();
            $fallbackLocale = (string) config('app.locale');
            $product = $line['product'];
            $translation = $line['translation'];
            $manufacturerTranslation = $product->relationLoaded('manufacturer')
                ? ($product->manufacturer?->translations?->firstWhere('locale', $locale)
                    ?? $product->manufacturer?->translations?->firstWhere('locale', $fallbackLocale))
                : null;
            $categoryTranslation = $product->relationLoaded('categories')
                ? ($product->categories?->first()?->translations?->firstWhere('locale', $locale)
                    ?? $product->categories?->first()?->translations?->firstWhere('locale', $fallbackLocale))
                : null;

            return [
                'item_id' => (string) ($line['sku'] ?: $product->sku ?: $product->id),
                'item_name' => (string) ($translation?->name ?? $product->code),
                'item_brand' => (string) ($manufacturerTranslation?->name ?? ''),
                'item_category' => (string) ($categoryTranslation?->name ?? ''),
                'price' => round((float) ($line['display_unit_price'] ?? $line['unit_price'] ?? 0), 2),
                'quantity' => (int) ($line['quantity'] ?? 1),
            ];
        })->values()->all();
    @endphp

    <section class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.checkout.title') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('ui.checkout.subtitle') }}</p>
    </section>

    @guest
        <section class="mb-6 border border-slate-200 bg-white p-6">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" data-checkout-login-toggle @checked($showLoginForm)>
                {{ __('ui.checkout.login.toggle') }}
            </label>

            <div class="overflow-hidden transition-all duration-300" data-checkout-login-panel style="{{ $showLoginForm ? '' : 'max-height:0;opacity:0;' }}">
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('ui.checkout.login.title') }}</h2>
                    <form method="POST" action="{{ route('checkout.login') }}" class="mt-3 grid gap-3 md:grid-cols-2" novalidate>
                        @csrf
                        <input type="hidden" name="checkout_login" value="1">
                        <input type="hidden" name="intended" value="{{ route('checkout.create') }}">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.login.email') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                            @error('email')
                                <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.login.password') }}</label>
                            <input type="password" name="password" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                            @error('password')
                                <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2 flex items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="remember" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0">
                                {{ __('ui.checkout.login.remember') }}
                            </label>
                            <button type="submit" class="border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">{{ __('ui.checkout.login.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endguest

    <div class="mb-4 hidden border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" data-checkout-top-error></div>

    <form method="POST" action="{{ route('checkout.store') }}" class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(460px,1fr)]" data-address-autofill data-address-source="{{ $placesAssetUrl }}" data-checkout-form data-checkout-options-url="{{ route('checkout.options') }}" data-region-options='@json($regionOptionsByCountry, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' data-ga4-checkout-form data-ga4-currency="EUR" data-ga4-value="{{ number_format((float) ($checkoutTotals['grand_total'] ?? $summary['grand_total'] ?? 0), 2, '.', '') }}" data-ga4-items='@json($ga4Items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' data-success-fallback="{{ route('checkout.success.latest') }}" novalidate>
        @csrf

        <div class="space-y-6">
            <section class="border border-slate-200 bg-white p-6" data-address-scope="billing">
                <h2 class="text-xl font-bold text-slate-900">{{ __('ui.checkout.sections.customer') }} / {{ __('ui.checkout.sections.billing') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('ui.checkout.sections.basic_information') }}</p>

                <input type="hidden" name="customer_first_name" value="{{ old('customer_first_name', old('billing_first_name', $prefill['billing']['first_name'])) }}" data-customer-first-hidden>
                <input type="hidden" name="customer_last_name" value="{{ old('customer_last_name', old('billing_last_name', $prefill['billing']['last_name'])) }}" data-customer-last-hidden>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.first_name') }}</label>
                        <input type="text" name="billing_first_name" value="{{ old('billing_first_name', $prefill['billing']['first_name']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-billing-first required>
                        @error('billing_first_name')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.last_name') }}</label>
                        <input type="text" name="billing_last_name" value="{{ old('billing_last_name', $prefill['billing']['last_name']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-billing-last required>
                        @error('billing_last_name')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.email') }}</label>
                        <input type="email" name="customer_email" value="{{ old('customer_email', $prefill['email']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                        @error('customer_email')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.phone') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone', $prefill['phone']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                        @error('customer_phone')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="want_r1_invoice" value="1" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" data-r1-toggle @checked($showR1Fields)>
                            {{ __('ui.checkout.options.r1_invoice') }}
                        </label>
                    </div>
                    <div class="md:col-span-2 overflow-hidden transition-all duration-300" data-r1-panel style="{{ $showR1Fields ? '' : 'max-height:0;opacity:0;' }}">
                        <div class="grid gap-4 border-t border-slate-200 pt-4 md:grid-cols-2">
                            <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.company') }}</label><input type="text" name="billing_company" value="{{ old('billing_company', $prefill['billing']['company']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-r1-company></div>
                            <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.oib') }}</label><input type="text" name="billing_oib" value="{{ old('billing_oib', $prefill['billing']['oib']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-r1-oib></div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.address_line_1') }}</label>
                        <input type="text" name="billing_address_line_1" value="{{ old('billing_address_line_1', $prefill['billing']['address_line_1']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                        @error('billing_address_line_1')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.postal_code') }}</label>
                        <input type="text" name="billing_postal_code" value="{{ old('billing_postal_code', $prefill['billing']['postal_code']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-postal required>
                        @error('billing_postal_code')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.city') }}</label>
                        <input type="text" name="billing_city" value="{{ old('billing_city', $prefill['billing']['city']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-city required>
                        @error('billing_city')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" data-state-label data-label-hr="{{ __('ui.account.fields.county') }}" data-label-intl="{{ __('ui.account.fields.region') }}">{{ __('ui.account.fields.state') }}</label>
                        <select name="billing_state" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-county data-state-select data-option-hr="{{ __('ui.account.fields.select_county') }}" data-option-intl="{{ __('ui.account.fields.select_region') }}">
                            <option value="">{{ __('ui.account.fields.select_county') }}</option>
                            @foreach ($countyOptions as $countyOption)
                                <option value="{{ $countyOption }}" @selected(old('billing_state', $prefill['billing']['state']) === $countyOption)>{{ $countyOption }}</option>
                            @endforeach
                        </select>
                        <input type="text" value="{{ old('billing_state', $prefill['billing']['state']) }}" class="hidden h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-state-input data-placeholder-intl="{{ __('ui.account.fields.enter_region') }}" />
                        @error('billing_state')
                            <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.country_code') }}</label>
                        <select name="billing_country_code" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-country required>
                            @foreach ($countryOptions as $countryOption)
                                <option value="{{ $countryOption['code'] }}" @selected(old('billing_country_code', $prefill['billing']['country_code']) === $countryOption['code'])>{{ $countryOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @guest
                    <div class="mt-4 border-t border-slate-200 pt-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="register_account" value="1" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" data-register-account-toggle @checked($showRegisterPanel)>
                            {{ __('ui.checkout.options.register_account') }}
                        </label>

                        <div class="overflow-hidden transition-all duration-300" data-register-account-panel style="{{ $showRegisterPanel ? '' : 'max-height:0;opacity:0;' }}">
                            <div class="mt-4 grid gap-4 border-t border-slate-200 pt-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.register.password') }}</label>
                                    <input type="password" name="register_password" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-register-password>
                                    @error('register_password')
                                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.register.password_repeat') }}</label>
                                    <input type="password" name="register_password_confirmation" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-register-password-confirmation>
                                </div>
                            </div>
                        </div>
                    </div>
                @endguest
            </section>

            <section class="border border-slate-200 bg-white p-6" data-address-scope="shipping">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-bold text-slate-900">{{ __('ui.checkout.sections.shipping') }}</h2>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="ship_to_different_address" value="1" @checked($showShippingAddress) class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" data-ship-to-different>
                        {{ __('ui.checkout.options.ship_to_different_address') }}
                    </label>
                </div>

                <input type="hidden" name="use_billing_for_shipping" value="{{ $showShippingAddress ? '0' : '1' }}" data-use-billing-for-shipping>

                <div class="overflow-hidden transition-all duration-300" data-shipping-fields style="{{ $showShippingAddress ? '' : 'max-height:0;opacity:0;' }}">
                    <div class="mt-4 grid gap-4 border-t border-slate-200 pt-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.first_name') }}</label><input type="text" name="shipping_first_name" value="{{ old('shipping_first_name', $prefill['shipping']['first_name']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0"></div>
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.last_name') }}</label><input type="text" name="shipping_last_name" value="{{ old('shipping_last_name', $prefill['shipping']['last_name']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0"></div>
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.company') }}</label><input type="text" name="shipping_company" value="{{ old('shipping_company', $prefill['shipping']['company']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0"></div>
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.vat_id') }}</label><input type="text" name="shipping_vat_id" value="{{ old('shipping_vat_id', $prefill['shipping']['vat_id']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0"></div>
                        <div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.address_line_1') }}</label><input type="text" name="shipping_address_line_1" value="{{ old('shipping_address_line_1', $prefill['shipping']['address_line_1']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0"></div>
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.postal_code') }}</label><input type="text" name="shipping_postal_code" value="{{ old('shipping_postal_code', $prefill['shipping']['postal_code']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-postal></div>
                        <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.city') }}</label><input type="text" name="shipping_city" value="{{ old('shipping_city', $prefill['shipping']['city']) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-city></div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" data-state-label data-label-hr="{{ __('ui.account.fields.county') }}" data-label-intl="{{ __('ui.account.fields.region') }}">{{ __('ui.account.fields.state') }}</label>
                            <select name="shipping_state" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-county data-state-select data-option-hr="{{ __('ui.account.fields.select_county') }}" data-option-intl="{{ __('ui.account.fields.select_region') }}">
                                <option value="">{{ __('ui.account.fields.select_county') }}</option>
                                @foreach ($countyOptions as $countyOption)
                                    <option value="{{ $countyOption }}" @selected(old('shipping_state', $prefill['shipping']['state']) === $countyOption)>{{ $countyOption }}</option>
                                @endforeach
                            </select>
                            <input type="text" value="{{ old('shipping_state', $prefill['shipping']['state']) }}" class="hidden h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-state-input data-placeholder-intl="{{ __('ui.account.fields.enter_region') }}" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.account.fields.country_code') }}</label>
                            <select name="shipping_country_code" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" data-address-country>
                                @foreach ($countryOptions as $countryOption)
                                    <option value="{{ $countryOption['code'] }}" @selected(old('shipping_country_code', $prefill['shipping']['country_code']) === $countryOption['code'])>{{ $countryOption['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border border-slate-200 bg-white p-6">
                <h2 class="text-xl font-bold text-slate-900">{{ __('ui.checkout.sections.shipping_payment') }}</h2>

                <div class="mt-4 space-y-5">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.labels.shipping_method') }}</p>
                        <div class="grid gap-2" data-checkout-shipping-options>
                            @foreach ($shippingMethods as $method)
                                <label class="flex cursor-pointer items-center justify-between gap-3 border border-slate-300 px-3 py-2.5 text-sm hover:border-slate-500">
                                    <span class="inline-flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="shipping_method_code"
                                            value="{{ $method->code }}"
                                            data-is-boxnow="{{ in_array(strtolower((string) $method->code), ['boxnow', 'box_now'], true) ? '1' : '0' }}"
                                            data-boxnow-partner-id="{{ (string) ((is_array($method->settings ?? null) ? ($method->settings['boxnow_partner_id'] ?? '') : '') ?: '') }}"
                                            @checked($selectedShippingCode === (string) $method->code)
                                            class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0"
                                            required
                                        >
                                        <span class="font-semibold text-slate-900">{{ $method->name }}</span>
                                    </span>
                                    <span class="text-slate-600">{{ \App\Support\Currency::format((float) $method->price) }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-3 hidden border border-slate-200 bg-slate-50 p-3" data-boxnow-panel>
                            <input type="hidden" name="shipping_boxnow_locker_id" value="{{ $selectedBoxNowLockerId }}" data-boxnow-locker-id>
                            <input type="hidden" name="shipping_boxnow_locker_name" value="{{ $selectedBoxNowLockerName }}" data-boxnow-locker-name>
                            <input type="hidden" name="shipping_boxnow_address_line_1" value="{{ $selectedBoxNowAddressLine1 }}" data-boxnow-address-line-1>
                            <input type="hidden" name="shipping_boxnow_postal_code" value="{{ $selectedBoxNowPostalCode }}" data-boxnow-postal-code>
                            <input type="hidden" name="shipping_boxnow_city" value="{{ $selectedBoxNowCity }}" data-boxnow-city>

                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" class="border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-slate-700" data-boxnow-open>
                                    {{ __('ui.checkout.boxnow.select_locker') }}
                                </button>
                                <span class="text-sm text-slate-700" data-boxnow-selected>
                                    {{ $selectedBoxNowLockerId !== '' ? $selectedBoxNowLockerName.' ('.$selectedBoxNowLockerId.')' : __('ui.checkout.boxnow.no_locker_selected') }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600" data-boxnow-selected-address>
                                {{ trim($selectedBoxNowAddressLine1.', '.$selectedBoxNowPostalCode.' '.$selectedBoxNowCity, ', ') }}
                            </p>
                            @error('shipping_boxnow_locker_id')
                                <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.labels.payment_method') }}</p>
                        <div class="grid gap-2" data-checkout-payment-options>
                            @foreach ($paymentMethods as $method)
                                <label class="flex cursor-pointer items-center justify-between gap-3 border border-slate-300 px-3 py-2.5 text-sm hover:border-slate-500">
                                    <span class="inline-flex items-center gap-2">
                                        <input type="radio" name="payment_method_code" value="{{ $method->code }}" @checked($selectedPaymentCode === (string) $method->code) class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" required>
                                        @if (in_array(strtolower((string) $method->code), ['keks', 'keks_pay', 'kekspay'], true))
                                            <span class="inline-flex items-center gap-2">
                                                <img src="{{ asset('assets/payments/keks-logo.svg') }}" alt="KEKS Pay" class="h-5 w-auto max-w-[110px]">
                                                <span class="font-semibold text-slate-900">{{ $method->name }}</span>
                                            </span>
                                        @else
                                            <span class="font-semibold text-slate-900">{{ $method->name }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.fields.order_note') }}</label>
                    <textarea name="customer_note" rows="3" class="w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0">{{ old('customer_note') }}</textarea>
                </div>

                <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="accept_terms" value="1" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" required @checked((bool) old('accept_terms'))>
                    {{ __('ui.checkout.options.accept_terms') }}
                </label>
                @error('accept_terms')
                    <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror

                <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="newsletter_opt_in" value="1" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" @checked((bool) old('newsletter_opt_in', false))>
                    {{ __('ui.checkout.options.newsletter_opt_in') }}
                </label>
            </section>
        </div>

        <aside class="h-fit self-start border border-slate-200 bg-white p-6 shadow-sm xl:sticky xl:top-28">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('ui.checkout.summary_title') }}</h2>

            <div class="mt-4 space-y-3">
                @foreach ($lines as $line)
                    @php
                        $translation = $line['translation'];
                        $product = $line['product'];
                        $productImage = $product->getFirstMedia('product_main') ?? $product->getFirstMedia('product_gallery');
                        $productImageUrl = $productImage
                            ? ($productImage->hasGeneratedConversion('thumb_100x100') ? $productImage->getUrl('thumb_100x100') : $productImage->getUrl())
                            : null;
                    @endphp
                    <div class="flex items-start gap-3 border border-slate-200 p-3">
                        <div class="w-16 shrink-0 border border-slate-200 bg-slate-50 p-1">
                            @if ($productImageUrl)
                                <img
                                    src="{{ $productImageUrl }}"
                                    alt="{{ $translation?->name ?? $line['product']->code }}"
                                    class="h-auto w-full"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @else
                                <span class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase text-slate-500">{{ __('ui.product.no_image') }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-slate-900">{{ $translation?->name ?? $line['product']->code }}</div>
                            @if (!empty($line['sku']))
                                <div class="mt-0.5 text-xs text-slate-500">{{ __('ui.checkout.labels.sku') }}: {{ $line['sku'] }}</div>
                            @endif
                            @if (!empty($line['option_label']))
                                <div class="mt-0.5 text-xs text-slate-500">{{ $line['option_label'] }}</div>
                            @endif
                            <div class="mt-1 flex items-center justify-between text-sm">
                                <span class="text-slate-600">{{ __('ui.checkout.labels.qty') }} {{ $line['quantity'] }}</span>
                                <span class="font-semibold text-slate-900">{{ \App\Support\Currency::format((float) ($line['display_line_total'] ?? $line['line_total'])) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 border-t border-slate-200 pt-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">{{ __('ui.checkout.labels.items') }}</span>
                    <span class="font-semibold text-slate-900">{{ $summary['item_qty'] }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-slate-600">{{ __('ui.checkout.labels.subtotal') }}</span>
                    <span class="font-semibold text-slate-900">{{ \App\Support\Currency::format((float) $summary['subtotal']) }}</span>
                </div>
                @if ((float) ($summary['discount_total'] ?? 0) > 0)
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-slate-600">{{ __('ui.checkout.labels.discount') }}</span>
                        <span class="font-semibold text-emerald-700">-{{ \App\Support\Currency::format((float) $summary['discount_total']) }}</span>
                    </div>
                @endif
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-slate-600">{{ __('ui.checkout.labels.tax') }}</span>
                    <span class="font-semibold text-slate-900" data-summary-tax>{{ \App\Support\Currency::format((float) ($checkoutTotals['tax_total'] ?? $summary['tax_total'] ?? 0)) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between" data-summary-shipping-row>
                    <span class="text-slate-600">{{ __('ui.checkout.labels.shipping') }}</span>
                    <span class="font-semibold text-slate-900" data-summary-shipping>{{ \App\Support\Currency::format((float) ($checkoutTotals['shipping_total'] ?? 0)) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between {{ (float) ($checkoutTotals['payment_fee_total'] ?? 0) <= 0 ? 'hidden' : '' }}" data-summary-payment-fee-row>
                    <span class="text-slate-600">{{ __('ui.checkout.labels.payment_fee') }}</span>
                    <span class="font-semibold text-slate-900" data-summary-payment-fee>{{ \App\Support\Currency::format((float) ($checkoutTotals['payment_fee_total'] ?? 0)) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                    <span class="text-slate-900">{{ __('ui.checkout.labels.total') }}</span>
                    <span class="font-bold text-slate-900" data-summary-total>{{ \App\Support\Currency::format((float) ($checkoutTotals['grand_total'] ?? $summary['grand_total'] ?? 0)) }}</span>
                </div>
            </div>

            <button type="submit" class="mt-5 w-full border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">{{ __('ui.checkout.actions.place_order') }}</button>
        </aside>
    </form>

    <div id="boxnow-widget-root"></div>
@endsection

@push('scripts')
    <script defer src="{{ asset('front-theme/scripts/address-autofill.js') }}?v={{ filemtime(public_path('front-theme/scripts/address-autofill.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkoutForm = document.querySelector('[data-checkout-form]');
            const toggle = document.querySelector('[data-ship-to-different]');
            const shippingFields = document.querySelector('[data-shipping-fields]');
            const useBillingInput = document.querySelector('[data-use-billing-for-shipping]');
            const billingFirst = document.querySelector('[data-billing-first]');
            const billingLast = document.querySelector('[data-billing-last]');
            const customerFirstHidden = document.querySelector('[data-customer-first-hidden]');
            const customerLastHidden = document.querySelector('[data-customer-last-hidden]');
            const loginToggle = document.querySelector('[data-checkout-login-toggle]');
            const loginPanel = document.querySelector('[data-checkout-login-panel]');
            const registerToggle = document.querySelector('[data-register-account-toggle]');
            const registerPanel = document.querySelector('[data-register-account-panel]');
            const registerPassword = document.querySelector('[data-register-password]');
            const registerPasswordConfirmation = document.querySelector('[data-register-password-confirmation]');
            const r1Toggle = document.querySelector('[data-r1-toggle]');
            const r1Panel = document.querySelector('[data-r1-panel]');
            const r1Company = document.querySelector('[data-r1-company]');
            const r1Oib = document.querySelector('[data-r1-oib]');
            const optionsUrl = checkoutForm?.dataset.checkoutOptionsUrl || '';
            const regionOptionsByCountry = checkoutForm?.dataset.regionOptions ? JSON.parse(checkoutForm.dataset.regionOptions) : {};
            const shippingOptionsRoot = checkoutForm?.querySelector('[data-checkout-shipping-options]');
            const paymentOptionsRoot = checkoutForm?.querySelector('[data-checkout-payment-options]');
            const topErrorBox = document.querySelector('[data-checkout-top-error]');
            const boxNowPanel = checkoutForm?.querySelector('[data-boxnow-panel]');
            const boxNowOpenButton = checkoutForm?.querySelector('[data-boxnow-open]');
            const boxNowSelectedLabel = checkoutForm?.querySelector('[data-boxnow-selected]');
            const boxNowSelectedAddress = checkoutForm?.querySelector('[data-boxnow-selected-address]');
            const boxNowLockerId = checkoutForm?.querySelector('[data-boxnow-locker-id]');
            const boxNowLockerName = checkoutForm?.querySelector('[data-boxnow-locker-name]');
            const boxNowAddressLine1 = checkoutForm?.querySelector('[data-boxnow-address-line-1]');
            const boxNowPostalCode = checkoutForm?.querySelector('[data-boxnow-postal-code]');
            const boxNowCity = checkoutForm?.querySelector('[data-boxnow-city]');
            const summaryTax = checkoutForm?.querySelector('[data-summary-tax]');
            const summaryShipping = checkoutForm?.querySelector('[data-summary-shipping]');
            const summaryPaymentFee = checkoutForm?.querySelector('[data-summary-payment-fee]');
            const summaryTotal = checkoutForm?.querySelector('[data-summary-total]');
            const summaryShippingRow = checkoutForm?.querySelector('[data-summary-shipping-row]');
            const summaryPaymentFeeRow = checkoutForm?.querySelector('[data-summary-payment-fee-row]');

            let optionsAbortController = null;
            let optionsRefreshTimer = null;
            let boxNowScriptLoaded = false;

            const syncCustomerNames = function () {
                if (!billingFirst || !billingLast || !customerFirstHidden || !customerLastHidden) {
                    return;
                }

                customerFirstHidden.value = billingFirst.value;
                customerLastHidden.value = billingLast.value;
            };

            const setShippingState = function () {
                if (!toggle || !shippingFields || !useBillingInput) {
                    return;
                }

                if (toggle.checked) {
                    useBillingInput.value = '0';
                    shippingFields.style.maxHeight = shippingFields.scrollHeight + 'px';
                    shippingFields.style.opacity = '1';
                } else {
                    useBillingInput.value = '1';
                    shippingFields.style.maxHeight = '0';
                    shippingFields.style.opacity = '0';
                }

                scheduleOptionsRefresh();
            };

            const escapeHtml = function (value) {
                return String(value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            };

            const applyStateFieldMode = function (scope) {
                const countrySelect = scope.querySelector('[data-address-country]');
                const stateLabel = scope.querySelector('[data-state-label]');
                const stateSelect = scope.querySelector('[data-state-select]');
                const stateInput = scope.querySelector('[data-state-input]');
                if (!countrySelect || !stateLabel || !stateSelect || !stateInput) {
                    return;
                }

                const stateFieldName = stateSelect.dataset.stateName || stateSelect.getAttribute('name') || stateInput.getAttribute('name') || 'state';
                stateSelect.dataset.stateName = stateFieldName;

                const countryCode = String(countrySelect.value || '').toUpperCase();
                const regions = Array.isArray(regionOptionsByCountry[countryCode]) ? regionOptionsByCountry[countryCode] : [];
                const hasRegions = regions.length > 0;
                const optionLabel = countryCode === 'HR'
                    ? (stateSelect.dataset.optionHr || '')
                    : (stateSelect.dataset.optionIntl || stateSelect.dataset.optionHr || '');

                stateLabel.textContent = countryCode === 'HR'
                    ? (stateLabel.dataset.labelHr || stateLabel.textContent)
                    : (stateLabel.dataset.labelIntl || stateLabel.textContent);

                if (hasRegions) {
                    const previousValue = stateSelect.value || stateInput.value || '';
                    const options = ['<option value="">' + escapeHtml(optionLabel) + '</option>']
                        .concat(regions.map(function (region) {
                            const regionName = String(region?.name || '');
                            const selected = previousValue !== '' && previousValue === regionName ? ' selected' : '';
                            return '<option value="' + escapeHtml(regionName) + '"' + selected + '>' + escapeHtml(regionName) + '</option>';
                        }));
                    stateSelect.innerHTML = options.join('');

                    stateSelect.classList.remove('hidden');
                    stateSelect.disabled = false;
                    stateSelect.setAttribute('name', stateFieldName);
                    stateInput.classList.add('hidden');
                    stateInput.disabled = true;
                    stateInput.removeAttribute('name');
                } else {
                    if (!stateInput.value && stateSelect.value) {
                        stateInput.value = stateSelect.value;
                    }
                    stateInput.classList.remove('hidden');
                    stateInput.disabled = false;
                    stateInput.setAttribute('name', stateFieldName);
                    stateInput.placeholder = stateInput.dataset.placeholderIntl || '';
                    stateSelect.classList.add('hidden');
                    stateSelect.disabled = true;
                    stateSelect.removeAttribute('name');
                }
            };

            const applyAllStateFieldModes = function () {
                if (!checkoutForm) {
                    return;
                }

                checkoutForm.querySelectorAll('[data-address-scope]').forEach(function (scope) {
                    applyStateFieldMode(scope);
                });
            };

            const renderShippingOptions = function (methods, selectedCode) {
                if (!shippingOptionsRoot) {
                    return;
                }

                const currentlySelected = shippingOptionsRoot.querySelector('input[name="shipping_method_code"]:checked')?.value || '';
                if (!Array.isArray(methods) || methods.length === 0) {
                    shippingOptionsRoot.innerHTML = '<div class="text-sm text-rose-600">{{ __('ui.checkout.labels.no_shipping_methods') }}</div>';
                    return;
                }

                shippingOptionsRoot.innerHTML = methods.map(function (method, index) {
                    const checked = (selectedCode && selectedCode === method.code)
                        || (!selectedCode && (currentlySelected !== '' && currentlySelected === method.code))
                        || (!selectedCode && currentlySelected === '' && index === 0);
                    const isBoxNow = method.is_boxnow ? '1' : '0';
                    const partnerId = escapeHtml(method.boxnow_partner_id || '');
                    return '<label class="flex cursor-pointer items-center justify-between gap-3 border border-slate-300 px-3 py-2.5 text-sm hover:border-slate-500">'
                        + '<span class="inline-flex items-center gap-2">'
                        + '<input type="radio" name="shipping_method_code" value="' + escapeHtml(method.code) + '" data-is-boxnow="' + isBoxNow + '" data-boxnow-partner-id="' + partnerId + '" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" required ' + (checked ? 'checked' : '') + '>'
                        + '<span class="font-semibold text-slate-900">' + escapeHtml(method.name) + '</span>'
                        + '</span>'
                        + '<span class="text-slate-600">' + escapeHtml(method.price_formatted || '') + '</span>'
                        + '</label>';
                }).join('');

                toggleBoxNowPanel();
            };

            const renderPaymentOptions = function (methods, selectedCode) {
                if (!paymentOptionsRoot) {
                    return;
                }

                const currentlySelected = paymentOptionsRoot.querySelector('input[name="payment_method_code"]:checked')?.value || '';
                if (!Array.isArray(methods) || methods.length === 0) {
                    paymentOptionsRoot.innerHTML = '<div class="text-sm text-rose-600">{{ __('ui.checkout.labels.no_payment_methods') }}</div>';
                    return;
                }

                paymentOptionsRoot.innerHTML = methods.map(function (method, index) {
                    const checked = (selectedCode && selectedCode === method.code)
                        || (!selectedCode && (currentlySelected !== '' && currentlySelected === method.code))
                        || (!selectedCode && currentlySelected === '' && index === 0);
                    const code = String(method.code || '').toLowerCase();
                    const isKeks = code === 'keks' || code === 'keks_pay' || code === 'kekspay';
                    const methodLabel = isKeks
                        ? '<span class="inline-flex items-center gap-2"><img src="{{ asset('assets/payments/keks-logo.svg') }}" alt="KEKS Pay" class="h-5 w-auto max-w-[110px]"><span class="font-semibold text-slate-900">' + escapeHtml(method.name) + '</span></span>'
                        : '<span class="font-semibold text-slate-900">' + escapeHtml(method.name) + '</span>';
                    return '<label class="flex cursor-pointer items-center justify-between gap-3 border border-slate-300 px-3 py-2.5 text-sm hover:border-slate-500">'
                        + '<span class="inline-flex items-center gap-2">'
                        + '<input type="radio" name="payment_method_code" value="' + escapeHtml(method.code) + '" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" required ' + (checked ? 'checked' : '') + '>'
                        + methodLabel
                        + '</span>'
                        + '</label>';
                }).join('');
            };

            const renderCheckoutTotals = function (totals) {
                if (!totals || typeof totals !== 'object') {
                    return;
                }

                if (summaryTax && totals.tax_total_formatted) {
                    summaryTax.textContent = String(totals.tax_total_formatted);
                }
                if (summaryShipping && totals.shipping_total_formatted) {
                    summaryShipping.textContent = String(totals.shipping_total_formatted);
                }
                if (summaryPaymentFee && totals.payment_fee_total_formatted) {
                    summaryPaymentFee.textContent = String(totals.payment_fee_total_formatted);
                }
                if (summaryTotal && totals.grand_total_formatted) {
                    summaryTotal.textContent = String(totals.grand_total_formatted);
                }

                const paymentFeeTotal = Number(totals.payment_fee_total || 0);
                summaryShippingRow?.classList.remove('hidden');
                summaryPaymentFeeRow?.classList.toggle('hidden', paymentFeeTotal <= 0);
            };

            const selectedShippingInput = function () {
                return shippingOptionsRoot?.querySelector('input[name="shipping_method_code"]:checked') || null;
            };

            const toggleBoxNowPanel = function () {
                if (!boxNowPanel) {
                    return;
                }

                const selected = selectedShippingInput();
                const isBoxNow = selected?.dataset?.isBoxnow === '1' || selected?.dataset?.isBoxnow === 'true';
                boxNowPanel.classList.toggle('hidden', !isBoxNow);
            };

            const initBoxNowWidget = function () {
                if (boxNowScriptLoaded) {
                    return;
                }

                const partnerSource = shippingOptionsRoot?.querySelector('input[name="shipping_method_code"][data-is-boxnow="1"]');
                const partnerId = String(partnerSource?.dataset?.boxnowPartnerId || '').trim();
                if (partnerId === '') {
                    return;
                }

                window._bn_map_widget_config = {
                    partnerId: partnerId,
                    parentElement: '#boxnow-widget-root',
                    buttonSelector: '[data-boxnow-open]',
                    type: 'popup',
                    afterSelect: function (selection) {
                        updateBoxNowSelection(selection || {});
                    },
                };

                const script = document.createElement('script');
                script.src = 'https://widget-cdn.boxnow.hr/map-widget/client/v5.js';
                script.async = true;
                script.defer = true;
                script.onerror = function () {
                    alert(@json(__('ui.checkout.boxnow.widget_unavailable')));
                };
                document.head.appendChild(script);
                boxNowScriptLoaded = true;
            };

            const updateBoxNowSelection = function (selection) {
                const lockerId = String(selection?.boxnowLockerId || selection?.id || '');
                const lockerAddress = String(selection?.boxnowLockerAddressLine1 || selection?.addressLine1 || '');
                const lockerPostal = String(selection?.boxnowLockerPostalCode || selection?.postalCode || '');
                const lockerCity = String(selection?.boxnowLockerCity || selection?.city || '');
                const lockerName = String(selection?.boxnowLockerDescription || selection?.name || '');

                if (boxNowLockerId) boxNowLockerId.value = lockerId;
                if (boxNowLockerName) boxNowLockerName.value = lockerName;
                if (boxNowAddressLine1) boxNowAddressLine1.value = lockerAddress;
                if (boxNowPostalCode) boxNowPostalCode.value = lockerPostal;
                if (boxNowCity) boxNowCity.value = lockerCity;

                if (boxNowSelectedLabel) {
                    boxNowSelectedLabel.textContent = lockerId !== ''
                        ? [lockerName, lockerId].filter(Boolean).join(' / ')
                        : @json(__('ui.checkout.boxnow.no_locker_selected'));
                }
                if (boxNowSelectedAddress) {
                    boxNowSelectedAddress.textContent = lockerId !== ''
                        ? [lockerAddress, (lockerPostal + ' ' + lockerCity).trim()].filter(Boolean).join(', ')
                        : '';
                }
            };

            const refreshCheckoutOptions = async function () {
                if (!checkoutForm || !optionsUrl) {
                    return;
                }

                if (optionsAbortController) {
                    optionsAbortController.abort();
                }
                optionsAbortController = new AbortController();

                const shipDifferent = !!toggle?.checked;
                const billingCountry = checkoutForm.querySelector('[name="billing_country_code"]')?.value || '';
                const billingState = checkoutForm.querySelector('[name="billing_state"]')?.value || '';
                const billingPostal = checkoutForm.querySelector('[name="billing_postal_code"]')?.value || '';
                const shippingCountry = checkoutForm.querySelector('[name="shipping_country_code"]')?.value || '';
                const shippingState = checkoutForm.querySelector('[name="shipping_state"]')?.value || '';
                const shippingPostal = checkoutForm.querySelector('[name="shipping_postal_code"]')?.value || '';
                const selectedShippingCode = shippingOptionsRoot?.querySelector('input[name="shipping_method_code"]:checked')?.value || '';
                const selectedPaymentCode = paymentOptionsRoot?.querySelector('input[name="payment_method_code"]:checked')?.value || '';

                const params = new URLSearchParams({
                    ship_to_different_address: shipDifferent ? '1' : '0',
                    billing_country_code: billingCountry,
                    billing_state: billingState,
                    billing_postal_code: billingPostal,
                    shipping_country_code: shippingCountry,
                    shipping_state: shippingState,
                    shipping_postal_code: shippingPostal,
                    shipping_method_code: selectedShippingCode,
                    payment_method_code: selectedPaymentCode,
                });

                try {
                    const response = await fetch(optionsUrl + '?' + params.toString(), {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: optionsAbortController.signal,
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    const totals = payload.totals || {};
                    renderShippingOptions(payload.shipping_methods || [], String(totals.shipping_method_code || ''));
                    renderPaymentOptions(payload.payment_methods || [], String(totals.payment_method_code || ''));
                    renderCheckoutTotals(totals);
                    initBoxNowWidget();
                } catch (error) {
                    // Keep existing options on network/request errors.
                }
            };

            const scheduleOptionsRefresh = function () {
                if (optionsRefreshTimer) {
                    window.clearTimeout(optionsRefreshTimer);
                }

                optionsRefreshTimer = window.setTimeout(function () {
                    refreshCheckoutOptions();
                }, 200);
            };

            const setLoginState = function () {
                if (!loginToggle || !loginPanel) {
                    return;
                }

                if (loginToggle.checked) {
                    loginPanel.style.maxHeight = loginPanel.scrollHeight + 'px';
                    loginPanel.style.opacity = '1';
                } else {
                    loginPanel.style.maxHeight = '0';
                    loginPanel.style.opacity = '0';
                }
            };

            const setRegisterState = function () {
                if (!registerToggle || !registerPanel || !registerPassword || !registerPasswordConfirmation) {
                    return;
                }

                if (registerToggle.checked) {
                    registerPassword.required = true;
                    registerPasswordConfirmation.required = true;
                    registerPanel.style.maxHeight = registerPanel.scrollHeight + 'px';
                    registerPanel.style.opacity = '1';
                } else {
                    registerPassword.required = false;
                    registerPasswordConfirmation.required = false;
                    registerPanel.style.maxHeight = '0';
                    registerPanel.style.opacity = '0';
                }
            };

            const setR1State = function () {
                if (!r1Toggle || !r1Panel || !r1Company || !r1Oib) {
                    return;
                }

                if (r1Toggle.checked) {
                    r1Company.disabled = false;
                    r1Oib.disabled = false;
                    r1Panel.style.maxHeight = r1Panel.scrollHeight + 'px';
                    r1Panel.style.opacity = '1';
                } else {
                    r1Company.disabled = true;
                    r1Oib.disabled = true;
                    r1Panel.style.maxHeight = '0';
                    r1Panel.style.opacity = '0';
                }
            };

            const clearInlineErrors = function () {
                document.querySelectorAll('[data-checkout-error]').forEach(function (node) {
                    node.remove();
                });
            };

            const hideTopError = function () {
                if (!topErrorBox) {
                    return;
                }
                topErrorBox.classList.add('hidden');
                topErrorBox.textContent = '';
            };

            const showTopError = function (message) {
                if (!topErrorBox) {
                    return;
                }
                topErrorBox.textContent = String(message || '');
                topErrorBox.classList.remove('hidden');
                topErrorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };

            const renderInlineError = function (field, message) {
                if (!checkoutForm) {
                    return;
                }

                const input = checkoutForm.querySelector('[name="' + field + '"]');
                if (!input || input.type === 'hidden') {
                    return;
                }

                const errorNode = document.createElement('p');
                errorNode.className = 'mt-2 text-xs font-semibold text-rose-600';
                errorNode.dataset.checkoutError = '1';
                errorNode.textContent = message;

                if (input.type === 'checkbox' || input.type === 'radio') {
                    const label = input.closest('label');
                    if (label && label.parentElement) {
                        label.parentElement.appendChild(errorNode);
                        return;
                    }
                }

                input.insertAdjacentElement('afterend', errorNode);
            };

            syncCustomerNames();
            setShippingState();
            setLoginState();
            setRegisterState();
            setR1State();
            applyAllStateFieldModes();
            scheduleOptionsRefresh();
            toggleBoxNowPanel();
            initBoxNowWidget();

            toggle?.addEventListener('change', setShippingState);
            loginToggle?.addEventListener('change', setLoginState);
            registerToggle?.addEventListener('change', setRegisterState);
            r1Toggle?.addEventListener('change', setR1State);
            shippingOptionsRoot?.addEventListener('change', function (event) {
                if (event.target && event.target.name === 'shipping_method_code') {
                    toggleBoxNowPanel();
                    scheduleOptionsRefresh();
                }
            });
            paymentOptionsRoot?.addEventListener('change', function (event) {
                if (event.target && event.target.name === 'payment_method_code') {
                    scheduleOptionsRefresh();
                }
            });
            boxNowOpenButton?.addEventListener('click', function () {
                const selected = selectedShippingInput();
                const partnerId = String(selected?.dataset?.boxnowPartnerId || '').trim();
                if (partnerId === '') {
                    alert(@json(__('ui.checkout.boxnow.partner_missing')));
                }
            });
            billingFirst?.addEventListener('input', syncCustomerNames);
            billingLast?.addEventListener('input', syncCustomerNames);
            checkoutForm?.querySelectorAll('[data-address-country], [data-state-input], [data-state-select], [name="billing_postal_code"], [name="shipping_postal_code"]').forEach(function (node) {
                node.addEventListener('change', function () {
                    applyAllStateFieldModes();
                    scheduleOptionsRefresh();
                });
                node.addEventListener('input', function () {
                    scheduleOptionsRefresh();
                });
            });

            window.addEventListener('resize', function () {
                if (toggle?.checked && shippingFields) {
                    shippingFields.style.maxHeight = shippingFields.scrollHeight + 'px';
                }
                if (loginToggle?.checked && loginPanel) {
                    loginPanel.style.maxHeight = loginPanel.scrollHeight + 'px';
                }
                if (registerToggle?.checked && registerPanel) {
                    registerPanel.style.maxHeight = registerPanel.scrollHeight + 'px';
                }
                if (r1Toggle?.checked && r1Panel) {
                    r1Panel.style.maxHeight = r1Panel.scrollHeight + 'px';
                }
            });

            checkoutForm?.addEventListener('submit', async function (event) {
                event.preventDefault();
                clearInlineErrors();
                hideTopError();

                const submitBtn = checkoutForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                try {
                    const formData = new FormData(checkoutForm);
                    formData.append('_ajax', '1');

                    const response = await fetch(checkoutForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    if (response.status === 422) {
                        const payload = await response.json();
                        const errors = payload.errors || {};
                        let firstErrorField = null;
                        Object.keys(errors).forEach(function (field) {
                            const firstMessage = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
                            if (firstMessage) {
                                renderInlineError(field, firstMessage);
                                if (firstErrorField === null) {
                                    firstErrorField = field;
                                }
                                if (field === 'accept_terms') {
                                    showTopError(firstMessage);
                                }
                            }
                        });

                        if (firstErrorField) {
                            const firstInput = checkoutForm.querySelector('[name="' + firstErrorField + '"]');
                            firstInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    if (!response.ok) {
                        return;
                    }

                    const headerRedirect = response.headers.get('X-Checkout-Redirect');
                    if (headerRedirect) {
                        window.location.href = headerRedirect;
                        return;
                    }

                    const rawBody = await response.text();
                    let payload = null;
                    try {
                        payload = rawBody ? JSON.parse(rawBody) : null;
                    } catch (error) {
                        payload = null;
                    }

                    if (payload && payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }

                    if (response.url && response.url !== window.location.href) {
                        window.location.href = response.url;
                        return;
                    }

                    const successFallbackUrl = checkoutForm.dataset.successFallback;
                    if (successFallbackUrl) {
                        window.location.href = successFallbackUrl;
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            });
        });
    </script>
@endpush
