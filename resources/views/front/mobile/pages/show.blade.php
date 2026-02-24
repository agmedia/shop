@extends('front.mobile.layouts.store')

@php
    $translation = $selectedTranslation
        ?? $page->translations->firstWhere('locale', $locale)
        ?? $page->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->title ?? 'Page')
@section('header_title', 'Page')
@section('page_title', $translation?->title ?? 'Page')

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style rounded-0">
        <div class="content">
            <div class="bg-light border p-3 mb-3">
                <h2 class="mb-1">{{ $translation?->title ?? $page->code }}</h2>
                @if (!empty($translation?->excerpt))
                    <p class="mb-0">{{ $translation->excerpt }}</p>
                @endif
            </div>
            <div class="font-13 content-richtext">{!! $translation?->body_html ?: '<p>No content available.</p>' !!}</div>
        </div>
    </div>

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
