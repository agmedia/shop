@php
    $klaviyoSettings = $storeSettings['newsletter'] ?? [];
    $klaviyoSiteId = trim((string) ($klaviyoSettings['klaviyo_site_id'] ?? ''));
    $klaviyoEnabled = ($klaviyoSettings['provider'] ?? '') === 'klaviyo'
        && preg_match('/^[a-zA-Z0-9]{6}$/', $klaviyoSiteId) === 1;
@endphp

@if ($klaviyoEnabled)
    <script>
        (function () {
            var loaded = false;
            var consentEnabled = @json((bool) ($storeSettings['cookies']['enabled'] ?? true));

            window.updateKlaviyoConsentFromCookie = function (marketingGranted) {
                // Klaviyo's opt-out cookie also handles consent withdrawal after the SDK loads.
                document.cookie = '__kla_off=' + (marketingGranted ? 'false' : 'true')
                    + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');

                if (!marketingGranted || loaded) {
                    return;
                }

                loaded = true;
                !function(){if(!window.klaviyo){window._klOnsite=window._klOnsite||[];try{window.klaviyo=new Proxy({},{get:function(n,i){return"push"===i?function(){var n;(n=window._klOnsite).push.apply(n,arguments)}:function(){for(var n=arguments.length,o=new Array(n),w=0;w<n;w++)o[w]=arguments[w];var t="function"==typeof o[o.length-1]?o.pop():void 0,e=new Promise((function(n){window._klOnsite.push([i].concat(o,[function(i){t&&t(i),n(i)}]))}));return e}}})}catch(n){window.klaviyo=window.klaviyo||[],window.klaviyo.push=function(){var n;(n=window._klOnsite).push.apply(n,arguments)}}}}();

                var script = document.createElement('script');
                script.async = true;
                script.src = 'https://static.klaviyo.com/onsite/js/' + @json($klaviyoSiteId)
                    + '/klaviyo.js?company_id=' + @json($klaviyoSiteId);
                script.onerror = function () { loaded = false; };
                document.head.appendChild(script);
            };

            window.updateKlaviyoConsentFromCookie(consentEnabled && window.cookieMarketingAllowed === true);
        })();
    </script>
@endif
