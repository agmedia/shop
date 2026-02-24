@extends('front.desktop.layouts.store')

@php
    use Illuminate\Support\Str;

    $translation = $post->translations->firstWhere('locale', $locale)
        ?? $post->translations->firstWhere('locale', $fallbackLocale);
    $mediaItems = $post->relationLoaded('media')
        ? $post->media
            ->sortBy(static fn ($mediaItem) => (int) ($mediaItem->order_column ?? 0))
            ->values()
        : collect();
    $coverImage = $mediaItems->firstWhere('collection_name', 'blog_cover') ?? $post->getFirstMedia('blog_cover');
    $coverImageUrl = $coverImage ? $coverImage->getUrl() : null;
    $galleryItems = $mediaItems->where('collection_name', 'blog_gallery')->values();
    if ($galleryItems->isEmpty()) {
        $galleryItems = $post->getMedia('blog_gallery')
            ->sortBy(static fn ($mediaItem) => (int) ($mediaItem->order_column ?? 0))
            ->values();
    }
    $galleryCount = $galleryItems->count();
    $galleryColumnsClass = match (true) {
        $galleryCount <= 1 => 'grid-cols-1',
        $galleryCount === 2 => 'grid-cols-1 md:grid-cols-2',
        $galleryCount === 4 => 'grid-cols-1 md:grid-cols-2',
        default => 'grid-cols-1 md:grid-cols-3',
    };
@endphp

@section('title', $translation?->title ?? __('ui.blog.page_title'))

