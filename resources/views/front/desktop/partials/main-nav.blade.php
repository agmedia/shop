@php
    $hasNavigation = !empty($mainNavigation ?? []);
@endphp

@if ($hasNavigation)
    @foreach ($mainNavigation as $item)
        @php
            $children = collect($item['children'] ?? []);
            $hasChildren = $children->isNotEmpty();
            $href = (string) ($item['url'] ?? '#');
            $target = !empty($item['open_in_new_tab']) ? '_blank' : null;
            $rel = !empty($item['open_in_new_tab']) ? 'noopener noreferrer' : null;
            $topItemWeightClass = $loop->index < 4 ? 'font-semibold' : 'font-normal';
        @endphp

        @if ($hasChildren)
            <div class="group/nav">
                <a href="{{ $href }}" class="inline-flex items-center py-6 text-[14px] {{ $topItemWeightClass }} uppercase tracking-[0.03em] text-slate-900 transition hover:text-black" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif>
                    <span class="border-b border-transparent pb-0.5 transition group-hover/nav:border-slate-400">{{ $item['label'] }}</span>
                </a>

                @php
                    $promo = is_array($item['mega_promo'] ?? null) ? $item['mega_promo'] : [];
                    $promoImage = trim((string) ($promo['image_url'] ?? ''));
                    $promoTitle = trim((string) ($promo['title'] ?? ''));
                    $promoSubtitle = trim((string) ($promo['subtitle'] ?? ''));
                    $promoCtaLabel = trim((string) ($promo['cta_label'] ?? ''));
                    $promoCtaUrl = trim((string) ($promo['cta_url'] ?? ''));
                    $hasPromo = $promoImage !== '' || $promoTitle !== '' || $promoSubtitle !== '' || ($promoCtaLabel !== '' && $promoCtaUrl !== '');
                    $panelWidthClass = 'w-[90vw]';
                    $isSpecialOffer = \Illuminate\Support\Str::of((string) ($item['label'] ?? ''))
                        ->lower()
                        ->contains('posebna');
                    $megaGroups = $children->map(function ($child): array {
                        $blocks = collect($child['children'] ?? [])->map(function ($subChild): array {
                            return [
                                'title' => (string) ($subChild['label'] ?? ''),
                                'url' => (string) ($subChild['url'] ?? '#'),
                                'items' => collect($subChild['children'] ?? []),
                            ];
                        });

                        if ($blocks->isEmpty()) {
                            $blocks = collect([[
                                'title' => (string) ($child['label'] ?? ''),
                                'url' => (string) ($child['url'] ?? '#'),
                                'items' => collect(),
                            ]]);
                        }

                        $perColumn = (int) ceil($blocks->count() / 2);
                        $chunks = $blocks->chunk(max(1, $perColumn))->values();

                        return [
                            'label' => (string) ($child['label'] ?? ''),
                            'columns' => [
                                $chunks->get(0, collect()),
                                $chunks->get(1, collect()),
                            ],
                        ];
                    })->values();

                    $megaBlocks = collect();
                    foreach ($children as $child) {
                        $subChildren = collect($child['children'] ?? []);
                        $megaBlocks->push([
                            'title' => (string) ($child['label'] ?? ''),
                            'url' => (string) ($child['url'] ?? '#'),
                            'items' => $subChildren,
                        ]);
                    }
                @endphp

                <div class="invisible fixed left-1/2 top-[114px] z-50 mt-0 {{ $panelWidthClass }} -translate-x-1/2 bg-white px-7 py-5 opacity-0 shadow-[0_24px_50px_-30px_rgba(15,23,42,0.55)] transition-all duration-150 group-hover/nav:visible group-hover/nav:opacity-100">
                    <div class="grid items-start gap-4 {{ $hasPromo ? 'grid-cols-[minmax(0,4fr)_minmax(240px,1fr)]' : 'grid-cols-1' }}">
                        <div class="max-h-[52vh] overflow-y-auto pr-1">
                            @if ($isSpecialOffer)
                                <div class="mx-auto grid gap-x-6 gap-y-4 lg:grid-cols-2">
                                    @foreach ($megaGroups as $group)
                                        <section>
                                            <h3 class="mb-2 text-[17px] font-black uppercase tracking-[0.06em] text-slate-900">{{ $group['label'] }}</h3>
                                            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                                @foreach ($group['columns'] as $columnBlocks)
                                                    <div>
                                                        @foreach ($columnBlocks as $block)
                                                            <div class="mb-3">
                                                                <a href="{{ $block['url'] }}" class="text-[15px] font-extrabold uppercase tracking-[0.05em] text-slate-900 transition hover:text-black">
                                                                    {{ $block['title'] }}
                                                                </a>
                                                                <ul class="mt-1.5 space-y-0.5">
                                                                    @foreach ($block['items'] as $subChild)
                                                                        @include('front.desktop.partials.main-nav-child', ['child' => $subChild, 'level' => 0])
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endforeach
                                </div>
                            @else
                                <div class="mx-auto columns-2 gap-6 lg:columns-4">
                                    @foreach ($megaBlocks as $block)
                                        <div class="mb-4 break-inside-avoid">
                                            <a href="{{ $block['url'] }}" class="text-[15px] font-extrabold uppercase tracking-[0.05em] text-slate-900 transition hover:text-black">
                                                {{ $block['title'] }}
                                            </a>
                                            <ul class="mt-1.5 space-y-0.5">
                                                @foreach ($block['items'] as $subChild)
                                                    @include('front.desktop.partials.main-nav-child', ['child' => $subChild, 'level' => 0])
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($hasPromo)
                            <aside class="hidden border border-slate-300 bg-white p-2 xl:block">
                                @if ($promoCtaUrl !== '')
                                    <a href="{{ $promoCtaUrl }}" class="group/promo block">
                                @endif
                                    <div class="relative aspect-[4/5] overflow-hidden bg-slate-200">
                                        @if ($promoImage !== '')
                                            <img src="{{ $promoImage }}" alt="{{ $promoTitle !== '' ? $promoTitle : $item['label'] }}" class="h-full w-full object-cover transition duration-300 group-hover/promo:scale-[1.02]">
                                        @else
                                            <div class="h-full w-full bg-gradient-to-br from-slate-200 via-slate-100 to-slate-300"></div>
                                        @endif
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/60 to-transparent p-3 text-white">
                                            @if ($promoTitle !== '')
                                                <p class="text-sm font-extrabold uppercase tracking-[0.06em]">{{ $promoTitle }}</p>
                                            @endif
                                            @if ($promoSubtitle !== '')
                                                <p class="mt-0.5 text-[11px] text-white/90">{{ $promoSubtitle }}</p>
                                            @endif
                                            @if ($promoCtaLabel !== '' && $promoCtaUrl !== '')
                                                <span class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/95">
                                                    {{ $promoCtaLabel }}
                                                    <span aria-hidden="true">→</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @if ($promoCtaUrl !== '')
                                    </a>
                                @endif
                            </aside>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <a href="{{ $href }}" class="inline-flex items-center py-6 text-[14px] {{ $topItemWeightClass }} uppercase tracking-[0.03em] text-slate-900 transition hover:text-black" @if($target) target="{{ $target }}" rel="{{ $rel }}" @endif>
                <span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach
@else
    <a href="{{ route('shop.index') }}" class="inline-flex items-center py-6 hover:text-slate-600"><span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">{{ __('ui.front.desktop.nav.new') }}</span></a>
    <a href="{{ route('categories.index') }}" class="inline-flex items-center py-6 hover:text-slate-600"><span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">Kategorije</span></a>
    @if ($catalogFeatures->useBlog())
        <a href="{{ route('blog.index') }}" class="inline-flex items-center py-6 hover:text-slate-600"><span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">{{ __('ui.front.desktop.nav.blog') }}</span></a>
    @endif
    <a href="{{ route('faq.index') }}" class="inline-flex items-center py-6 hover:text-slate-600"><span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">{{ __('ui.front.desktop.nav.faq') }}</span></a>
    <a href="{{ route('contact.create') }}" class="inline-flex items-center py-6 hover:text-slate-600"><span class="border-b border-transparent pb-0.5 transition hover:border-slate-400">{{ __('ui.front.desktop.nav.contact') }}</span></a>
@endif
