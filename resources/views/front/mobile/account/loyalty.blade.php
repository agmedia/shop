@extends('front.mobile.layouts.store')

@section('title', __('ui.account.loyalty.page_title'))
@section('header_title', __('ui.account.nav.dashboard'))
@section('page_title', __('ui.account.loyalty.title'))

@section('content')
    <div class="card card-style">
        <div class="content">
            <div class="d-flex mb-2">
                <h4 class="mb-0">{{ __('ui.account.loyalty.title') }}</h4>
                <a href="{{ route('account.dashboard') }}" class="ms-auto font-12 color-highlight">{{ __('ui.account.nav.dashboard') }}</a>
            </div>
            <p class="mb-0 font-12 opacity-70">{{ __('ui.account.loyalty.subtitle') }}</p>
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <div class="d-flex mb-2"><span>{{ __('ui.account.loyalty.cards.balance') }}</span><strong class="ms-auto">{{ $balance }}</strong></div>
            <div class="d-flex mb-2"><span>{{ __('ui.account.loyalty.cards.earned') }}</span><strong class="ms-auto color-green-dark">+{{ $earned }}</strong></div>
            <div class="d-flex"><span>{{ __('ui.account.loyalty.cards.spent') }}</span><strong class="ms-auto color-red-dark">-{{ $spent }}</strong></div>
            <div class="divider my-3"></div>
            <p class="font-12 opacity-70 mb-1">{{ __('ui.account.loyalty.usage.min_order', ['amount' => \App\Support\Currency::format($minOrderTotal)]) }}</p>
            <p class="font-12 opacity-70 mb-0">{{ __('ui.account.loyalty.usage.instant') }}</p>
        </div>
    </div>

    @forelse ($transactions as $entry)
        @php
            $isEarn = (int) $entry->points > 0;
            $typeKey = 'ui.account.loyalty.types.'.$entry->type;
            $typeLabel = \Illuminate\Support\Facades\Lang::has($typeKey) ? __($typeKey) : ucfirst(str_replace('_', ' ', (string) $entry->type));
            $noteMap = [
                'Auto settlement from order status sync.' => __('ui.account.loyalty.notes.auto_settlement'),
                'Order discount consumed through loyalty redemption.' => __('ui.account.loyalty.notes.redemption_consumed'),
                'Auto reversal from order status sync.' => __('ui.account.loyalty.notes.auto_reversal'),
            ];
            $noteLabel = $entry->note ? ($noteMap[$entry->note] ?? $entry->note) : null;
        @endphp
        <div class="card card-style">
            <div class="content">
                <div class="d-flex mb-1">
                    <h5 class="mb-0">{{ $typeLabel }}</h5>
                    <strong class="ms-auto {{ $isEarn ? 'color-green-dark' : 'color-red-dark' }}">
                        {{ (int) $entry->points > 0 ? '+' : '' }}{{ (int) $entry->points }}
                    </strong>
                </div>
                <p class="mb-1 opacity-70 font-12">{{ optional($entry->created_at)->format('Y-m-d H:i') }}</p>
                @if ($entry->order)
                    <p class="mb-1 font-12">{{ __('ui.account.loyalty.table.order') }}: {{ $entry->order->order_number }}</p>
                @endif
                @if ($noteLabel)
                    <p class="mb-0 opacity-70 font-12">{{ $noteLabel }}</p>
                @endif
            </div>
        </div>
    @empty
        <div class="card card-style">
            <div class="content">
                <p class="mb-0 opacity-70">{{ __('ui.account.loyalty.empty') }}</p>
            </div>
        </div>
    @endforelse

    @if ($transactions->hasPages())
        <div class="card card-style">
            <div class="content">{{ $transactions->links('pagination::bootstrap-5') }}</div>
        </div>
    @endif
@endsection
