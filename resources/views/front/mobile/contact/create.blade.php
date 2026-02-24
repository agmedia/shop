@extends('front.mobile.layouts.store')

@section('title', __('contact.page_title'))
@section('header_title', __('contact.page_title'))
@section('page_title', __('contact.heading'))

@section('content')
    @php
        $captchaSiteKey = trim((string) ($storeSettings['captcha']['recaptcha_v3_site_key'] ?? ''));
        $captchaEnabled = (bool) ($storeSettings['captcha']['recaptcha_v3_enabled'] ?? false) && $captchaSiteKey !== '';
    @endphp

    <div class="card card-style rounded-0 bg-white border border-gray-light">
        <div class="content">
            <p class="font-12 color-highlight mb-n1">{{ __('contact.eyebrow') }}</p>
            <h2 class="mb-2">{{ __('contact.heading') }}</h2>
            <p class="mb-0">{{ __('contact.subheading') }}</p>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <form
                method="POST"
                action="{{ route('contact.store') }}"
                novalidate
                data-contact-form
                data-msg-name-required="{{ __('contact.validation.inline.name_required') }}"
                data-msg-email-required="{{ __('contact.validation.inline.email_required') }}"
                data-msg-email-invalid="{{ __('contact.validation.inline.email_invalid') }}"
                data-msg-message-required="{{ __('contact.validation.inline.message_required') }}"
                data-msg-message-min="{{ __('contact.validation.inline.message_min') }}"
                data-msg-accept-terms="{{ __('contact.validation.inline.accept_terms') }}"
                @if($captchaEnabled) data-recaptcha-form data-recaptcha-site-key="{{ $captchaSiteKey }}" data-recaptcha-action="contact_form" @endif
            >
                @csrf
                <input type="hidden" name="recaptcha_token" value="" data-recaptcha-token>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="contact-name" class="color-highlight">{{ __('contact.form.name') }}</label>
                    <input id="contact-name" type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('name') ? '' : 'hidden' }}" data-field-error="name">@error('name'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="contact-email" class="color-highlight">{{ __('contact.form.email') }}</label>
                    <input id="contact-email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('email') ? '' : 'hidden' }}" data-field-error="email">@error('email'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="contact-phone" class="color-highlight">{{ __('contact.form.phone') }}</label>
                    <input id="contact-phone" type="text" name="phone" value="{{ old('phone') }}">
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('phone') ? '' : 'hidden' }}" data-field-error="phone">@error('phone'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="contact-subject" class="color-highlight">{{ __('contact.form.subject') }}</label>
                    <input id="contact-subject" type="text" name="subject" value="{{ old('subject') }}">
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('subject') ? '' : 'hidden' }}" data-field-error="subject">@error('subject'){{ $message }}@enderror</p>
                </div>

                <div class="input-style has-borders input-style-always-active no-icon mb-3">
                    <textarea id="contact-message" name="message" style="height:140px;" required>{{ old('message') }}</textarea>
                    <label for="contact-message" class="color-highlight">{{ __('contact.form.message') }}</label>
                    <p class="font-11 color-red-dark mb-0 mt-1 {{ $errors->has('message') ? '' : 'hidden' }}" data-field-error="message">@error('message'){{ $message }}@enderror</p>
                </div>

                <label class="font-12 d-block mb-3">
                    <input type="checkbox" name="accept_terms" value="1" @checked((bool) old('accept_terms'))> {{ __('contact.form.accept_terms') }}
                </label>
                <p class="font-11 color-red-dark mb-2 mt-1 {{ $errors->has('accept_terms') ? '' : 'hidden' }}" data-field-error="accept_terms">@error('accept_terms'){{ $message }}@enderror</p>

                <button type="submit" class="btn btn-full btn-border border-dark-dark color-dark-dark font-600">{{ __('contact.form.submit') }}</button>
                <p class="font-11 color-red-dark mb-2 mt-1 {{ $errors->has('recaptcha_token') ? '' : 'hidden' }}" data-field-error="recaptcha_token">@error('recaptcha_token'){{ $message }}@enderror</p>
            </form>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <h4 class="mb-2">{{ __('contact.direct.title') }}</h4>
            @if (!empty($storeSettings['footer']['email_support'] ?? ''))
                <p class="mb-1"><strong>{{ __('contact.direct.email') }}:</strong> {{ $storeSettings['footer']['email_support'] }}</p>
            @endif
            @if (!empty($storeSettings['footer']['phone'] ?? ''))
                <p class="mb-1"><strong>{{ __('contact.direct.phone') }}:</strong> {{ $storeSettings['footer']['phone'] }}</p>
            @endif
            <p class="mb-0"><strong>{{ __('contact.direct.response_time') }}:</strong> {{ (string) ($storeSettings['footer']['hours'] ?? __('contact.direct.response_fallback')) }}</p>
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
                const forms = document.querySelectorAll('[data-contact-form]');
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
                        ['name', 'email', 'message', 'accept_terms', 'recaptcha_token'].forEach(clearError);

                        const name = form.querySelector('[name="name"]');
                        const email = form.querySelector('[name="email"]');
                        const message = form.querySelector('[name="message"]');
                        const acceptTerms = form.querySelector('[name="accept_terms"]');
                        let valid = true;

                        if (!name || name.value.trim() === '') {
                            setError('name', form.dataset.msgNameRequired || '');
                            valid = false;
                        }

                        const emailValue = email ? email.value.trim() : '';
                        if (emailValue === '') {
                            setError('email', form.dataset.msgEmailRequired || '');
                            valid = false;
                        } else if (!emailRegex.test(emailValue)) {
                            setError('email', form.dataset.msgEmailInvalid || '');
                            valid = false;
                        }

                        const messageValue = message ? message.value.trim() : '';
                        if (messageValue === '') {
                            setError('message', form.dataset.msgMessageRequired || '');
                            valid = false;
                        } else if (messageValue.length < 10) {
                            setError('message', form.dataset.msgMessageMin || '');
                            valid = false;
                        }

                        if (!acceptTerms || !acceptTerms.checked) {
                            setError('accept_terms', form.dataset.msgAcceptTerms || '');
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
                        const action = form.dataset.recaptchaAction || 'contact_form';
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
