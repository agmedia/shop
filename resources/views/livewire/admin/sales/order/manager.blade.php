@php
    $statusClasses = [
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
                <h1 class="text-xl font-semibold tracking-tight">{{ __('Orders') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Snapshot-based order flow with item, totals, status and timeline history.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('Items per page') }}: <span class="admin-chip">{{ $perPage }}</span></p>
            </div>

            <div class="flex w-[74rem] max-w-full items-end justify-end gap-3">
                <div class="grid w-full max-w-[72rem] items-end gap-3" style="grid-template-columns: minmax(30rem, 1fr) 13rem 10rem 10rem;">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.search') }}</label>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Order #, customer, email, phone...') }}"
                            class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status') }}</label>
                        <select wire:model.live="status" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border px-3 py-2 text-sm">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($statuses as $statusItem)
                                <option value="{{ $statusItem->id }}">{{ $statusItem->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Placed From') }}</label>
                        <input type="date" wire:model.live="dateFrom" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Placed To') }}</label>
                        <input type="date" wire:model.live="dateTo" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-panel admin-panel-soft p-5">
        <h2 class="admin-section-title">{{ __('admin.common.items') }}</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="admin-items-table min-w-full text-sm">
                <thead class="text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-center font-semibold">
                            <button type="button" wire:click="sort('id')" class="inline-flex items-center gap-1">
                                {{ __('ID') }} <span class="text-xs">{{ $sortBy === 'id' ? ($sortDir === 'asc' ? '^' : 'v') : '<>' }}</span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-left font-semibold">
                            <button type="button" wire:click="sort('order_number')" class="inline-flex items-center gap-1">
                                {{ __('Order') }} <span class="text-xs">{{ $sortBy === 'order_number' ? ($sortDir === 'asc' ? '^' : 'v') : '<>' }}</span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-left font-semibold">
                            <button type="button" wire:click="sort('customer_name')" class="inline-flex items-center gap-1">
                                {{ __('Customer') }} <span class="text-xs">{{ $sortBy === 'customer_name' ? ($sortDir === 'asc' ? '^' : 'v') : '<>' }}</span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-center font-semibold">
                            <button type="button" wire:click="sort('grand_total')" class="inline-flex items-center gap-1">
                                {{ __('Total') }} <span class="text-xs">{{ $sortBy === 'grand_total' ? ($sortDir === 'asc' ? '^' : 'v') : '<>' }}</span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Status') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">
                            <button type="button" wire:click="sort('placed_at')" class="inline-flex items-center gap-1">
                                {{ __('Placed') }} <span class="text-xs">{{ $sortBy === 'placed_at' ? ($sortDir === 'asc' ? '^' : 'v') : '<>' }}</span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-right font-semibold">{{ __('admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $statusColor = strtolower((string) ($row->status?->color ?? 'slate'));
                            $statusClass = $statusClasses[$statusColor] ?? $statusClasses['slate'];
                            $placedAt = $row->placed_at ?: $row->created_at;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-center font-mono text-xs text-slate-700">{{ $row->id }}</td>
                            <td class="px-3 py-2 text-slate-800">
                                <div class="font-medium">{{ $row->order_number }}</div>
                                <div class="text-xs text-slate-500">{{ $row->items_count }} {{ __('items') }}</div>
                            </td>
                            <td class="px-3 py-2 text-slate-700">
                                <div class="font-medium">{{ $row->customer_name }}</div>
                                <div class="text-xs text-slate-500">{{ $row->customer_email }}</div>
                            </td>
                            <td class="px-3 py-2 text-center text-slate-700">
                                <div class="font-semibold">{{ \App\Support\Currency::format((float) $row->grand_total, $row->currency_code) }}</div>
                                <div class="text-xs text-slate-500">{{ \App\Support\Currency::format((float) $row->subtotal, $row->currency_code) }} {{ __('subtotal') }}</div>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $row->status?->name ?? __('Unknown') }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center text-slate-600">{{ optional($placedAt)->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.orders.show', ['order' => $row->id]) }}" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                    {{ __('Show') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('No orders yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>
</div>
