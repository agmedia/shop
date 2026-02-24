@extends('front.mobile.layouts.store')

@section('title', 'Categories')
@section('header_title', 'Categories')
@section('page_title', 'Categories')

@section('content')
    <div class="card card-style">
        <div class="content">
            <p class="mb-n1 font-600 color-highlight">Browse</p>
            <h2>Category Search</h2>
            <form method="GET" action="{{ route('categories.index') }}" class="mt-3">
                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="cat-search" class="color-highlight">Search</label>
                    <input id="cat-search" type="search" name="q" value="{{ $search }}" placeholder="Category name">
                </div>
                <button type="submit" class="btn btn-s font-600 gradient-blue rounded-sm">Search</button>
            </form>
        </div>
    </div>

    @forelse ($categories as $category)
        @php
            $translation = $category->translations->firstWhere('locale', $locale)
                ?? $category->translations->firstWhere('locale', $fallbackLocale);
        @endphp
        <a href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="card card-style mb-2 d-block">
            <div class="content">
                <h5 class="mb-1">{{ $translation?->name ?? $category->code }}</h5>
                <p class="opacity-70 mb-2">{{ $translation?->clean_description ?: 'Open to view assigned products.' }}</p>
                <span class="badge bg-highlight">{{ $category->products_count }} products</span>
            </div>
        </a>
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No categories found.</p></div></div>
    @endforelse

    @if ($categories->hasPages())
        <div class="card card-style"><div class="content">{{ $categories->links('pagination::bootstrap-5') }}</div></div>
    @endif
@endsection
