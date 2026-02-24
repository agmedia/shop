@php
    $rangeOptions = [
        '1' => __('Today'),
        '7' => __('Last 7 Days'),
        '30' => __('Last 30 Days'),
    ];

    $statusColorClasses = [
        'blue' => 'bg-blue-100 text-blue-800',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'green' => 'bg-emerald-100 text-emerald-800',
        'rose' => 'bg-rose-100 text-rose-800',
        'red' => 'bg-rose-100 text-rose-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'yellow' => 'bg-amber-100 text-amber-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'purple' => 'bg-violet-100 text-violet-800',
        'slate' => 'bg-slate-200 text-slate-700',
        'gray' => 'bg-slate-200 text-slate-700',
        'cyan' => 'bg-cyan-100 text-cyan-800',
    ];
@endphp

<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ __('Performance Overview') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Operational dashboard for orders, users, and current feature availability.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('Window') }}: <span class="admin-chip">{{ $start->format('Y-m-d') }} - {{ $end->format('Y-m-d') }}</span></p>
            </div>
            <div class="w-56">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Range') }}</label>
                <select wire:model.live="rangeDays" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border px-3 py-2 text-sm">
                    @foreach ($rangeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="grid gap-4" style="grid-template-columns: repeat(20, minmax(0, 1fr));">
        @foreach ($kpis as $kpi)
            @php
                $delta = $kpi['delta'];
                $direction = $delta['direction'];
                $tone = $direction === 'up' ? 'text-emerald-700' : ($direction === 'down' ? 'text-rose-700' : 'text-slate-600');
            @endphp
            <div class="admin-panel admin-panel-soft p-4" style="grid-column: span 4;">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $kpi['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ $kpi['value'] }} @if (isset($kpi['suffix']))<span class="text-sm text-slate-600">{{ $kpi['suffix'] }}</span>@endif
                </p>
                <p class="mt-2 text-xs {{ $tone }}">
                    @if ($direction === 'up') + @endif{{ number_format($delta['delta'], 2) }}
                    @if ($delta['percent'] !== null)
                        ({{ $delta['percent'] >= 0 ? '+' : '' }}{{ number_format($delta['percent'], 1) }}%)
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <div class="admin-panel admin-panel-soft p-5">
            <div class="flex items-center justify-between gap-2">
                <h2 class="admin-section-title">{{ __('Order Pipeline') }}</h2>
                <a href="{{ route('admin.orders') }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Open Orders') }}</a>
            </div>

            <div class="mt-4 grid gap-2" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
                @forelse ($pipeline as $status)
                    @php
                        $colorKey = strtolower((string) ($status['color'] ?? 'slate'));
                        $statusClass = $statusColorClasses[$colorKey] ?? $statusColorClasses['slate'];
                    @endphp
                    <a href="{{ $status['url'] }}" class="rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50" style="grid-column: span 3;">
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $status['name'] }}</span>
                            <span class="text-lg font-semibold text-slate-900">{{ $status['count'] }}</span>
                        </div>
                        <p class="mt-2 text-[11px] uppercase tracking-[0.1em] text-slate-500">{{ $status['code'] }}</p>
                    </a>
                @empty
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500">{{ __('No statuses configured.') }}</div>
                @endforelse
            </div>
    </div>

    <div class="grid gap-4" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
        <div class="admin-panel admin-panel-soft p-5" style="grid-column: span 4;">
            <h2 class="admin-section-title">{{ __('Revenue vs Orders') }}</h2>
            <div class="mt-4" style="height: 16rem;">
                <canvas
                    data-dashboard-chart
                    data-chart-key="sales_trend"
                    data-chart-payload='@json($dashboardCharts["sales_trend"])'
                ></canvas>
            </div>
        </div>

        <div class="admin-panel admin-panel-soft p-5" style="grid-column: span 4;">
            <h2 class="admin-section-title">{{ __('New Users Trend') }}</h2>
            <div class="mt-4" style="height: 16rem;">
                <canvas
                    data-dashboard-chart
                    data-chart-key="new_users_trend"
                    data-chart-payload='@json($dashboardCharts["new_users_trend"])'
                ></canvas>
            </div>
        </div>

        <div class="admin-panel admin-panel-soft p-5" style="grid-column: span 4;">
            <h2 class="admin-section-title">{{ __('Pipeline Share') }}</h2>
            <div class="mt-4" style="height: 16rem;">
                <canvas
                    data-dashboard-chart
                    data-chart-key="pipeline_share"
                    data-chart-payload='@json($dashboardCharts["pipeline_share"])'
                ></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-6" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
        <div class="admin-panel admin-panel-soft p-5">
            <h2 class="admin-section-title">{{ __('Sales Trend (:days Days)', ['days' => min($days, 30)]) }}</h2>
            <div class="mt-4 space-y-2">
                @foreach ($trendRows as $row)
                    <div class="grid items-center gap-3" style="grid-template-columns: 7rem minmax(0, 1fr) 9rem 5rem;">
                        <span class="text-xs text-slate-600">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M d') }}</span>
                        <div class="h-2 rounded-full bg-slate-200">
                            <div class="h-2 rounded-full bg-cyan-600" style="width: {{ max(2, (int) $row['bar_width']) }}%;"></div>
                        </div>
                        <span class="text-xs text-slate-700 text-right">{{ \App\Support\Currency::format((float) $row['revenue'], $storeCurrencyCode ?? null) }}</span>
                        <span class="text-xs text-slate-500 text-right">{{ $row['orders'] }} {{ __('ord') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-panel admin-panel-soft p-5">
            <h2 class="admin-section-title">{{ __('Feature Flags') }}</h2>
            <div class="mt-4 grid gap-2" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                @foreach ($featureFlags as $flag => $enabled)
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <span class="text-slate-700">{{ $flag }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                            {{ $enabled ? __('On') : __('Off') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
        <div class="admin-panel admin-panel-soft p-5">
            <h2 class="admin-section-title">{{ __('Recent Orders') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="admin-items-table min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left">{{ __('Order') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Customer') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="px-2 py-2">
                                    <a href="{{ route('admin.orders.show', ['order' => $order->id]) }}" class="text-cyan-700 hover:text-cyan-900">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-2 py-2">{{ $order->customer_name }}</td>
                                <td class="px-2 py-2 text-right">{{ \App\Support\Currency::format((float) $order->grand_total, $order->currency_code) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-2 py-4 text-center text-slate-500">{{ __('No orders yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-panel admin-panel-soft p-5">
            <h2 class="admin-section-title">{{ __('Recent Admin Activity') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="admin-items-table min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left">{{ __('Time') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Event') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Causer') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentAdminActivity as $activity)
                            <tr>
                                <td class="px-2 py-2">{{ $activity->created_at?->format('m-d H:i') ?? '-' }}</td>
                                <td class="px-2 py-2">{{ $activity->event ?: $activity->description }}</td>
                                <td class="px-2 py-2">{{ $activity->causer?->name ?: __('System') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-2 py-4 text-center text-slate-500">{{ __('No admin activity.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($loyaltyEnabled)
        <div class="admin-panel admin-panel-soft p-5">
            <h2 class="admin-section-title">{{ __('Recent Loyalty Activity') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="admin-items-table min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left">{{ __('Time') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Event') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Actor') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentLoyaltyActivity as $activity)
                            <tr>
                                <td class="px-2 py-2">{{ $activity->created_at?->format('m-d H:i') ?? '-' }}</td>
                                <td class="px-2 py-2">{{ $activity->event ?: $activity->description }}</td>
                                <td class="px-2 py-2">{{ $activity->causer?->name ?: __('System') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-2 py-4 text-center text-slate-500">{{ __('No loyalty activity.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="admin-panel admin-panel-soft p-5">
        <h2 class="admin-section-title">{{ __('Catalog & Content Snapshot') }}</h2>
        <div class="mt-4 grid gap-2" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
            @foreach ($catalogSnapshot as $item)
                <a href="{{ $item['url'] }}" class="rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50" style="grid-column: span 2;">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">{{ $item['label'] }}</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ number_format((int) $item['value']) }}</p>
                </a>
            @endforeach
        </div>
    </div>
</div>
