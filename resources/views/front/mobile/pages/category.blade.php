@extends('front.mobile.layouts.store')

@php
    $categoryTranslation = $category->translations->firstWhere('locale', $locale)
        ?? $category->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $categoryTranslation?->name ?? 'Pages')
@section('header_title', 'Pages')
@section('page_title', $categoryTranslation?->name ?? 'Pages')

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style rounded-0">
        <div class="content">
            <div class="bg-light border p-3">
                <h3 class="mb-2">{{ $categoryTranslation?->name ?? 'Pages' }}</h3>
                @if (!empty($categoryTranslation?->clean_description))
                    <p class="mb-0 opacity-70">{{ $categoryTranslation->clean_description }}</p>
                @endif
            </div>
        </div>
    </div>

    @forelse ($pages as $page)
        @php
            $translation = $page->translations->firstWhere('locale', $locale)
                ?? $page->translations->firstWhere('locale', $fallbackLocale);
        @endphp
        <div class="card card-style">
            <div class="content">
                <h4 class="mb-2">{{ $translation?->title ?? $page->code }}</h4>
                @if (!empty($translation?->excerpt))
                    <p class="font-13 opacity-70 mb-2">{{ $translation->excerpt }}</p>
                @endif
                <a href="{{ route('pages.show', ['slug' => $translation?->slug ?? $page->id]) }}" class="font-600 text-uppercase font-11">Open page</a>
            </div>
        </div>
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No pages in this category.</p></div></div>
    @endforelse

    @if ($pages->hasPages())
        <div class="card card-style"><div class="content">{{ $pages->links('pagination::bootstrap-5') }}</div></div>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
