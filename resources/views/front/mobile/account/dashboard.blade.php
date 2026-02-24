@extends('front.mobile.layouts.store')

@section('title', __('ui.account.dashboard.page_title'))
@section('header_title', __('ui.account.breadcrumb.account'))
@section('page_title', __('ui.account.nav.dashboard'))

@section('content')
    <div class="card card-style bg-11" data-card-height="170">
        <div class="card-bottom ps-3 pb-3 pe-3">
            <p class="color-white opacity-70 mb-1">{{ __('ui.account.dashboard.cards.user') }}</p>
            <h2 class="color-white font-800 mb-0">{{ $user->name }}</h2>
            <p class="color-white opacity-70 mb-0">{{ $user->email }}</p>
        </div>
        <div class="card-overlay bg-black opacity-70"></div>
    </div>

    <div class="content mt-0 mb-1">
        <div class="row mb-0">
            <div class="col-6 pe-1">
                <a href="{{ route('account.orders') }}" class="card card-style mx-0 mb-2 p-3 d-block">
                    <h6 class="font-14 mb-1">{{ __('ui.account.nav.orders') }}</h6>
                    <h3 class="mb-0">{{ $orders->count() }}</h3>
                </a>
            </div>
            <div class="col-6 ps-1">
                <a href="{{ route('account.profile') }}" class="card card-style mx-0 mb-2 p-3 d-block">
                    <h6 class="font-14 mb-1">{{ __('ui.account.nav.edit_account') }}</h6>
                    <h3 class="mb-0"><i class="fa fa-user font-18"></i></h3>
                </a>
            </div>
            <div class="col-12">
                <div class="card card-style mx-0 p-3 mb-0">
                    <h6 class="font-14 mb-1">{{ __('ui.account.nav.loyalty') }}</h6>
                    @if ($loyaltyEnabled)
                        <a href="{{ route('account.loyalty') }}" class="d-block color-theme">
                            <h3 class="color-green-dark mb-0">{{ $loyaltyBalance }} {{ __('ui.account.dashboard.cards.points') }}</h3>
                        </a>
                    @else
                        <h3 class="opacity-50 mb-0">{{ __('ui.account.dashboard.cards.disabled') }}</h3>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <div class="d-flex mb-2">
                <h4 class="mb-0">{{ __('ui.account.dashboard.recent_orders.title') }}</h4>
                <a href="{{ route('account.orders') }}" class="ms-auto font-12 color-highlight">{{ __('ui.account.dashboard.cards.view_orders') }}</a>
            </div>

            @forelse ($orders as $order)
                <a href="{{ route('account.orders.show', ['orderNumber' => $order->order_number]) }}" class="d-block">
                    <div class="d-flex">
                        <div>
                            <h6 class="font-14 mb-1">{{ $order->order_number }}</h6>
                            <p class="font-11 opacity-60 mb-0">{{ optional($order->placed_at ?? $order->created_at)->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="ms-auto text-end">
                            <p class="font-11 opacity-60 mb-1">{{ $order->status?->name ?? __('ui.account.orders.status_new') }}</p>
                            <h6 class="font-14 mb-0">{{ \App\Support\Currency::format((float) $order->grand_total, $order->currency_code) }}</h6>
                        </div>
                    </div>
                </a>
                @if (! $loop->last)
                    <div class="divider my-2"></div>
                @endif
            @empty
                <p class="mb-0 opacity-70">{{ __('ui.account.orders.empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($loyaltyEnabled && $loyaltyRecent->isNotEmpty())
        <div class="card card-style">
            <div class="content">
                <h4 class="mb-2">{{ __('ui.account.dashboard.recent_loyalty.title') }}</h4>
                @foreach ($loyaltyRecent as $entry)
                    @php
                        $typeKey = 'ui.account.loyalty.types.'.$entry->type;
                        $typeLabel = \Illuminate\Support\Facades\Lang::has($typeKey)
                            ? __($typeKey)
                            : ucfirst(str_replace('_', ' ', (string) $entry->type));
                        $noteMap = [
                            'Auto settlement from order status sync.' => __('ui.account.loyalty.notes.auto_settlement'),
                            'Order discount consumed through loyalty redemption.' => __('ui.account.loyalty.notes.redemption_consumed'),
                            'Auto reversal from order status sync.' => __('ui.account.loyalty.notes.auto_reversal'),
                        ];
                        $noteLabel = $entry->note ? ($noteMap[$entry->note] ?? $entry->note) : null;
                    @endphp
                    <div class="d-flex mb-2">
                        <div>
                            <p class="font-13 mb-0">{{ $typeLabel }}</p>
                            <p class="font-11 opacity-60 mb-0">{{ optional($entry->created_at)->format('Y-m-d H:i') }}</p>
                            @if ($noteLabel)
                                <p class="font-11 opacity-60 mb-0">{{ $noteLabel }}</p>
                            @endif
                        </div>
                        <p class="ms-auto mb-0 font-700 {{ $entry->points >= 0 ? 'color-green-dark' : 'color-red-dark' }}">
                            {{ $entry->points >= 0 ? '+' : '' }}{{ $entry->points }}
                        </p>
                    </div>
                    @if (! $loop->last)
                        <div class="divider my-2"></div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endsection
