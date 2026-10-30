@php
    $deliveryCountry = strtoupper((string) ($showShippingAddress
        ? old('shipping_country_code', $prefill['shipping']['country_code'] ?? 'HR')
        : old('billing_country_code', $prefill['billing']['country_code'] ?? 'HR')));
@endphp
<p data-de-at-delivery-note @if (!in_array($deliveryCountry, ['DE', 'AT'], true)) hidden @endif style="margin-top:.75rem;font-size:1rem;line-height:1.5" role="status" aria-live="polite">{{ __('compliance.delivery_time_de_at') }}</p>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-checkout-options-url]').forEach(function (form) {
                    const note = form.querySelector('[data-de-at-delivery-note]');
                    if (!note) return;

                    const updateDeliveryEstimate = function () {
                        const separateShipping = form.querySelector('[name="ship_to_different_address"]')?.checked === true;
                        const countryField = separateShipping ? 'shipping_country_code' : 'billing_country_code';
                        const country = String(form.querySelector('[name="' + countryField + '"]')?.value || '').toUpperCase();
                        note.hidden = !['DE', 'AT'].includes(country);
                    };

                    form.addEventListener('change', updateDeliveryEstimate);
                    form.addEventListener('input', updateDeliveryEstimate);
                    updateDeliveryEstimate();
                });
            }());
        </script>
    @endpush
@endonce
