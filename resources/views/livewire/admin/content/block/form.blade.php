<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Content / Blocks v2') }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $this->isEdit ? __('Edit Block') : __('Create Block') }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('Simple builder: choose type, set slot, pick items, edit Blade template, publish.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="admin-chip">{{ __('Locale:') }} {{ $form['locale'] }}</span>
                <button type="button" wire:click="backToList" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Back to List') }}</button>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Core') }}</p>

            <div class="mt-4 grid gap-3 md:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Code') }}</label>
                    <input type="text" wire:model="form.code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
                    @error('form.code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Name') }}</label>
                    <input type="text" wire:model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    @error('form.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Type') }}</label>
                    <select wire:model.live="form.type" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($types as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}" @selected(($form['type'] ?? '') === $typeKey)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    @error('form.type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.locale') }}</label>
                    <select wire:model.live="form.locale" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm lowercase">
                        @foreach ($adminLocaleOptions as $localeOption)
                            <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                        @endforeach
                    </select>
                    @error('form.locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="$toggle('form.is_active')"
                        class="admin-switch"
                        data-state="{{ $form['is_active'] ? 'on' : 'off' }}"
                        role="switch"
                        aria-checked="{{ $form['is_active'] ? 'true' : 'false' }}"
                        aria-label="{{ __('Toggle block active state') }}"
                    >
                        <span class="admin-switch-track"><span class="admin-switch-thumb"></span></span>
                        <span class="admin-switch-label">{{ $form['is_active'] ? __('admin.common.active') : __('admin.common.inactive') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Slot (Placement)') }}</p>

            <div class="mt-4 grid gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Placement') }}</label>
                    <select wire:model="form.slot_placement" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($placements as $placementKey => $placementLabel)
                            <option value="{{ $placementKey }}" @selected(($form['slot_placement'] ?? '') === $placementKey)>{{ $placementLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Surface') }}</label>
                    <select wire:model="form.slot_frontend_variant" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($frontendVariants as $frontendVariantKey => $frontendVariantLabel)
                            <option value="{{ $frontendVariantKey }}" @selected(($form['slot_frontend_variant'] ?? 'all') === $frontendVariantKey)>{{ $frontendVariantLabel }}</option>
                        @endforeach
                    </select>
                    @error('form.slot_frontend_variant') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Target Type') }}</label>
                    <select wire:model="form.slot_target_type" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($targetTypes as $targetTypeKey => $targetTypeLabel)
                            <option value="{{ $targetTypeKey }}" @selected((string) ($form['slot_target_type'] ?? '') === (string) $targetTypeKey)>{{ $targetTypeLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Target Ref') }}</label>
                    <input type="text" wire:model="form.slot_target_ref" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('slug or id') }}" />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Sort Order') }}</label>
                    <input type="number" min="0" wire:model="form.slot_sort_order" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Starts At') }}</label>
                    <input type="datetime-local" wire:model="form.slot_starts_at" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Ends At') }}</label>
                    <input type="datetime-local" wire:model="form.slot_ends_at" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="$toggle('form.slot_is_active')"
                        class="admin-switch"
                        data-state="{{ $form['slot_is_active'] ? 'on' : 'off' }}"
                        role="switch"
                        aria-checked="{{ $form['slot_is_active'] ? 'true' : 'false' }}"
                        aria-label="{{ __('Toggle slot active state') }}"
                    >
                        <span class="admin-switch-track"><span class="admin-switch-thumb"></span></span>
                        <span class="admin-switch-label">{{ $form['slot_is_active'] ? __('Slot Active') : __('Slot Inactive') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="admin-panel admin-form-panel p-6">
                <p class="admin-section-title">{{ __('Content') }}</p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Title') }}</label>
                        <input type="text" wire:model="form.title" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Subtitle') }}</label>
                        <input type="text" wire:model="form.subtitle" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('CTA Label') }}</label>
                        <input type="text" wire:model="form.cta_label" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('CTA URL') }}</label>
                        <input type="text" wire:model="form.cta_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('/shop or https://...') }}" />
                    </div>
                </div>

                @if (($form['type'] ?? '') === 'five_star_reviews_carousel' || ($form['type'] ?? '') === 'blogs_carousel')
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                            {{ ($form['type'] ?? '') === 'blogs_carousel' ? __('Number of blog posts to show') : __('Number of comments to show') }}
                        </label>
                        <input type="number" min="1" max="50" wire:model="form.items_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm md:max-w-[220px]" />
                        @error('form.items_limit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                        @if (($form['type'] ?? '') === 'blogs_carousel')
                            <div class="mt-2 md:max-w-[220px]">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Blog source') }}</label>
                                <select wire:model="form.blog_source" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                    <option value="latest">{{ __('Latest') }}</option>
                                    <option value="featured">{{ __('Featured only') }}</option>
                                </select>
                            </div>
                        @else
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="form.reviews_featured_only" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">{{ __('Featured comments only') }}</span>
                            </label>
                        @endif
                    </div>
                @endif

                <p class="mt-3 text-xs text-slate-500">{{ __('Main markup/content is edited in the Blade Template section below (Ace).') }}</p>
            </div>

            <div class="admin-panel admin-form-panel p-6">
                <p class="admin-section-title">{{ __('Style & Background') }}</p>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Custom Classes') }}</label>
                    <input type="text" wire:model="form.custom_classes" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('extra utility classes') }}" />
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Background CSS') }}</label>
                    <textarea rows="4" wire:model="form.bg_css" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs" placeholder="{{ __('background-color:#0f172a; color:white;') }}"></textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('If a background image is uploaded, it is applied first, then this CSS is appended.') }}</p>
                </div>
            </div>
        </div>

        @if ($this->isItemBlock)
            <div class="admin-panel admin-form-panel p-6">
                <p class="admin-section-title">{{ __('Selected Items') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('Choose items and order them. No JSON IDs needed.') }}</p>

                <div class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Available') }}</label>
                        <select wire:model="pickerItemId" data-tom-select class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">{{ __('Select item...') }}</option>
                            @foreach ($this->itemOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="addSelectedItem" class="h-10 rounded-xl bg-cyan-700 px-4 text-sm font-semibold text-white hover:bg-cyan-800">{{ __('Add Item') }}</button>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($this->selectedItems as $row)
                        <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="text-sm text-slate-800">{{ $row['label'] }}</div>
                            <div class="inline-flex items-center gap-1">
                                <button type="button" wire:click="moveSelectedItemUp({{ $row['index'] }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Up') }}</button>
                                <button type="button" wire:click="moveSelectedItemDown({{ $row['index'] }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Down') }}</button>
                                <button type="button" wire:click="removeSelectedItem({{ $row['id'] }})" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">{{ __('No items selected.') }}</div>
                    @endforelse
                    @error('form.selected_item_ids') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        <div class="admin-panel admin-form-panel p-6">
            <p class="admin-section-title">{{ __('Blade Template (Per Block File)') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Saved to') }} <code>resources/views/front/content-blocks/instances/{{ $form['code'] ?: 'block-code' }}.blade.php</code>. {{ __('This block only.') }}</p>

            <div class="mt-3 mb-2 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="loadTemplatePreset" class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">{{ __('Load Default For Type') }}</button>
                <button
                    type="button"
                    data-ace-open
                    data-ace-target="content-block-template-blade"
                    data-ace-label="Content Block Blade Template"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                    {{ __('Open in Ace') }}
                </button>
            </div>

            <textarea id="content-block-template-blade" rows="16" wire:model="form.template_body" data-ace-inline class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
            @error('form.template_body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        @if ($blockId)
            <livewire:admin.media.manager
                :model-class="\App\Models\Content\ContentBlock::class"
                :model-id="$blockId"
                :locale="$form['locale']"
                :wire:key="'content-block-media-manager-'.($blockId ?? 'new').'-'.$form['locale']"
            />
        @endif

        <div class="admin-form-actions flex items-center gap-2 pt-2">
            <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                {{ $this->isEdit ? __('Update Block') : __('Create Block') }}
            </button>
            <button type="button" wire:click="backToList" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                {{ __('Cancel') }}
            </button>
        </div>
    </form>
</div>
