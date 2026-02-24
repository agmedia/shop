@extends('front.mobile.layouts.store')

@section('title', __('ui.blog.page_title'))
@section('header_title', __('ui.blog.title'))
@section('page_title', __('ui.blog.title'))

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    @if ($posts->isEmpty())
        <div class="card card-style">
            <div class="content">
                <p class="mb-0">{{ __('ui.blog.empty') }}</p>
            </div>
        </div>
    @else
        @foreach ($posts as $post)
            @php
                $translation = $post->translations->firstWhere('locale', $locale)
                    ?? $post->translations->firstWhere('locale', $fallbackLocale);
                $postImage = $post->getFirstMedia('blog_cover');
                $postImageUrl = $postImage ? $postImage->getUrl() : null;
            @endphp

            <article class="card card-style mb-3">
                <a href="{{ route('blog.show', ['slug' => $translation?->slug ?? $post->id]) }}" class="d-block text-decoration-none color-theme">
                    @if ($postImageUrl)
                        <img src="{{ $postImageUrl }}" alt="{{ $translation?->title ?? $post->code }}" class="img-fluid" style="width:100%; aspect-ratio:3/4; object-fit:cover;" loading="lazy" decoding="async">
                    @endif
                    <div class="content">
                        <h3 class="font-700 mb-2">{{ $translation?->title ?? $post->code }}</h3>
                        <p class="font-13 opacity-80 mb-0">{{ $translation?->excerpt ?: __('ui.blog.excerpt_fallback') }}</p>
                    </div>
                </a>
            </article>
        @endforeach

        @if ($posts->hasPages())
            <div class="card card-style"><div class="content">{{ $posts->links('pagination::bootstrap-5') }}</div></div>
        @endif
    @endif

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
