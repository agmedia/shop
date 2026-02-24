@extends('front.mobile.layouts.store')

@section('title', __('ui.wishlist.page_title'))
@section('header_title', __('ui.wishlist.title'))
@section('page_title', __('ui.wishlist.title'))

@section('content')
    @if ($products->isEmpty())
        <div class="card card-style">
            <div class="content">
                <p class="mb-0">{{ __('ui.wishlist.empty') }}</p>
            </div>
        </div>
    @else
        @foreach ($products as $product)
            @include('front.mobile.partials.product-card', ['product' => $product, 'locale' => $locale, 'fallbackLocale' => $fallbackLocale])
        @endforeach
    @endif
@endsection