@section('content')
    <section class="mb-8 px-1">
        <nav aria-label="Breadcrumb" class="mb-4 text-center">
            <ol class="inline-flex max-w-full items-center justify-center gap-2 text-[11px] font-medium tracking-[0.08em] text-slate-500">
                <li><a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a></li>
                <li class="text-slate-400">/</li>
                <li><a href="{{ route('blog.index') }}" class="hover:text-slate-700">{{ __('ui.blog.title') }}</a></li>
                <li class="text-slate-400">/</li>
                <li class="max-w-[42ch] truncate text-slate-700">{{ Str::limit((string) ($translation?->title ?? $post->code), 78, '...') }}</li>
            </ol>
        </nav>
        <div class="border border-slate-200 bg-slate-100/80 px-8 py-9 text-center">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Editorial</p>
            <h1 class="mx-auto mt-3 max-w-4xl text-[1.7rem] font-semibold leading-[1.12] tracking-[-0.01em] text-slate-900 md:text-[2.2rem]">{{ $translation?->title ?? $post->code }}</h1>
            @if (!empty($translation?->excerpt))
                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-slate-600">{{ $translation->excerpt }}</p>
            @endif
        </div>
    </section>

    <article class="bg-white px-2 py-2">
        <div class="mx-auto w-full max-w-4xl">
            @if ($post->published_at)
                <p class="mb-4 inline-flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="17" rx="2"></rect>
                        <line x1="8" y1="2.5" x2="8" y2="6"></line>
                        <line x1="16" y1="2.5" x2="16" y2="6"></line>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                    </svg>
                    <span>{{ $post->published_at->format('d.m.Y.') }}</span>
                </p>
            @endif

            @if ($coverImageUrl)
                <figure class="mb-8">
                    <img
                        src="{{ $coverImageUrl }}"
                        alt="{{ $translation?->title ?? $post->code }}"
                        class="h-auto w-full object-cover"
                        loading="eager"
                        decoding="async"
                    >
                </figure>
            @endif

            <div class="content-richtext">
                {!! $translation?->body_html ?: '<p>No body content available.</p>' !!}
            </div>
        </div>
    </article>

    @if ($galleryItems->isNotEmpty())
        <section class="mt-12 border-t border-slate-200 pt-8">
            <h2 class="mb-6 text-center text-2xl font-semibold tracking-tight text-slate-900">Editorial</h2>
            <div class="grid gap-5 {{ $galleryColumnsClass }}" data-blog-gallery>
                @foreach ($galleryItems as $mediaItem)
                    @php
                        $galleryImageUrl = $mediaItem->getUrl();
                    @endphp
                    <a
                        href="{{ $galleryImageUrl }}"
                        class="block aspect-[3/4] overflow-hidden bg-slate-100"
                        data-blog-gallery-item
                        data-sub-html="{{ $translation?->title ?? $post->code }}"
                    >
                        <img
                            src="{{ $galleryImageUrl }}"
                            alt="{{ $translation?->title ?? $post->code }}"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (($relatedProducts ?? collect())->isNotEmpty())
        <section class="relative left-1/2 right-1/2 mt-14 -ml-[50vw] -mr-[50vw] w-screen bg-white py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="mb-8 text-center">
                    <div class="mx-auto flex max-w-3xl items-center gap-4 md:gap-6">
                        <span class="h-px flex-1 bg-slate-300"></span>
                        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">{{ __('Povezani artikli') }}</h2>
                        <span class="h-px flex-1 bg-slate-300"></span>
                    </div>
                </div>

                <style>
                    #blog-related-products-carousel-{{ $post->id }} .splide__arrow {
                        opacity: 0;
                        width: 46px;
                        height: 46px;
                        border-radius: 9999px;
                        border: 1px solid rgba(255, 255, 255, 0.75);
                        background: rgba(15, 23, 42, 0.35);
                        backdrop-filter: blur(6px);
                        transform: translateY(-50%) scale(0.92);
                        transition: opacity .25s ease, transform .25s ease, background-color .25s ease;
                    }

                    #blog-related-products-carousel-{{ $post->id }}:hover .splide__arrow,
                    #blog-related-products-carousel-{{ $post->id }}:focus-within .splide__arrow {
                        opacity: 1;
                        transform: translateY(-50%) scale(1);
                    }

                    #blog-related-products-carousel-{{ $post->id }} .splide__arrow:hover {
                        background: rgba(15, 23, 42, 0.55);
                    }

                    #blog-related-products-carousel-{{ $post->id }} .splide__arrow svg {
                        fill: #fff;
                    }

                    @media (hover: none) {
                        #blog-related-products-carousel-{{ $post->id }} .splide__arrow {
                            opacity: 1;
                            transform: translateY(-50%) scale(1);
                        }
                    }
                </style>

                <div class="mt-4">
                    <div id="blog-related-products-carousel-{{ $post->id }}" class="splide" data-blog-related-products-splide>
                        <div class="splide__track">
                            <ul class="splide__list">
                                @foreach ($relatedProducts as $relatedProduct)
                                    <li class="splide__slide">
                                        @include('front.desktop.partials.product-card', [
                                            'product' => $relatedProduct,
                                            'locale' => $locale,
                                            'fallbackLocale' => $fallbackLocale,
                                            'flat' => true,
                                        ])
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery-bundle.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
    <script defer>
        document.addEventListener('DOMContentLoaded', function () {
            const galleryRoot = document.querySelector('[data-blog-gallery]');
            if (!galleryRoot || typeof window.lightGallery !== 'function') {
                return;
            }

            window.lightGallery(galleryRoot, {
                selector: '[data-blog-gallery-item]',
                download: false,
                counter: true,
            });

            const sliders = document.querySelectorAll('[data-blog-related-products-splide]');
            sliders.forEach(function (el) {
                if (el.dataset.splideReady === '1' || typeof window.Splide !== 'function') {
                    return;
                }
                el.dataset.splideReady = '1';

                const count = el.querySelectorAll('.splide__slide').length;
                new window.Splide(el, {
                    type: count > 1 ? 'loop' : 'slide',
                    perPage: Math.min(4, Math.max(1, count)),
                    perMove: 1,
                    gap: '1.25rem',
                    drag: count > 1,
                    snap: true,
                    pagination: count > 1,
                    arrows: count > 1,
                    updateOnMove: true,
                    speed: 520,
                    breakpoints: {
                        1280: { perPage: Math.min(3, Math.max(1, count)) },
                        1024: { perPage: Math.min(2, Math.max(1, count)) },
                        860: { perPage: 1, gap: '1rem' },
                        640: { perPage: 1, gap: '0.8rem' },
                    },
                }).mount();
            });
        });
    </script>
@endpush
