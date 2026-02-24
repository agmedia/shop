@php
    $locale = app()->getLocale();
    $fallbackLocale = config('app.locale');
    $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
    $customClasses = trim((string) ($translationPayload['custom_classes'] ?? ''));
    $slides = $block->getMedia('block_slides')->take(2);
@endphp

@if ($slides->isNotEmpty())
    <section class="relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] w-screen {{ $customClasses }}">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            @foreach ($slides as $media)
                @php
                    $imageUrl = $media->hasGeneratedConversion('hero_1440x480')
                        ? $media->getUrl('hero_1440x480')
                        : $media->getUrl();
                    $props = (array) ($media->custom_properties ?? []);
                    $slideTitle = trim((string) (
                        data_get($props, "block_title.$locale")
                        ?: data_get($props, "block_title.$fallbackLocale")
                        ?: $media->name
                    ));

                    $cta1Label = trim((string) (
                        data_get($props, "cta_1_label.$locale")
                        ?: data_get($props, "cta_1_label.$fallbackLocale")
                        ?: __('ui.content_blocks.dual_image_cta.default_cta_1')
                    ));
                    $cta1Url = trim((string) (
                        data_get($props, "cta_1_url.$locale")
                        ?: data_get($props, "cta_1_url.$fallbackLocale")
                        ?: '#'
                    ));

                    $cta2Label = trim((string) (
                        data_get($props, "cta_2_label.$locale")
                        ?: data_get($props, "cta_2_label.$fallbackLocale")
                        ?: __('ui.content_blocks.dual_image_cta.default_cta_2')
                    ));
                    $cta2Url = trim((string) (
                        data_get($props, "cta_2_url.$locale")
                        ?: data_get($props, "cta_2_url.$fallbackLocale")
                        ?: '#'
                    ));
                @endphp

                <article class="group relative min-h-[360px] overflow-hidden md:min-h-[560px]">
                    <img src="{{ $imageUrl }}" alt="{{ $slideTitle !== '' ? $slideTitle : $block->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/20 to-transparent"></div>

                    <div class="absolute inset-x-0 bottom-12 px-8 text-center text-white md:bottom-16 md:px-10">
                        @if ($slideTitle !== '')
                            <h3 class="text-3xl font-black uppercase tracking-[0.02em] md:text-4xl">{{ $slideTitle }}</h3>
                        @endif

                        <div class="mx-auto mt-5 flex max-w-[460px] flex-col justify-center gap-2.5 sm:flex-row">
                            @if ($cta1Label !== '')
                                <a href="{{ $cta1Url !== '' ? $cta1Url : '#' }}" class="inline-flex h-11 min-w-[145px] items-center justify-center border border-white bg-white px-5 text-base font-black uppercase tracking-[0.02em] text-slate-900 transition hover:bg-slate-100">
                                    {{ $cta1Label }}
                                </a>
                            @endif

                            @if ($cta2Label !== '')
                                <a href="{{ $cta2Url !== '' ? $cta2Url : '#' }}" class="inline-flex h-11 min-w-[145px] items-center justify-center border border-white bg-white px-5 text-base font-black uppercase tracking-[0.02em] text-slate-900 transition hover:bg-slate-100">
                                    {{ $cta2Label }}
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
