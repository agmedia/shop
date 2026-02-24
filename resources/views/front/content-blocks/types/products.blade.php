@php
    $basePayload = is_array($block->payload ?? null) ? $block->payload : [];
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $payload = array_merge($basePayload, $translationPayload);

    $sectionClass = (string) ($payload['section_class'] ?? 'rounded-3xl border border-slate-200 bg-white p-6');
    $gridClass = (string) ($payload['grid_class'] ?? 'mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4');
    $cardClass = (string) ($payload['card_class'] ?? 'rounded-2xl border border-slate-200 bg-slate-50 p-4');
    $titleClass = (string) ($payload['title_class'] ?? 'text-2xl font-extrabold tracking-tight text-slate-900');
    $taxPricing = app(\App\Services\Pricing\TaxPricingService::class);
@endphp

<section class="{{ $sectionClass }}">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h2 class="{{ $titleClass }}">{{ $translation?->title ?: $block->name }}</h2>
            @if (!empty($translation?->subtitle))
                <p class="mt-2 text-sm text-slate-600">{{ $translation->subtitle }}</p>
            @endif
        </div>
    </div>

    <div class="{{ $gridClass }}">
        @forelse ($products as $product)
            @php
                $pt = $product->translations->firstWhere('locale', app()->getLocale())
                    ?? $product->translations->firstWhere('locale', config('app.locale'));
                $displayPrice = $taxPricing->grossFromStored((float) $product->base_price, $product);
            @endphp
            <article class="{{ $cardClass }}">
                <div class="h-36 rounded-xl bg-gradient-to-br from-slate-200 to-slate-100"></div>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $pt?->name ?? $product->code }}</h3>
                <p class="mt-2 text-sm font-semibold text-slate-800">{{ number_format($displayPrice, 2) }} €</p>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-4">
                No products selected for this block.
            </div>
        @endforelse
    </div>
</section>
