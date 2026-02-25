<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('Luceed Sync Manager') }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ __('Full admin Luceed operation panel: catalog dictionaries, product sync, price/stock updates, and order status synchronization.') }}</p>
        <p class="mt-2 text-xs text-slate-500">{{ __('All actions run manually in admin and each run is logged with result details below.') }}</p>
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
        <div class="grid gap-4 xl:grid-cols-3">
            @foreach ($actionGroups as $group)
                <section class="admin-panel admin-items-panel p-5">
                    <h3 class="text-base font-semibold text-slate-900">{{ $group['title'] }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $group['description'] }}</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($group['actions'] as $action)
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $action['description'] }}</p>
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.12em] text-slate-400">{{ $action['key'] }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="runAction('{{ $action['key'] }}')"
                                        class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        @if($runningActionKey !== '') disabled @endif
                                    >
                                        @if($runningActionKey === $action['key'])
                                            {{ __('Running...') }}
                                        @else
                                            {{ __('Run') }}
                                        @endif
                                    </button>
                                </div>
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
                        <p class="mt-1 text-sm font-semibold {{ $lastRun->status === 'success' ? 'text-emerald-700' : ($lastRun->status === 'failed' ? 'text-rose-700' : 'text-slate-700') }}">{{ strtoupper($lastRun->status) }}</p>
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
                        <input type="text" wire:model="syncForm.luceed_sync_default_locale" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_default_locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Article Limit (0 = all)') }}</label>
                        <input type="number" min="0" wire:model="syncForm.luceed_sync_article_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_article_limit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Stock Warehouse Codes (CSV)') }}</label>
                    <input type="text" wire:model="syncForm.luceed_sync_stock_warehouses" placeholder="1001,1002" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used by quantity sync. Leave empty to auto-load all warehouses from Luceed.') }}</p>
                    @error('syncForm.luceed_sync_stock_warehouses') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('B2B Partner Type') }}</label>
                        <input type="text" wire:model="syncForm.luceed_sync_partner_type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_partner_type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('B2B Partner Value') }}</label>
                        <input type="text" wire:model="syncForm.luceed_sync_partner_value" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_partner_value') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('B2B Currency') }}</label>
                        <input type="text" wire:model="syncForm.luceed_sync_partner_currency" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_partner_currency') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Order Status Domain') }}</label>
                        <select wire:model="syncForm.luceed_sync_order_status_domain" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="NaloziProdaje">NaloziProdaje</option>
                            <option value="Narudzbe">Narudzbe</option>
                        </select>
                        @error('syncForm.luceed_sync_order_status_domain') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Order Lookback Days') }}</label>
                        <input type="number" min="1" wire:model="syncForm.luceed_sync_orders_lookback_days" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_orders_lookback_days') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Remote Status Codes Filter (CSV, optional)') }}</label>
                        <input type="text" wire:model="syncForm.luceed_sync_status_codes" placeholder="10,20,30" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_status_codes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status Duration Threshold (days)') }}</label>
                        <input type="number" min="1" wire:model="syncForm.luceed_sync_status_duration_days" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('syncForm.luceed_sync_status_duration_days') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">{{ __('Save Sync Settings') }}</button>
                </div>
            </form>
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
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $run->status === 'success' ? 'bg-emerald-100 text-emerald-700' : ($run->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ strtoupper($run->status) }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ $run->summary ?: '-' }}</p>
                        @if($run->error_message)
                            <div class="mt-2 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">{{ $run->error_message }}</div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">{{ __('No Luceed sync runs yet.') }}</div>
                @endforelse
            </div>
            <div class="mt-4">{{ $runs->links() }}</div>
        </div>
    @endif

    @if ($tab === 'help')
        <div class="admin-panel admin-items-panel p-6 space-y-6">
            <section>
                <h3 class="text-base font-semibold text-slate-900">{{ __('Content Block Building Style Help: Luceed Sync Edition') }}</h3>
                <p class="mt-2 text-sm text-slate-700">{{ __('Use this panel as an operations console. Connection tab defines transport/auth; Actions tab executes one concrete sync operation; History tab confirms what happened and when.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('How to think about actions') }}</h4>
                <p class="mt-2 text-sm text-slate-700">{{ __('Each action is intentionally narrow: one action imports categories, another updates quantities, another updates order statuses. This keeps runs deterministic and easier to rollback mentally.') }}</p>
                <p class="mt-2 text-sm text-slate-700">{{ __('Recommended cadence: dictionaries first (categories/manufacturers/payments), then products, then prices/quantities, then order statuses.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Key settings explained') }}</h4>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('Article Limit') }}:</strong> {{ __('safety cap for heavy product sync. 0 means full list. Use a small number while validating mapping.') }}</p>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('Stock Warehouse Codes') }}:</strong> {{ __('warehouse scope for quantity sync. Empty means auto-discover all warehouses.') }}</p>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('B2B Partner Type/Value') }}:</strong> {{ __('required by B2B price diff endpoint. If missing, B2B price sync intentionally fails with explicit error.') }}</p>
                <p class="mt-2 text-sm text-slate-700"><strong>{{ __('Order Lookback + Status Codes') }}:</strong> {{ __('controls how many remote rows are scanned when syncing local order statuses.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Safety and switch behavior') }}</h4>
                <p class="mt-2 text-sm text-slate-700">{{ __('Entire Luceed module is gated by Catalog Features switch `Use Luceed API`. If disabled, Luceed route/menu/actions are blocked.') }}</p>
                <p class="mt-2 text-sm text-slate-700">{{ __('Second gate is connector toggle in Luceed API connection panel. Both gates must be ON for actions to run.') }}</p>
            </section>

            <section>
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Operational workflow') }}</h4>
                <p class="mt-2 text-sm text-slate-700">1) {{ __('Save connection and test probe.') }}</p>
                <p class="mt-1 text-sm text-slate-700">2) {{ __('Set sync settings (locale/warehouses/partner).') }}</p>
                <p class="mt-1 text-sm text-slate-700">3) {{ __('Run catalog dictionary actions once or on demand.') }}</p>
                <p class="mt-1 text-sm text-slate-700">4) {{ __('Run product + price + quantity actions in order.') }}</p>
                <p class="mt-1 text-sm text-slate-700">5) {{ __('Run order status sync and inspect history log for counts/errors.') }}</p>
            </section>
        </div>
    @endif
</div>
