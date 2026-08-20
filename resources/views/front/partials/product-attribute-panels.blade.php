@php
    $attributeOrder = ['sastav', 'kvaliteta', 'garancija'];
    $fallbackLabels = [
        'sastav' => __('ui.product.attribute_groups.composition'),
        'kvaliteta' => __('ui.product.attribute_groups.quality'),
        'garancija' => __('ui.product.attribute_groups.warranty'),
    ];
    $attributes = $product->relationLoaded('attributes') ? $product->attributes : collect();

    $attributePanels = collect($attributeOrder)
        ->map(function (string $groupCode) use ($attributes, $fallbackLabels, $locale, $fallbackLocale): ?array {
            $items = $attributes
                ->filter(fn ($attribute): bool => (string) $attribute->group_code === $groupCode)
                ->map(function ($attribute) use ($locale, $fallbackLocale): array {
                    $translation = $attribute->translations->firstWhere('locale', $locale)
                        ?? $attribute->translations->firstWhere('locale', $fallbackLocale)
                        ?? $attribute->translations->first();

                    return [
                        'id' => (int) $attribute->id,
                        'group_name' => trim((string) ($translation?->group_name ?? '')),
                        'name' => trim((string) ($translation?->name ?? $attribute->code)),
                    ];
                })
                ->filter(fn (array $item): bool => $item['name'] !== '')
                ->unique('name')
                ->values();

            if ($items->isEmpty()) {
                return null;
            }

            $groupName = $items
                ->pluck('group_name')
                ->first(fn (string $value): bool => $value !== '');

            return [
                'group_code' => $groupCode,
                'group_name' => $groupName ?: $fallbackLabels[$groupCode],
                'items' => $items->all(),
            ];
        })
        ->filter()
        ->values();

    $materialFeatureIconSets = [
        'giza_pamuk' => [
            ['label' => 'Prozračan', 'icon' => 'PROZRACAN.svg'],
            ['label' => 'Upijajući', 'icon' => 'UPOJNOST.svg'],
            ['label' => 'Izdržljiv', 'icon' => 'DUGOTRAJAN.svg'],
        ],
        'modal_pamuk' => [
            ['label' => 'Svilenkast', 'icon' => 'SVILENKASTI_DODIR.svg'],
            ['label' => 'Elastičan', 'icon' => 'ELASTICNOST.svg'],
            ['label' => 'Prozračan', 'icon' => 'PROZRACAN.svg'],
        ],
        'mikromodal' => [
            ['label' => 'Svilenkast', 'icon' => 'SVILENKASTI_DODIR.svg'],
            ['label' => 'Elastičan', 'icon' => 'ELASTICNOST.svg'],
            ['label' => 'Hipoalergen', 'icon' => 'HIPOALERGEN.svg'],
        ],
    ];

    $materialFeatureIcons = [];
    $materialPanel = $attributePanels->firstWhere('group_code', 'sastav');

    if ($materialPanel) {
        $materialText = collect($materialPanel['items'])->pluck('name')->implode(' ');
        $materialText = strtolower(\Illuminate\Support\Str::ascii($materialText));
        $materialText = preg_replace('/\s+/', ' ', $materialText) ?? $materialText;
        $isMikromodal = str_contains($materialText, 'mikromodal')
            || str_contains($materialText, 'mikro modal')
            || str_contains($materialText, 'micromodal')
            || str_contains($materialText, 'micro modal');

        if ($isMikromodal) {
            $materialFeatureIcons = $materialFeatureIconSets['mikromodal'];
        } elseif (str_contains($materialText, 'giza') && str_contains($materialText, 'pamuk')) {
            $materialFeatureIcons = $materialFeatureIconSets['giza_pamuk'];
        } elseif (str_contains($materialText, 'modal') && str_contains($materialText, 'pamuk')) {
            $materialFeatureIcons = $materialFeatureIconSets['modal_pamuk'];
        }
    }
@endphp

