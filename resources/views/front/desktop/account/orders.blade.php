@extends('front.desktop.layouts.store')

@section('title', __('ui.account.orders.page_title'))

@section('content')
    @include('front.desktop.account.partials.breadcrumbs', ['items' => [
        ['label' => __('ui.account.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('ui.account.breadcrumb.account'), 'url' => route('account.dashboard')],
        ['label' => __('ui.account.orders.title')],
    ]])

    <section class="mb-8 border border-slate-200 bg-slate-100 px-6 py-6 text-center">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.account.orders.title') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('ui.account.orders.subtitle') }}</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        @include('front.desktop.account.partials.nav', ['current' => 'orders'])

        <div>
            <div class="overflow-hidden border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full text-sm">
                        <thead class="bg-slate-100/70 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('ui.account.orders.table.order') }}</th>
                            <th class="px-4 py-3">{{ __('ui.account.orders.table.placed') }}</th>
                            <th class="px-4 py-3">{{ __('ui.account.orders.table.status') }}</th>
                            <th class="px-4 py-3">{{ __('ui.account.orders.table.total') }}</th>
                            <th class="px-4 py-3">{{ __('ui.account.orders.table.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $order->order_number }}</td>
                                <td class="px-4 py-3">{{ optional($order->placed_at ?? $order->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">{{ $order->status?->name ?? __('ui.account.orders.status_new') }}</td>
                                <td class="px-4 py-3 font-semibold">{{ \App\Support\Currency::format((float) $order->grand_total, $order->currency_code) }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('account.orders.show', ['orderNumber' => $order->order_number]) }}" class="inline-flex h-9 items-center justify-center border border-slate-300 bg-white px-3 text-xs font-semibold uppercase tracking-wide text-slate-800 hover:border-slate-500 hover:bg-slate-50">
                                        {{ __('ui.account.orders.table.details') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('ui.account.orders.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $orders->links() }}</div>
        </div>
    </div>
@endsection
