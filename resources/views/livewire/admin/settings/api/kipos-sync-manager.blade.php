<div class="space-y-6" @if($shouldPoll) wire:poll.2s="processActiveBrowserBatch" @endif>
    <div class="admin-panel admin-search-panel p-6">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('Kipos Sync Manager') }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ __('Granular Kipos console: import selected products by code, then run only content, prices, quantities, actions, or images when needed.') }}</p>
        <p class="mt-2 text-xs text-slate-500">{{ __('Every action runs manually in admin and writes a persistent run log with exact stats / error details.') }}</p>
        <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
            {{ __('Import Products, Update Prices, Update Quantities, and Update Order Statuses run immediately. Update Images runs from this admin screen in batches of 10 products with visible progress. Import Images and the longer catalog syncs still run in background on the dedicated `kipos` queue; keep a worker active for those actions, for example `php artisan queue:work --queue=kipos,default`.') }}
        </div>
    </div>

    <div class="admin-panel admin-search-panel p-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['actions' => __('Actions'), 'settings' => __('Settings'), 'history' => __('History'), 'help' => __('Help')] as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-slate-900 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-100' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($tab === 'actions')
        <div
            wire:loading.flex
            wire:target="runAction,processActiveBrowserBatch"
            class="items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
        >
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-300 border-t-blue-700"></span>
            <div>
                <p class="font-semibold">{{ __('Kipos sync request is running.') }}</p>
                <p class="text-xs">
                    @if($runningActionKey === 'update_images')
                        {{ __('Update Images processes 10 products per request. Keep this admin page open to continue progress.') }}
                    @elseif($runningActionKey !== '')
                        {{ __('Please wait while this Kipos action finishes.') }}
                    @else
                        {{ __('Refreshing active Kipos progress.') }}
                    @endif
                </p>
            </div>
        </div>

        @if ($activeRuns->isNotEmpty())
            <div class="admin-panel admin-items-panel p-5">
                <p class="admin-section-title">{{ __('Active Sync Progress') }}</p>
                <div class="mt-4 space-y-3">
                    @foreach ($activeRuns as $activeRun)
                        @php
                            $stats = (array) ($activeRun->stats ?? []);
                            $processed = (int) ($stats['processed_products'] ?? 0);
                            $total = (int) ($stats['total_products'] ?? 0);
                            $percent = (int) ($stats['progress_percent'] ?? ($total > 0 ? floor(($processed / max(1, $total)) * 100) : 0));
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $activeRun->action_label }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $activeRun->action_key }} · #{{ $activeRun->id }} · {{ strtoupper($activeRun->status) }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($total > 0)
                                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $processed }} / {{ $total }} · {{ $percent }}%</span>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="cancelRun({{ $activeRun->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="cancelRun"
                                        class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {{ __('Stop') }}
                                    </button>
                                </div>
                            </div>
                            @if($total > 0)
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, max(0, $percent)) }}%"></div>
                                </div>
                            @endif
                            <p class="mt-2 text-sm text-slate-700">{{ $activeRun->summary ?: '-' }}</p>
                            @if(!empty($stats))
                                <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-4">
                                    <div>{{ __('Updated') }}: <span class="font-semibold text-slate-900">{{ (int) ($stats['updated_products'] ?? 0) }}</span></div>
                                    <div>{{ __('Skipped') }}: <span class="font-semibold text-slate-900">{{ (int) ($stats['skipped_without_remote'] ?? 0) }}</span></div>
                                    <div>{{ __('Failures') }}: <span class="font-semibold text-slate-900">{{ (int) ($stats['download_failures'] ?? 0) }}</span></div>
                                    <div>{{ __('Batch') }}: <span class="font-semibold text-slate-900">{{ (int) ($stats['batch_size'] ?? 10) }}</span></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($lastRun)
            @php
                $lastStats = (array) ($lastRun->stats ?? []);
                $lastProcessed = (int) ($lastStats['processed_products'] ?? 0);
                $lastTotal = (int) ($lastStats['total_products'] ?? 0);
                $lastPercent = (int) ($lastStats['progress_percent'] ?? ($lastTotal > 0 ? floor(($lastProcessed / max(1, $lastTotal)) * 100) : 0));
            @endphp
            <div class="admin-panel admin-items-panel p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="admin-section-title">{{ __('Last Run Result') }}</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $lastRun->summary ?: '-' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $lastRun->status === 'success' ? 'bg-emerald-100 text-emerald-700' : ($lastRun->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700') }}">
                        {{ strtoupper($lastRun->status) }}
                    </span>
                </div>
                @if($lastTotal > 0)
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ min(100, max(0, $lastPercent)) }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ $lastProcessed }} / {{ $lastTotal }} · {{ $lastPercent }}%</p>
                @endif
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($actionGroups as $group)
                <section class="admin-panel admin-items-panel p-5">
                    <h3 class="text-base font-semibold text-slate-900">{{ $group['title'] }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $group['description'] }}</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($group['actions'] as $action)
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                @if ($action['key'] === 'import_products')
                                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $action['description'] }}</p>
                                            <input
                                                type="text"
                                                wire:model.defer="importProductCodes"
                                                placeholder="W5003, W5030, W5027"
                                                class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-100"
                                            />
                                            @error('importProductCodes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            <p class="mt-1 text-[11px] uppercase tracking-[0.12em] text-slate-400">{{ $action['key'] }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="runAction('import_products')"
                                            wire:loading.attr="disabled"
                                            wire:target="runAction"
                                            class="shrink-0 rounded-lg bg-cyan-700 px-4 py-2 text-xs font-semibold text-white hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-60"
                                            @if($runningActionKey !== '') disabled @endif
                                        >
                                            <span wire:loading.remove wire:target="runAction">{{ __('Import') }}</span>
                                            <span wire:loading wire:target="runAction">{{ __('Importing...') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $action['description'] }}</p>
                                            <p class="mt-1 text-[11px] uppercase tracking-[0.12em] text-slate-400">{{ $action['key'] }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="runAction('{{ $action['key'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="runAction"
                                            class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                            @if($runningActionKey !== '') disabled @endif
                                        >
                                            <span wire:loading.remove wire:target="runAction">{{ __('Run') }}</span>
                                            <span wire:loading wire:target="runAction">{{ __('Running...') }}</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if ($lastRun)
            <div class="admin-panel admin-items-panel p-6">
                <p class="admin-section-title">{{ __('Last Run Result') }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Action') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $lastRun->action_label }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Status') }}</p>
                        <p class="mt-1 text-sm font-semibold {{ $lastRun->status === 'success' ? 'text-emerald-700' : ($lastRun->status === 'failed' ? 'text-rose-700' : ($lastRun->status === 'queued' ? 'text-blue-700' : 'text-slate-700')) }}">{{ strtoupper($lastRun->status) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Started') }}</p>
                        <p class="mt-1 text-sm text-slate-700">{{ optional($lastRun->started_at)->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Duration') }}</p>
                        <p class="mt-1 text-sm text-slate-700">
                            @if($lastRun->started_at && $lastRun->finished_at)
                                {{ $lastRun->started_at->diffInSeconds($lastRun->finished_at) }}s
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-700">{{ $lastRun->summary ?: '-' }}</p>
                @if (!empty($lastRun->stats))
                    <pre class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($lastRun->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
                @if ($lastRun->error_message)
                    <div class="mt-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900">{{ $lastRun->error_message }}</div>
                @endif
            </div>
        @endif
    @endif

    @if ($tab === 'settings')
        <div class="admin-panel admin-form-panel p-6">
            <form wire:submit="saveSyncSettings" class="admin-form space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Default Locale') }}</label>
                        <input type="text" wire:model="syncForm.kipos_sync_default_locale" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.kipos_sync_default_locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Import Category ID') }}</label>
                        <input type="number" min="1" wire:model="syncForm.kipos_sync_import_category_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Optional fixed category used when imported Kipos products should all land in one admin category, like the old OpenCart flow.') }}</p>
                        @error('syncForm.kipos_sync_import_category_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Size Option ID') }}</label>
                        <input type="number" min="1" wire:model="syncForm.kipos_sync_size_option_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Required when Kipos products use sizes / variants. Also enable `Use Options` in Catalog Features.') }}</p>
                        @error('syncForm.kipos_sync_size_option_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Price Field') }}</label>
                        <input type="text" wire:model="syncForm.kipos_sync_price_field" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Default old setup used `CIJENA_MPC`.') }}</p>
                        @error('syncForm.kipos_sync_price_field') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Action Price Field') }}</label>
                        <input type="text" wire:model="syncForm.kipos_sync_action_price_field" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.kipos_sync_action_price_field') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Warehouse IDs (CSV)') }}</label>
                        <input type="text" wire:model="syncForm.kipos_sync_stock_warehouse_ids" placeholder="100,200" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Leave empty to sum all returned `IDSKL` rows from `getZalihaK`.') }}</p>
                        @error('syncForm.kipos_sync_stock_warehouse_ids') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Quantity Overrides') }}</label>
                    <textarea rows="6" wire:model="syncForm.kipos_sync_quantity_overrides" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder='{"100":["W5004","W5005"]}'></textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Supports JSON like the old config or line format `100: W5004,W5005`. Matches both product group code (`IDODJEL`) and item code (`IDROBA`).') }}</p>
                    @error('syncForm.kipos_sync_quantity_overrides') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Color Overrides') }}</label>
                    <textarea rows="4" wire:model="syncForm.kipos_sync_color_overrides" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder='{"W7042":"tamno-plava"}'></textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Optional JSON or one `PRODUCT: color-code` rule per line. Used only when a product has no existing color value; otherwise the nightly sync can infer a matching color from the product name.') }}</p>
                    @error('syncForm.kipos_sync_color_overrides') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ERP Order Send Settings') }}</p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Order Prefix') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_prefix" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_prefix') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Valuta') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_valuta" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_valuta') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Fallback Customer CMS ID') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_customer_cms_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_customer_cms_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Shipping Item Code') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_shipping_item_code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Optional code override. Price comes from the matching Shipping Method; empty code uses Balidoo mapping: 10.00 = US00000001, otherwise US00000203.') }}</p>
                            @error('syncForm.kipos_order_shipping_item_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Payment Fee Item Code') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_payment_fee_item_code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_payment_fee_item_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Private Austria Company ID') }}</label>
                            <input type="number" min="1" wire:model="syncForm.kipos_order_private_at_company_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_private_at_company_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Private Germany Company ID') }}</label>
                            <input type="number" min="1" wire:model="syncForm.kipos_order_private_de_company_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_private_de_company_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('ERP Order Status Sync Settings') }}</p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status Endpoint') }}</label>
                            <input type="text" wire:model="syncForm.kipos_order_status_endpoint" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Supports placeholders like `{from}`, `{from_dmy}`, `{status_codes}`, and `{status_codes_bracketed}`.') }}</p>
                            @error('syncForm.kipos_order_status_endpoint') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Lookback Days') }}</label>
                            <input type="number" min="1" max="365" wire:model="syncForm.kipos_order_status_lookback_days" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            @error('syncForm.kipos_order_status_lookback_days') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Remote Status Codes') }}</label>
                        <input type="text" wire:model="syncForm.kipos_order_status_codes" placeholder="10,20,30" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Optional CSV list. When filled, it is sent as `statusi` unless the endpoint already contains a status placeholder.') }}</p>
                        @error('syncForm.kipos_order_status_codes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status Map') }}</label>
                        <textarea rows="6" wire:model="syncForm.kipos_order_status_map" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder='{"placeno":"paid","poslano":"sent","otkazano":"cancelled"}'></textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Map Kipos status code/name to local order status code. JSON or line format `remote: local` is supported.') }}</p>
                        @error('syncForm.kipos_order_status_map') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">{{ __('Save Sync Settings') }}</button>
                </div>
            </form>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Kipos Endpoint Map') }}</p>
                <pre class="mt-2 overflow-x-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-700">{{ json_encode($endpointMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif

    @if ($tab === 'history')
        <div class="admin-panel admin-items-panel p-6">
            <p class="admin-section-title">{{ __('Execution History') }}</p>
            <div class="mt-4 space-y-3">
                @forelse($runs as $run)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $run->action_label }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $run->action_key }} · #{{ $run->id }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ optional($run->started_at)->format('Y-m-d H:i:s') }}
                                    @if($run->finished_at)
                                        · {{ __('finished') }} {{ $run->finished_at->format('Y-m-d H:i:s') }}
                                    @endif
                                    @if($run->initiator)
                                        · {{ __('by') }} {{ $run->initiator->name }}
                                    @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $run->status === 'success' ? 'bg-emerald-100 text-emerald-700' : ($run->status === 'failed' ? 'bg-rose-100 text-rose-700' : ($run->status === 'queued' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700')) }}">
                                {{ strtoupper($run->status) }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ $run->summary ?: '-' }}</p>
                        @if($run->error_message)
                            <div class="mt-2 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{{ $run->error_message }}</div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">{{ __('No Kipos sync runs yet.') }}</div>
                @endforelse
            </div>
            <div class="mt-4">{{ $runs->links() }}</div>
        </div>
    @endif

    @if ($tab === 'help')
        <div class="admin-panel admin-items-panel p-6 space-y-6">
            <section>
                <h3 class="text-base font-semibold text-slate-900">{{ __('How this Kipos module is intended to be used') }}</h3>
                <p class="mt-2 text-sm text-slate-700">{{ __('This is intentionally not an “update everything blindly” button. The goal is safe manual control: import once, then run just content, just prices, just quantities, just actions, or just images depending on what changed in ERP.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Product mapping') }}</h4>
                <p class="mt-2 text-sm text-slate-700">{{ __('Kipos `IDODJEL` becomes the local product code. Kipos `IDROBA` becomes the local SKU / option row SKU. Base price is the minimum price inside the Kipos department group, while size option rows carry the variant-level price difference.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Granular updates') }}</h4>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('Update Content') }}:</strong> {{ __('name, description, active state, and size-row structure only.') }}</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('Update Prices') }}:</strong> {{ __('base price + variant price override only.') }}</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('Update Quantities') }}:</strong> {{ __('stock only, with optional warehouse filter and quantity overrides.') }}</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('Update Actions') }}:</strong> {{ __('local catalog actions from Kipos action price field.') }}</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('Update Order Statuses') }}:</strong> {{ __('pulls Kipos order status rows, matches local webshop orders by CMS/order number, and writes status history.') }}</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ __('Import / Update Images') }}:</strong> {{ __('separate media workflow so image refreshes do not force product data sync.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Admin order send') }}</h4>
                <p class="mt-2 text-sm text-slate-700">{{ __('Kipos order send is admin-only. Open any order detail, generate Test Payload first, then use Send to ERP when the order / invoice should actually be pushed. Preview and response snapshots are persisted in order payload for traceability.') }}</p>
            </section>
        </div>
    @endif
</div>
