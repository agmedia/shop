<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Catalog / Products') }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $isEdit ? __('Edit Product') : __('Create Product') }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('Core product fields, translation, and category assignments.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="admin-chip">{{ __('Locale:') }} {{ $form['locale'] }}</span>
                <button type="button" wire:click="backToList" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Back to List') }}</button>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="admin-panel admin-form-panel p-3 sm:p-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setTab('content')" class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] {{ $activeTab === 'content' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                    {{ __('Sadržaj') }}
                </button>
                <button type="button" wire:click="setTab('seo')" class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] {{ $activeTab === 'seo' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                    {{ __('SEO') }}
                </button>
                <button type="button" wire:click="setTab('media')" class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] {{ $activeTab === 'media' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                    {{ __('Media') }}
                </button>
                <button type="button" wire:click="setTab('catalog')" class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] {{ $activeTab === 'catalog' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                    {{ __('Katalog') }}
                </button>
            </div>
        </div>

        @if ($activeTab === 'content')
        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Core Data') }}</p>
            <div class="mt-4 grid gap-3" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
                <div style="grid-column: span 3;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SKU') }}</label>
                    <input type="text" wire:model="form.sku" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
                    @error('form.sku') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div style="grid-column: span 3;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Code') }}</label>
                    <input type="text" wire:model="form.code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
                    @error('form.code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div style="grid-column: span 2;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.locale') }}</label>
                    <select wire:model.live="form.locale" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm lowercase">
                          @foreach ($adminLocaleOptions as $localeOption)
                                <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                            @endforeach
                    </select>
                    @error('form.locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div style="grid-column: span 2;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Base Price') }}</label>
                    <input type="number" min="0" step="0.01" wire:model="form.base_price" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    @error('form.base_price') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div style="grid-column: span 2;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Stock Qty') }}</label>
                    <input type="number" min="0" wire:model="form.stock_qty" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    @error('form.stock_qty') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-3 grid gap-3" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
                <div style="grid-column: span 4;">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Tax Class') }}</label>
                    <select wire:model="form.tax_rate_id" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($this->taxRateOptions as $taxRate)
                            <option value="{{ $taxRate->id }}">{{ $taxRate->name }} ({{ rtrim(rtrim(number_format((float) $taxRate->rate, 2), '0'), '.') }}{{ $taxRate->rate_type === 'percent' ? '%' : '' }})</option>
                        @endforeach
                    </select>
                    @error('form.tax_rate_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($useManufacturers)
                <div class="mt-3 grid gap-3" style="grid-template-columns: repeat(12, minmax(0, 1fr));">
                    <div style="grid-column: span 5;">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Manufacturer') }}</label>
                            <a href="{{ route('admin.manufacturers', ['locale' => $form['locale']]) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">{{ __('Manage') }}</a>
                        </div>
                        <select wire:model="form.manufacturer_id" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">{{ __('No manufacturer') }}</option>
                            @foreach ($this->manufacturerOptions as $manufacturer)
                                @php
                                    $tr = $manufacturer->translations->first();
                                    $label = $tr?->name ?? ($manufacturer->code ?: __('Manufacturer #:id', ['id' => $manufacturer->id]));
                                @endphp
                                <option value="{{ $manufacturer->id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('form.manufacturer_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="$toggle('form.is_active')"
                    class="admin-switch"
                    data-state="{{ $form['is_active'] ? 'on' : 'off' }}"
                    role="switch"
                    aria-checked="{{ $form['is_active'] ? 'true' : 'false' }}"
                    aria-label="{{ __('Toggle product active state') }}"
                >
                    <span class="admin-switch-track">
                        <span class="admin-switch-thumb"></span>
                    </span>
                    <span class="admin-switch-label">{{ $form['is_active'] ? __('admin.common.active') : __('admin.common.inactive') }}</span>
                </button>
            </div>
        </div>
        @endif

        @if ($activeTab === 'content')
        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Content') }}</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Name') }}</label>
                    <input type="text" wire:model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    @error('form.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Slug') }}</label>
                        <button type="button" wire:click="generateSlug" class="text-xs font-semibold text-slate-600 hover:text-slate-900">{{ __('Generate') }}</button>
                    </div>
                    <input type="text" wire:model="form.slug" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm lowercase" />
                    @error('form.slug') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Excerpt') }}</label>
                <textarea rows="3" wire:model="form.excerpt" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="mt-3">
                <label for="product-description-html" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Description') }}</label>
                <textarea id="product-description-html" rows="8" wire:model.live.debounce.300ms="form.description" data-quill-editor class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
        </div>
        @endif

        @if ($activeTab === 'seo')
        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('SEO & Payload') }}</p>
            <div class="mt-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Meta Title') }}</label>
                <input type="text" wire:model="form.meta_title" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Meta Description') }}</label>
                <textarea rows="3" wire:model="form.meta_description" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>
            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Product Payload JSON') }}</label>
                <textarea rows="6" wire:model="form.payload_text" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
                @error('form.payload_text') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Translation Payload JSON') }}</label>
                <textarea rows="6" wire:model="form.translation_payload_text" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
                @error('form.translation_payload_text') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
        @endif

        @if ($activeTab === 'media')
        <livewire:admin.media.manager
            :model-class="\App\Models\Catalog\Product\Product::class"
            :model-id="$productId"
            :locale="$form['locale']"
            :wire:key="'product-media-manager-'.($productId ?? 'new').'-'.$form['locale']"
        />
        @endif

        @if ($activeTab === 'catalog')
        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Categories & Attributes') }}</p>
            <div class="mt-4 rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Categories (order defines primary)') }}</p>
                <input type="text" wire:model.live.debounce.250ms="categorySearch" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('Pretraga kategorija...') }}">
                    <div class="mt-3 max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white p-2">
                        @forelse ($this->filteredCategoryOptions as $category)
                            <button type="button" wire:click="addCategory({{ $category['id'] }})" class="mb-1 flex w-full items-center justify-between rounded-lg border border-slate-200 px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50">
                                <span>{{ $category['label'] }}</span>
                                <span class="text-xs font-semibold text-slate-500">+</span>
                            </button>
                        @empty
                        <p class="px-1 py-1 text-xs text-slate-500">{{ __('Nema rezultata') }}</p>
                    @endforelse
                </div>
                <div class="mt-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Odabrano') }}</p>
                    <div class="space-y-1">
                        @forelse ($this->selectedCategoryRows as $row)
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm">
                                <span>{{ $row['label'] }}</span>
                                <button type="button" wire:click="removeCategory({{ $row['id'] }})" class="text-xs font-semibold text-rose-600 hover:text-rose-700">{{ __('Makni') }}</button>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">{{ __('Nema odabranih kategorija.') }}</p>
                        @endforelse
                    </div>
                </div>
                @error('form.category_ids.*') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            @if ($useAttributes)
                <div class="mt-5">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Attributes') }}</label>
                        <a href="{{ route('admin.attributes', ['locale' => $form['locale']]) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">{{ __('Manage') }}</a>
                    </div>
                    <div class="mb-3 grid gap-2 lg:grid-cols-[minmax(12rem,20rem)_auto]">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Visible Group') }}</label>
                            <select wire:model.live="attributeGroupView" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option value="all">{{ __('All groups') }}</option>
                                @foreach ($this->attributeGroupOptions as $groupOption)
                                    <option value="{{ $groupOption['group_code'] }}">
                                        {{ $groupOption['group_name'] }} ({{ $groupOption['item_count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                type="button"
                                wire:click="$toggle('attributeShowAssignedOnly')"
                                class="rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] {{ $attributeShowAssignedOnly ? 'border-cyan-300 bg-cyan-50 text-cyan-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}"
                            >
                                {{ $attributeShowAssignedOnly ? __('Assigned Only: On') : __('Assigned Only: Off') }}
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse ($this->visibleAttributeGroups as $group)
                            @php
                                $groupCode = (string) $group['group_code'];
                                $groupType = (string) $group['type'];
                            @endphp
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800">{{ $group['group_name'] }}</p>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">{{ $groupType === 'multi' ? __('Multi') : __('Single') }}</span>
                                </div>

                                @if ($groupType === 'multi')
                                    <select wire:model="attributeSelections.{{ $groupCode }}" multiple size="5" class="admin-multiselect w-full rounded-xl border border-slate-300 text-sm">
                                        @foreach ($group['items'] as $item)
                                            <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <select wire:model="attributeSelections.{{ $groupCode }}" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">{{ __('No value') }}</option>
                                        @foreach ($group['items'] as $item)
                                            <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                @error('attributeSelections.'.$groupCode) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600 lg:col-span-2">
                                {{ __('No attribute groups match current filter.') }}
                            </div>
                        @endforelse
                    </div>
                    @error('form.attribute_ids') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('form.attribute_ids.*') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($useOptions)
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Product Option Values') }}</p>
                            <p class="text-xs text-slate-600">{{ __('Assign option groups and manage per-value SKU/price/stock combinations.') }}</p>
                        </div>
                        @if ($isEdit && $productId)
                            <a href="{{ route('admin.products.options', ['product' => $productId, 'locale' => $form['locale']]) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                {{ __('Manage Option Values') }}
                            </a>
                        @else
                            <span class="text-xs text-slate-500">{{ __('Save product first') }}</span>
                        @endif
                    </div>
                </div>
            @endif

        </div>
        @endif

        <div class="admin-form-actions mt-5 flex items-center gap-2 pt-2">
            <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                {{ $isEdit ? __('Update Product') : __('Create Product') }}
            </button>
            <button type="button" wire:click="backToList" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                {{ __('Cancel') }}
            </button>
        </div>
    </form>
</div>
