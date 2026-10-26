@once
    @include('front.partials.recaptcha-loader')
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-recaptcha-form]').forEach(function (form) {
                    if (form.dataset.recaptchaBound === '1') {
                        return;
                    }

                    form.dataset.recaptchaBound = '1';

                    form.addEventListener('submit', function (event) {
                        const tokenInput = form.querySelector('[data-recaptcha-token]');
                        const siteKey = form.dataset.recaptchaSiteKey;
                        const action = form.dataset.recaptchaAction || 'form_submit';

                        if (!tokenInput || !siteKey) {
                            return;
                        }

                        event.preventDefault();

                        window.getStoreRecaptchaToken(siteKey, action).then(function (token) {
                            tokenInput.value = token || '';
                            form.submit();
                        });
                    });
                });
            }());
        </script>
    @endpush
@endonce
