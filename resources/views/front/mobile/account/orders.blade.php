@extends('front.mobile.layouts.store')

@section('title', __('ui.account.orders.page_title'))
@section('header_title', __('ui.account.breadcrumb.account'))
@section('page_title', __('ui.account.orders.title'))

@section('content')
    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex mb-1">
                <h4 class="mb-0">{{ __('ui.account.orders.title') }}</h4>
                <a href="{{ route('account.dashboard') }}" class="ms-auto font-12 color-highlight">{{ __('ui.account.nav.dashboard') }}</a>
            </div>
            <p class="font-12 opacity-70 mb-0">{{ __('ui.account.orders.subtitle') }}</p>
        </div>
    </div>

    @forelse ($orders as $order)
        <a href="{{ route('account.orders.show', ['orderNumber' => $order->order_number]) }}" class="card card-style d-block mb-2">
            <div class="content">
                <div class="d-flex mb-1">
                    <h5 class="mb-0">{{ $order->order_number }}</h5>
                    <p class="ms-auto mb-0 font-13">{{ \App\Support\Currency::format((float) $order->grand_total, $order->currency_code) }}</p>
                </div>
                <p class="mb-1 opacity-70 font-12">{{ optional($order->placed_at ?? $order->created_at)->format('Y-m-d H:i') }}</p>
                <span class="badge bg-highlight">{{ $order->status?->name ?? __('ui.account.orders.status_new') }}</span>
            </div>
        </a>
    @empty
        <div class="card card-style"><div class="content"><p class="mb-0">{{ __('ui.account.orders.empty') }}</p></div></div>
    @endforelse

    @if ($orders->hasPages())
        <div class="card card-style"><div class="content">{{ $orders->links('pagination::bootstrap-5') }}</div></div>
    @endif
@endsection