@if ($attributePanels->isNotEmpty())
    @once
        <style>
            [data-product-attribute-panels] details > summary {
                list-style: none;
            }

            [data-product-attribute-panels] details > summary::-webkit-details-marker {
                display: none;
            }

            [data-product-attribute-panels] details {
                transition: color .2s ease;
            }

            [data-product-attribute-panels] details[open] {
                color: #0f172a;
            }

            [data-product-attribute-panels] [data-attribute-chevron] {
                transition: transform .2s ease;
            }

            [data-product-attribute-panels] details[open] [data-attribute-chevron] {
                transform: rotate(180deg);
            }

            [data-product-attribute-panels] [data-material-feature-grid] {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1rem 1.5rem;
                width: 100%;
                max-width: 30rem;
                margin-top: 1.25rem;
            }

            [data-product-attribute-panels] [data-material-feature] {
                min-width: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            [data-product-attribute-panels] [data-material-feature-icon] {
                display: block;
                width: 40px;
                height: 40px;
                object-fit: contain;
            }

            [data-product-attribute-panels] [data-material-feature-label] {
                margin-top: 0.375rem;
                color: #0f172a;
                font-size: 10px !important;
                line-height: 1.2 !important;
                font-weight: 600;
                letter-spacing: 0.1em;
                text-transform: uppercase;
            }

            @media (min-width: 640px) {
                [data-product-attribute-panels] [data-material-feature-grid] {
                    column-gap: 3rem;
                }

                [data-product-attribute-panels] [data-material-feature-icon] {
                    width: 42px;
                    height: 42px;
                }
            }
        </style>
    @endonce

    <div
        class="{{ trim(($containerClass ?? '').' overflow-hidden px-1') }}"
        data-product-attribute-panels
    >
        <div class="space-y-0">
            @foreach ($attributePanels as $panel)
                <details
                    class="group border-b border-slate-300/70 last:border-b-0"
                    @if ($loop->first) open @endif
                    data-product-attribute-group="{{ $panel['group_code'] }}"
                >
                    <summary class="flex cursor-pointer items-center justify-between gap-4 py-[1.05rem]">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="shrink-0 text-slate-600" aria-hidden="true">
                                @switch($panel['group_code'])
                                    @case('sastav')
                                        <img src="{{ asset('assets/payments/MATERIJAL.svg') }}" alt="" class="h-10 w-10 object-contain" loading="lazy" decoding="async">
                                        @break
                                    @case('kvaliteta')
                                        <img src="{{ asset('assets/payments/KVALITETA.svg') }}" alt="" class="h-10 w-10 object-contain" loading="lazy" decoding="async">
                                        @break
                                    @default
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7">
                                            <path d="M12 3l7 3v5c0 4.4-2.8 7.8-7 9-4.2-1.2-7-4.6-7-9V6l7-3Z"></path>
                                            <path d="m9.5 11.8 1.7 1.7 3.5-3.8"></path>
                                        </svg>
                                @endswitch
                            </span>
                            <div class="min-w-0">
                                <p class="text-[0.95rem] font-semibold uppercase tracking-[0.14em] text-slate-900">{{ $panel['group_name'] }}</p>
                            </div>
                        </div>

                        <svg class="h-[18px] w-[18px] shrink-0 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" data-attribute-chevron aria-hidden="true">
                            <path d="m5 7.5 5 5 5-5"></path>
                        </svg>
                    </summary>

                    <div class="pb-4 pl-8 pr-6 text-[0.95rem] leading-relaxed text-slate-600">
                        @if (count($panel['items']) === 1)
                            <div>{!! nl2br(e($panel['items'][0]['name'])) !!}</div>
                        @else
                            <ul class="space-y-2">
                                @foreach ($panel['items'] as $item)
                                    <li class="flex gap-2">
                                        <span class="shrink-0 text-slate-400">-</span>
                                        <span>{!! nl2br(e($item['name'])) !!}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($panel['group_code'] === 'sastav' && count($materialFeatureIcons) > 0)
                            <div data-material-feature-grid aria-label="Karakteristike materijala">
                                @foreach ($materialFeatureIcons as $featureIcon)
                                    <figure data-material-feature>
                                        <img
                                            src="{{ asset('assets/payments/'.$featureIcon['icon']) }}"
                                            alt=""
                                            data-material-feature-icon
                                            loading="lazy"
                                            decoding="async"
                                            aria-hidden="true"
                                        >
                                        <figcaption data-material-feature-label>
                                            {{ $featureIcon['label'] }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endif
