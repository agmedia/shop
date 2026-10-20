<section class="py-4" style="padding:1rem 0" aria-label="{{ __('compliance.manufacturer') }}" data-manufacturer-information>
    <h2 class="text-sm font-semibold" style="font-size:1rem">{{ __('compliance.manufacturer') }}</h2>
    <address style="font-style:normal;line-height:1.6">
        Bali d.o.o.<br>
        Hrupine 19, HR-40323 Prelog, Kroatien<br>
        <a href="mailto:webshop@balidoo.com" style="text-decoration:underline">webshop@balidoo.com</a>
    </address>
    <p>{{ __('compliance.identifier') }}: {{ $product->sku ?: $product->code }}</p>
    @php($safetyWarnings = data_get($product->payload, 'safety_warnings.'.app()->getLocale(), data_get($product->payload, 'safety_warnings_text', '')))
    @if (is_string($safetyWarnings) && trim($safetyWarnings) !== '')
        <h3>{{ __('compliance.warnings') }}</h3>
        <p>{!! nl2br(e($safetyWarnings)) !!}</p>
    @endif
</section>
