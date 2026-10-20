@extends('front.desktop.layouts.store')

@section('title', __('return_request.page_title'))
@section('main_class', 'mx-auto w-full max-w-5xl px-6 pt-0 pb-8')

@section('content')
    @php
        $captchaSiteKey = trim((string) ($storeSettings['captcha']['recaptcha_v3_site_key'] ?? ''));
        $captchaEnabled = (bool) ($storeSettings['captcha']['recaptcha_v3_enabled'] ?? false) && $captchaSiteKey !== '';
    @endphp

    <section class="mb-8 px-1">
        <div class="front-soft-hero px-6 py-4 text-center sm:px-8 sm:py-5">
            <nav aria-label="Breadcrumb" class="mb-2">
                <ol class="flex flex-wrap items-center justify-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:gap-2">
                    <li>
                        <a href="{{ route('home') }}" class="inline-flex items-center justify-center text-slate-500 hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a>
                    </li>
                    <li class="text-slate-400">/</li>
                    <li class="text-slate-700">{{ __('return_request.page_title') }}</li>
                </ol>
            </nav>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('return_request.eyebrow') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold uppercase tracking-tight text-slate-900">{{ __('return_request.heading') }}</h1>
            <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('return_request.subheading') }}</p>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_300px]">
        <form
            method="POST"
            action="{{ route('returns.store', ['returnRequestSlug' => __('return_request.slug')]) }}"
            class="border border-slate-200 bg-white p-6 sm:p-8"
            novalidate
            data-return-form
            data-msg-email-required="{{ __('return_request.validation.inline.email_required') }}"
            data-msg-email-invalid="{{ __('return_request.validation.inline.email_invalid') }}"
            data-msg-order-number-required="{{ __('return_request.validation.inline.order_number_required') }}"
            data-msg-return-items-required="{{ __('return_request.validation.inline.return_items_required') }}"
            data-msg-return-items-min="{{ __('return_request.validation.inline.return_items_min') }}"
            @if($captchaEnabled) data-recaptcha-form data-recaptcha-site-key="{{ $captchaSiteKey }}" data-recaptcha-action="return_request_form" @endif
        >
            @csrf
            <input type="hidden" name="recaptcha_token" value="" data-recaptcha-token>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="return-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('return_request.form.email') }}</label>
                    <input id="return-email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                    <p class="mt-2 text-xs font-semibold text-rose-600 {{ $errors->has('email') ? '' : 'hidden' }}" data-field-error="email">@error('email'){{ $message }}@enderror</p>
                </div>
                <div>
                    <label for="return-order-number" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('return_request.form.order_number') }}</label>
                    <input id="return-order-number" type="text" name="order_number" value="{{ old('order_number') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" required>
                    <p class="mt-2 text-xs font-semibold text-rose-600 {{ $errors->has('order_number') ? '' : 'hidden' }}" data-field-error="order_number">@error('order_number'){{ $message }}@enderror</p>
                </div>
            </div>

            <div class="mt-4">
                <label for="return-items" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('return_request.form.return_items') }}</label>
                <textarea id="return-items" name="return_items" rows="6" class="w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" placeholder="{{ __('return_request.form.return_items_placeholder') }}" required>{{ old('return_items') }}</textarea>
                <p class="mt-2 text-xs font-semibold text-rose-600 {{ $errors->has('return_items') ? '' : 'hidden' }}" data-field-error="return_items">@error('return_items'){{ $message }}@enderror</p>
            </div>

            <div class="mt-4">
                <label for="return-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('return_request.form.note') }}</label>
                <textarea id="return-note" name="note" rows="5" class="w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" placeholder="{{ __('return_request.form.note_placeholder') }}">{{ old('note') }}</textarea>
                <p class="mt-2 text-xs font-semibold text-rose-600 {{ $errors->has('note') ? '' : 'hidden' }}" data-field-error="note">@error('note'){{ $message }}@enderror</p>
            </div>

            <button type="submit" class="mt-6 inline-flex h-11 items-center justify-center border border-slate-900 bg-slate-900 px-6 text-sm font-semibold text-white transition hover:bg-slate-700">
                {{ __('return_request.form.submit') }}
            </button>
            <p class="mt-2 text-xs font-semibold text-rose-600 {{ $errors->has('recaptcha_token') ? '' : 'hidden' }}" data-field-error="recaptcha_token">@error('recaptcha_token'){{ $message }}@enderror</p>
        </form>

        <aside class="border border-slate-200 bg-slate-100 p-6">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ __('return_request.help.title') }}</h2>
            <p class="mt-2 text-sm text-slate-700">{{ __('return_request.help.body') }}</p>
        </aside>
    </section>

    @if ($captchaEnabled)
        @push('scripts')
            <script src="https://www.google.com/recaptcha/api.js?render={{ $captchaSiteKey }}"></script>
        @endpush
    @endif

    @push('scripts')
        <script>
            (function () {
                const forms = document.querySelectorAll('[data-return-form]');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                forms.forEach(function (form) {
                    const clearError = function (field) {
                        const errorNode = form.querySelector('[data-field-error="' + field + '"]');
                        if (!errorNode) {
                            return;
                        }
                        errorNode.textContent = '';
                        errorNode.classList.add('hidden');
                        errorNode.style.display = 'none';
                    };

                    const setError = function (field, message) {
                        const errorNode = form.querySelector('[data-field-error="' + field + '"]');
                        if (!errorNode) {
                            return;
                        }
                        errorNode.textContent = message;
                        errorNode.classList.remove('hidden');
                        errorNode.style.display = 'block';
                    };

                    form.querySelectorAll('[data-field-error]').forEach(function (node) {
                        if ((node.textContent || '').trim() === '') {
                            node.style.display = 'none';
                        } else {
                            node.style.display = 'block';
                            node.classList.remove('hidden');
                        }
                    });

                    const validate = function () {
                        ['email', 'order_number', 'return_items', 'note', 'recaptcha_token'].forEach(clearError);

                        const email = form.querySelector('[name="email"]');
                        const orderNumber = form.querySelector('[name="order_number"]');
                        const returnItems = form.querySelector('[name="return_items"]');
                        let valid = true;

                        const emailValue = email ? email.value.trim() : '';
                        if (emailValue === '') {
                            setError('email', form.dataset.msgEmailRequired || '');
                            valid = false;
                        } else if (!emailRegex.test(emailValue)) {
                            setError('email', form.dataset.msgEmailInvalid || '');
                            valid = false;
                        }

                        if (!orderNumber || orderNumber.value.trim() === '') {
                            setError('order_number', form.dataset.msgOrderNumberRequired || '');
                            valid = false;
                        }

                        const returnItemsValue = returnItems ? returnItems.value.trim() : '';
                        if (returnItemsValue === '') {
                            setError('return_items', form.dataset.msgReturnItemsRequired || '');
                            valid = false;
                        } else if (returnItemsValue.length < 2) {
                            setError('return_items', form.dataset.msgReturnItemsMin || '');
                            valid = false;
                        }

                        return valid;
                    };

                    form.addEventListener('submit', function (event) {
                        event.preventDefault();
                        if (!validate()) {
                            return;
                        }

                        const tokenInput = form.querySelector('[data-recaptcha-token]');
                        const siteKey = form.dataset.recaptchaSiteKey;
                        const action = form.dataset.recaptchaAction || 'return_request_form';
                        if (!tokenInput || !window.grecaptcha || !siteKey) {
                            form.submit();
                            return;
                        }

                        grecaptcha.ready(function () {
                            grecaptcha.execute(siteKey, { action: action }).then(function (token) {
                                tokenInput.value = token || '';
                                form.submit();
                            });
                        });
                    });
                });
            }());
        </script>
    @endpush
@endsection
