@extends('front.mobile.layouts.store')

@php
    $translation = $category->translations->firstWhere('locale', $locale)
        ?? $category->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->name ?? 'Category')
@section('header_title', 'Category')
@section('page_title', $translation?->name ?? 'Category')

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style">
        <div class="content">
            <h3 class="mb-2">{{ $translation?->name ?? $category->code }}</h3>
            <p class="mb-0 opacity-70">{{ $translation?->clean_description ?: 'Assigned catalog products.' }}</p>
        </div>
    </div>

    @forelse ($products as $product)
        @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No products in this category.</p></div></div>
    @endforelse

    @if ($products->hasPages())
        <div class="card card-style"><div class="content">{{ $products->links('pagination::bootstrap-5') }}</div></div>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
