@extends('front.mobile.layouts.store')

@section('title', __('return_request.page_title'))
@section('header_title', __('return_request.page_title'))
@section('page_title', __('return_request.heading'))

@section('content')
    @php
        $captchaSiteKey = trim((string) ($storeSettings['captcha']['recaptcha_v3_site_key'] ?? ''));
        $captchaEnabled = (bool) ($storeSettings['captcha']['recaptcha_v3_enabled'] ?? false) && $captchaSiteKey !== '';
    @endphp

    <div class="card card-style rounded-0 bg-white border border-gray-light">
        <div class="content">
            <p class="font-12 color-highlight mb-n1">{{ __('return_request.eyebrow') }}</p>
            <h2 class="mb-2">{{ __('return_request.heading') }}</h2>
            <p class="mb-0">{{ __('return_request.subheading') }}</p>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <form
                method="POST"
                action="{{ route('returns.store', ['returnRequestSlug' => __('return_request.slug')]) }}"
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

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="return-email" class="color-highlight">{{ __('return_request.form.email') }}</label>
                    <input id="return-email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('email') ? '' : 'hidden' }}" data-field-error="email">@error('email'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="return-order-number" class="color-highlight">{{ __('return_request.form.order_number') }}</label>
                    <input id="return-order-number" type="text" name="order_number" value="{{ old('order_number') }}" required>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('order_number') ? '' : 'hidden' }}" data-field-error="order_number">@error('order_number'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders input-style-always-active no-icon mb-3">
                    <textarea id="return-items" name="return_items" style="height:130px;" placeholder="{{ __('return_request.form.return_items_placeholder') }}" required>{{ old('return_items') }}</textarea>
                    <label for="return-items" class="color-highlight">{{ __('return_request.form.return_items') }}</label>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('return_items') ? '' : 'hidden' }}" data-field-error="return_items">@error('return_items'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders input-style-always-active no-icon mb-3">
                    <textarea id="return-note" name="note" style="height:120px;" placeholder="{{ __('return_request.form.note_placeholder') }}">{{ old('note') }}</textarea>
                    <label for="return-note" class="color-highlight">{{ __('return_request.form.note') }}</label>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('note') ? '' : 'hidden' }}" data-field-error="note">@error('note'){{ $message }}@enderror</p>
                </div>

                <button type="submit" class="btn btn-full btn-border border-dark-dark color-dark-dark font-600">{{ __('return_request.form.submit') }}</button>
                <p class="font-11 color-red-dark mb-2 mt-1 {{ $errors->has('recaptcha_token') ? '' : 'hidden' }}" data-field-error="recaptcha_token">@error('recaptcha_token'){{ $message }}@enderror</p>
            </form>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <h4 class="mb-2">{{ __('return_request.help.title') }}</h4>
            <p class="mb-0">{{ __('return_request.help.body') }}</p>
        </div>
    </div>

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
