<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex items-end justify-between gap-6">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ __('Products') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Product foundation with feature-flag-aware query strategy.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('Items per page') }}: <span class="admin-chip">{{ $perPage }}</span></p>
            </div>
            <div class="grid items-end gap-3" style="grid-template-columns: 24rem 8rem;">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.search') }}</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Code, SKU, name or slug...') }}"
                        class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.locale') }}</label>
                    <select wire:model.live="locale" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm lowercase">
                        @foreach ($adminLocaleOptions as $localeOption)
                            <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @php
            $secondRowColumns = ($features['catalog_use_manufacturers'] ?? false)
                ? '7rem 7rem minmax(16rem, 1fr) minmax(16rem, 1fr) 9rem 8rem 8rem'
                : '7rem 7rem minmax(24rem, 1fr) 9rem 8rem 8rem';
        @endphp
        <div class="mt-4 grid items-end gap-3" style="grid-template-columns: {{ $secondRowColumns }};">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.state') }}</label>
                <select wire:model.live="stateFilter" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                    @foreach ($stateOptions as $stateKey => $stateLabel)
                        <option value="{{ $stateKey }}">{{ $stateLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Stock') }}</label>
                <select wire:model.live="stockFilter" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                    @foreach ($stockOptions as $stockKey => $stockLabel)
                        <option value="{{ $stockKey }}">{{ $stockLabel }}</option>
                    @endforeach
                </select>
            </div>
            @if ($features['catalog_use_manufacturers'])
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Manufacturer') }}</label>
                    <select wire:model.live="manufacturerFilter" data-tom-select class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                        <option value="all">{{ __('All') }}</option>
                        @foreach ($this->manufacturerOptions as $manufacturer)
                            @php $manufacturerTr = $manufacturer->translations->first(); @endphp
                            <option value="{{ $manufacturer->id }}">{{ $manufacturerTr?->name ?? $manufacturer->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Category') }}</label>
                    <select wire:model.live="categoryFilter" data-tom-select class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                        <option value="all">{{ __('All') }}</option>
                        @foreach ($this->categoryOptions as $category)
                            <option value="{{ $category['id'] }}">{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.sort') }}</label>
                <select wire:model.live="sortBy" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                    @foreach ($sortOptions as $sortKey => $sortLabel)
                        <option value="{{ $sortKey }}">{{ $sortLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="button" wire:click="clearFilters" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                    {{ __('Reset') }}
                </button>
            </div>
            <div>
                <a href="{{ route('admin.products.create', ['locale' => $locale]) }}" class="block w-full rounded-xl bg-cyan-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-cyan-800">
                    {{ __('admin.common.create') }}
                </a>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="admin-chip">{{ __('Attributes') }}: {{ $features['catalog_use_attributes'] ? __('ON') : __('OFF') }}</span>
            <span class="admin-chip">{{ __('Options') }}: {{ $features['catalog_use_options'] ? __('ON') : __('OFF') }}</span>
            <span class="admin-chip">{{ __('Manufacturers') }}: {{ $features['catalog_use_manufacturers'] ? __('ON') : __('OFF') }}</span>
            <span class="admin-chip">{{ __('Actions') }}: {{ ($features['catalog_use_actions'] ?? false) ? __('ON') : __('OFF') }}</span>
            <a href="{{ route('admin.settings.system.catalog-features') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Catalog Features') }}</a>
            @if ($features['catalog_use_attributes'])
                <a href="{{ route('admin.attributes', ['locale' => $locale]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Attributes') }}</a>
            @endif
            @if ($features['catalog_use_options'])
                <a href="{{ route('admin.options', ['locale' => $locale]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Options') }}</a>
            @endif
            @if ($features['catalog_use_manufacturers'])
                <a href="{{ route('admin.manufacturers', ['locale' => $locale]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Manufacturers') }}</a>
            @endif
            @if ($features['catalog_use_actions'] ?? false)
                <a href="{{ route('admin.actions', ['locale' => $locale]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.common.actions') }}</a>
            @endif
        </div>
    </div>

    <div class="admin-panel admin-panel-soft p-5">
        <h2 class="admin-section-title">{{ __('admin.common.items') }}</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="admin-items-table min-w-full text-sm">
                <thead class="text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Image') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Product') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Slug') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Price') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Stock') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Categories') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('admin.common.state') }}</th>
                        <th class="px-3 py-2 text-right font-semibold">{{ __('admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $tr = $row->translations->first();
                            $mainMedia = $row->getFirstMedia('product_main') ?: $row->getFirstMedia('product_gallery');
                            $mainThumb = $mainMedia
                                ? ($mainMedia->hasGeneratedConversion('thumb_100x100') ? $mainMedia->getUrl('thumb_100x100') : $mainMedia->getUrl())
                                : null;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-center align-top">
                                @if ($mainThumb)
                                    <img
                                        src="{{ $mainThumb }}"
                                        alt=""
                                        title="{{ $tr?->name ?? $row->code }}"
                                        class="mx-auto h-12 w-12 rounded-lg border border-slate-200 object-cover"
                                        loading="lazy"
                                    />
                                @else
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">
                                        n/a
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-800">
                                <div class="font-medium">{{ $tr?->name ?? __('(missing name)') }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $row->code }} @if($row->sku) / {{ $row->sku }} @endif
                                </div>
                                @if (($features['catalog_use_manufacturers'] ?? false) && $row->manufacturer)
                                    @php $manufacturerTr = $row->manufacturer->translations->first(); @endphp
                                    <div class="text-xs text-slate-500">
                                        {{ __('Manufacturer') }}: {{ $manufacturerTr?->name ?? $row->manufacturer->code }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $tr?->slug ?? '-' }}</td>
                            <td class="px-3 py-2 text-center text-slate-700">{{ \App\Support\Currency::format((float) $row->base_price, $row->currency_code ?? null) }}</td>
                            <td class="px-3 py-2 text-center text-slate-700">{{ $row->stock_qty }}</td>
                            <td class="px-3 py-2 text-center text-slate-700">
                                {{ $row->categories_count }}
                                @if ($features['catalog_use_attributes'])
                                    <div class="text-xs text-slate-500">{{ __('Attr') }}: {{ $row->attributes_count }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $row->is_active ? __('admin.common.active') : __('admin.common.inactive') }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.products.edit', ['product' => $row->id, 'locale' => $locale]) }}" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                    {{ __('admin.common.edit') }}
                                </a>
                                @if ($features['catalog_use_options'])
                                    <a href="{{ route('admin.products.options', ['product' => $row->id, 'locale' => $locale]) }}" class="ml-1 rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        {{ __('Option Values') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('No products yet. Seed or create products next.') }}</td>
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
