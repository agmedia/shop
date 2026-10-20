@php($noticeLocale = in_array(app()->getLocale(), ['de', 'en', 'hr'], true) ? app()->getLocale() : 'en')
<section aria-label="{{ __('compliance.warranty') }}" style="margin-top:1rem;background:white;color:#111" data-legal-guarantee>
    <a href="{{ asset('front-theme/images/legal/legal-guarantee-'.$noticeLocale.'.jpg') }}" target="_blank" rel="noopener" aria-label="{{ __('compliance.warranty_full') }}">
        <img src="{{ asset('front-theme/images/legal/legal-guarantee-'.$noticeLocale.'.jpg') }}" alt="{{ __('compliance.warranty') }}" width="880" height="1199" style="display:block;width:100%;height:auto">
    </a>
    <p style="font-size:1rem;line-height:1.5;margin-top:.5rem">{{ __('compliance.warranty_text') }}</p>
</section>
