@extends('front.mobile.layouts.store')

@php
    $translation = $manufacturer->translations->firstWhere('locale', $locale)
        ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->name ?? 'Manufacturer')
@section('header_title', 'Brand')
@section('page_title', $translation?->name ?? 'Brand')

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style">
        <div class="content">
            <h4>{{ $translation?->name ?? $manufacturer->code }}</h4>
            <p class="mb-0 opacity-70">{{ $translation?->description ?: 'Products from this manufacturer.' }}</p>
        </div>
    </div>

    @forelse ($products as $product)
        @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No products available.</p></div></div>
    @endforelse

    @if ($products->hasPages())
        <div class="card card-style"><div class="content">{{ $products->links('pagination::bootstrap-5') }}</div></div>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
