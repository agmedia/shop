@extends('front.mobile.layouts.store')

@php
    $translation = $post->translations->firstWhere('locale', $locale)
        ?? $post->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->title ?? 'Article')
@section('header_title', 'Article')
@section('page_title', 'Article')

@section('content')
    <div class="card rounded-0 bg-20" data-card-height="280">
        <div class="card-bottom text-end pe-3 pb-3">
            <h2 class="color-white font-700 mb-n1">{{ $translation?->title ?? $post->code }}</h2>
            <p class="color-white font-12 opacity-60">{{ optional($post->published_at)->format('Y-m-d') ?: 'Draft' }}</p>
        </div>
        <div class="card-overlay bg-gradient"></div>
    </div>

    <div class="card card-style card-full-right" style="margin-top:-60px; z-index:1;">
        <div class="content">
            <p class="mb-n1 color-highlight font-600">Blog Post</p>
            <h2>{{ $translation?->title ?? $post->code }}</h2>
            <p>{{ $translation?->excerpt }}</p>
            <div class="font-13">{!! $translation?->body_html ?: '<p>No content available.</p>' !!}</div>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <div class="card card-style">
            <div class="content mb-2">
                <h4>Related Articles</h4>
            </div>
        </div>

        @foreach ($related as $post)
            @php
                $relatedTranslation = $post->translations->firstWhere('locale', $locale)
                    ?? $post->translations->firstWhere('locale', $fallbackLocale);
            @endphp
            <a href="{{ route('blog.show', ['slug' => $relatedTranslation?->slug ?? $post->id]) }}" class="card card-style d-block mb-2">
                <div class="content">
                    <h5 class="mb-1">{{ $relatedTranslation?->title ?? $post->code }}</h5>
                    <p class="mb-0 opacity-70 font-12">{{ $relatedTranslation?->excerpt ?: 'Open article for details.' }}</p>
                </div>
            </a>
        @endforeach
    @endif
@endsection
