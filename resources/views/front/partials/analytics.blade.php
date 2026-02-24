@php
    $analytics = $storeSettings['analytics'] ?? [];
    $analyticsEnabled = (bool) ($analytics['enabled'] ?? false);
    $ga4Id = trim((string) ($analytics['ga4_measurement_id'] ?? ''));
@endphp

@if ($analyticsEnabled && $ga4Id !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $ga4Id }}');
    </script>
    <script defer src="{{ asset('front-theme/scripts/shop-analytics.js') }}?v={{ filemtime(public_path('front-theme/scripts/shop-analytics.js')) }}"></script>
@endif
