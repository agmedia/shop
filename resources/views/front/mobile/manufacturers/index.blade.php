@extends('front.mobile.layouts.store')

@section('title', 'Manufacturers')
@section('header_title', 'Brands')
@section('page_title', 'Manufacturers')

@section('content')
    <div class="card card-style">
        <div class="content mb-1">
            <p class="mb-n1 color-highlight font-600">Catalog</p>
            <h2>Manufacturers</h2>
            <p class="mb-0">Ecommerce brand listing mapped from AppKit cards.</p>
        </div>
    </div>

    @forelse ($manufacturers as $manufacturer)
        @php
            $translation = $manufacturer->translations->firstWhere('locale', $locale)
                ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
        @endphp
        <a href="{{ route('manufacturers.show', ['slug' => $translation?->slug ?? $manufacturer->id]) }}" class="card card-style d-block mb-2">
            <div class="content">
                <h5 class="mb-1">{{ $translation?->name ?? $manufacturer->code }}</h5>
                <p class="opacity-70 font-12 mb-2">{{ $translation?->description ?: 'View products by this manufacturer.' }}</p>
                <span class="badge bg-highlight">{{ $manufacturer->products_count }} products</span>
            </div>
        </a>
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No active manufacturers.</p></div></div>
    @endforelse
@endsection
