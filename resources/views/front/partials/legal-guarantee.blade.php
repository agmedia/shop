@php($noticeLocale = in_array(app()->getLocale(), ['de', 'en', 'hr'], true) ? app()->getLocale() : 'en')
<section aria-label="{{ __('compliance.warranty') }}" style="margin-top:1rem;color:#111" data-legal-guarantee>
    <a href="{{ asset('front-theme/images/legal/legal-guarantee-'.$noticeLocale.'.jpg') }}" target="_blank" rel="noopener" style="font-size:1rem;line-height:1.5;text-decoration:underline;font-weight:600">
        {{ __('compliance.warranty_link') }}
    </a>
</section>
