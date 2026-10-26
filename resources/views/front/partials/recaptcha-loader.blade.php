@once
    @push('scripts')
        <script>
            (function () {
                let loading;

                window.loadStoreRecaptcha = function (siteKey) {
                    if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                        return Promise.resolve(window.grecaptcha);
                    }
                    if (loading) return loading;

                    loading = new Promise(function (resolve, reject) {
                        const existing = document.querySelector('script[src^="https://www.google.com/recaptcha/api.js"]');
                        const script = existing || document.createElement('script');
                        const timeout = window.setTimeout(function () {
                            script.remove();
                            reject(new Error('reCAPTCHA loading timed out.'));
                        }, 15000);
                        script.addEventListener('load', function () {
                            window.clearTimeout(timeout);
                            if (window.grecaptcha) resolve(window.grecaptcha);
                            else reject(new Error('reCAPTCHA is unavailable.'));
                        }, { once: true });
                        script.addEventListener('error', function () {
                            window.clearTimeout(timeout);
                            script.remove();
                            reject(new Error('reCAPTCHA could not load.'));
                        }, { once: true });
                        if (!existing) {
                            script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKey);
                            script.async = true;
                            document.head.appendChild(script);
                        }
                    }).catch(function (error) {
                        loading = null;
                        throw error;
                    });

                    return loading;
                };

                window.getStoreRecaptchaToken = function (siteKey, action) {
                    return window.loadStoreRecaptcha(siteKey).then(function (api) {
                        return new Promise(function (resolve, reject) {
                            api.ready(function () {
                                try {
                                    Promise.resolve(api.execute(siteKey, { action: action })).then(resolve, reject);
                                } catch (error) {
                                    reject(error);
                                }
                            });
                        });
                    }).catch(function () {
                        // Let the existing server validation show its localized CAPTCHA error.
                        return '';
                    });
                };
            }());
        </script>
    @endpush
@endonce
