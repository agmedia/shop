@php
    $consentLinks = [
        'terms' => [
            'label' => __('ui.front.desktop.newsletter.consent_terms'),
            'url' => route('pages.show', ['slug' => 'opci-uvjeti-koristenja']),
        ],
        'privacy' => [
            'label' => __('ui.front.desktop.newsletter.consent_privacy'),
            'url' => route('pages.show', ['slug' => 'privacy-policy-short-marketing']),
        ],
    ];
    $consentPhrases = [];

    // Custom consent copy can remain in its original language after a locale switch.
    foreach (array_unique([app()->getLocale(), 'hr', 'en', 'de']) as $consentLocale) {
        foreach ($consentLinks as $consentKey => $consentLink) {
            $phrase = (string) trans('ui.front.desktop.newsletter.consent_'.$consentKey, [], $consentLocale);
            $consentPhrases[mb_strtolower($phrase)] = $consentKey;
        }
    }

    $consentPattern = '~('.implode('|', array_map(static fn ($phrase) => preg_quote($phrase, '~'), array_keys($consentPhrases))).')~iu';
    $consentParts = preg_split($consentPattern, (string) $label, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    $consentHtml = '';
    $missingConsentLinks = $consentLinks;
    $consentAnchor = static fn ($text, $url) => '<a href="'.e($url).'" class="underline underline-offset-2 hover:text-slate-700">'.e($text).'</a>';

    foreach ($consentParts as $consentPart) {
        $consentKey = $consentPhrases[mb_strtolower($consentPart)] ?? null;
        $consentHtml .= $consentKey === null ? e($consentPart) : $consentAnchor($consentPart, $consentLinks[$consentKey]['url']);
        unset($missingConsentLinks[$consentKey]);
    }

    if ($missingConsentLinks !== []) {
        $consentHtml .= ' ('.implode('; ', array_map(static fn ($link) => $consentAnchor($link['label'], $link['url']), $missingConsentLinks)).')';
    }
@endphp
<span>{{ new \Illuminate\Support\HtmlString($consentHtml) }}</span>
