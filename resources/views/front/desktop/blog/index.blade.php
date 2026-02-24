@extends('front.desktop.layouts.store')

@section('title', __('ui.blog.page_title'))

@section('content')
    <section class="mb-8 px-1">
        <nav aria-label="Breadcrumb" class="mb-3 text-center">
            <ol class="inline-flex items-center justify-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                <li><a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a></li>
                <li class="text-slate-400">/</li>
                <li class="text-slate-700">{{ __('ui.blog.title') }}</li>
            </ol>
        </nav>
        <div class="bg-slate-100 px-8 py-8 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">{{ __('ui.blog.title') }}</h1>
            <p class="mt-2 text-slate-600">{{ __('ui.blog.subtitle') }}</p>
        </div>
    </section>

    @if ($topBlocks->isNotEmpty())
        <section class="mb-8">@include('components.content-placement', ['items' => $topBlocks])</section>
    @endif

    @if ($posts->isEmpty())
        <div class="border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('ui.blog.empty') }}</div>
    @else
        <section class="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                @php
                    $translation = $post->translations->firstWhere('locale', $locale)
                        ?? $post->translations->firstWhere('locale', $fallbackLocale);
                    $postImage = $post->getFirstMedia('blog_cover');
                    $postImageUrl = $postImage ? $postImage->getUrl() : null;
                @endphp

                <article>
                    <a href="{{ route('blog.show', ['slug' => $translation?->slug ?? $post->id]) }}" class="group block">
                        <div class="aspect-[3/4] overflow-hidden bg-slate-200">
                            @if ($postImageUrl)
                                <img
                                    src="{{ $postImageUrl }}"
                                    alt="{{ $translation?->title ?? $post->code }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.product.no_image') }}</div>
                            @endif
                        </div>

                        <h2 class="mt-4 text-center text-2xl font-semibold leading-tight text-slate-800">
                            {{ $translation?->title ?? $post->code }}
                        </h2>

                        <p class="mx-auto mt-4 max-w-[30ch] text-center text-sm leading-relaxed text-slate-700">
                            {{ $translation?->excerpt ?: __('ui.blog.excerpt_fallback') }}
                        </p>
                    </a>
                </article>
            @endforeach
        </section>

        <div class="mt-10">{{ $posts->links() }}</div>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10">@include('components.content-placement', ['items' => $bottomBlocks])</section>
    @endif
@endsection
