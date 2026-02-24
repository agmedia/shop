@extends('front.mobile.layouts.store')

@section('title', 'Shop')
@section('header_title', 'Listing')
@section('page_title', 'Shop')

@section('content')
    <div class="card card-style">
        <div class="content mb-0">
            <p class="mb-n1 font-600 color-highlight">Storefront</p>
            <h2 class="mb-3">Product Listing</h2>

            <form method="GET" action="{{ route('shop.index') }}">
                <div class="row mb-0">
                    <div class="col-6">
                        <div class="input-style has-borders no-icon input-style-always-active mb-3">
                            <label for="shop-category" class="color-highlight font-500">Category</label>
                            <select id="shop-category" name="category">
                                <option value="">All</option>
                                @foreach ($categories as $category)
                                    @php
                                        $translation = $category->translations->firstWhere('locale', $locale)
                                            ?? $category->translations->firstWhere('locale', $fallbackLocale);
                                    @endphp
                                    <option value="{{ $translation?->slug }}" @selected($filters['category'] === ($translation?->slug ?? ''))>
                                        {{ $translation?->name ?? $category->code }}
                                    </option>
                                @endforeach
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-style has-borders no-icon input-style-always-active mb-3">
                            <label for="shop-sort" class="color-highlight font-500">Sort</label>
                            <select id="shop-sort" name="sort">
                                <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                                <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest</option>
                                <option value="price_low" @selected($filters['sort'] === 'price_low')>Price Low</option>
                                <option value="price_high" @selected($filters['sort'] === 'price_high')>Price High</option>
                                <option value="stock_high" @selected($filters['sort'] === 'stock_high')>Stock</option>
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                        </div>
                    </div>
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="shop-search" class="color-highlight font-500">Search</label>
                    <input id="shop-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Product name">
                </div>

                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="shop-manufacturer" class="color-highlight font-500">Manufacturer</label>
                    <select id="shop-manufacturer" name="manufacturer">
                        <option value="">All</option>
                        @foreach ($manufacturers as $manufacturer)
                            @php
                                $translation = $manufacturer->translations->firstWhere('locale', $locale)
                                    ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                            @endphp
                            <option value="{{ $translation?->slug }}" @selected($filters['manufacturer'] === ($translation?->slug ?? ''))>
                                {{ $translation?->name ?? $manufacturer->code }}
                            </option>
                        @endforeach
                    </select>
                    <span><i class="fa fa-chevron-down"></i></span>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-s font-600 gradient-blue rounded-sm w-100">Apply</button>
                    <a href="{{ route('shop.index') }}" class="btn btn-s font-600 btn-border border-gray-dark color-gray-dark rounded-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @forelse ($products as $product)
        @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">No products found.</p></div></div>
    @endforelse

    @if ($products->hasPages())
        <div class="card card-style"><div class="content">{{ $products->links('pagination::bootstrap-5') }}</div></div>
    @endif
@endsection
