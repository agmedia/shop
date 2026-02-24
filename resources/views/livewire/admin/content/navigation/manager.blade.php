<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ __('admin.content.navigation.title') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('admin.content.navigation.subtitle') }}</p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <div class="w-28">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.locale') }}</label>
                    <select wire:model.live="locale" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm lowercase">
                        @foreach ($adminLocaleOptions as $localeOption)
                            <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" wire:click="addCategoryItem" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.content.navigation.add_category') }}</button>
                <button type="button" wire:click="addPageItem" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.content.navigation.add_page') }}</button>
                <button type="button" wire:click="addBlogItem" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.content.navigation.add_blog') }}</button>
                <button type="button" wire:click="addContactItem" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.content.navigation.add_contact') }}</button>
                <button type="button" wire:click="addFaqItem" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('admin.content.navigation.add_faq') }}</button>
                <button type="button" wire:click="addCustomItem" class="rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">{{ __('admin.content.navigation.add_custom') }}</button>
            </div>
        </div>
    </div>

    <div class="admin-panel admin-panel-soft p-5">
        <h2 class="admin-section-title">{{ __('admin.common.items') }}</h2>

        @if (empty($form['items']))
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
                {{ __('admin.content.navigation.empty') }}
            </div>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($form['items'] as $index => $item)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="grid gap-3 lg:grid-cols-[8rem_1fr_1fr_1fr_auto] lg:items-end">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.content.navigation.type') }}</label>
                                <select wire:model.live="form.items.{{ $index }}.type" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                                    <option value="category">{{ __('admin.content.navigation.type_category') }}</option>
                                    <option value="page">{{ __('admin.content.navigation.type_page') }}</option>
                                    <option value="blog">{{ __('admin.content.navigation.type_blog') }}</option>
                                    <option value="contact">{{ __('admin.content.navigation.type_contact') }}</option>
                                    <option value="faq">{{ __('admin.content.navigation.type_faq') }}</option>
                                    <option value="custom">{{ __('admin.content.navigation.type_custom') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.content.navigation.label') }}</label>
                                <input type="text" wire:model.live="form.items.{{ $index }}.label" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="{{ __('admin.content.navigation.label_placeholder') }}" />
                                @error('form.items.'.$index.'.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            @if (($item['type'] ?? '') === 'category')
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.content.navigation.category') }}</label>
                                    <select wire:model.live="form.items.{{ $index }}.category_id" data-tom-select class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                                        <option value="0">{{ __('admin.content.navigation.select_category') }}</option>
                                        @foreach ($categoryOptions as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.items.'.$index.'.category_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @elseif (($item['type'] ?? '') === 'page')
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.content.navigation.page') }}</label>
                                    <select wire:model.live="form.items.{{ $index }}.page_id" data-tom-select class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                                        <option value="0">{{ __('admin.content.navigation.select_page') }}</option>
                                        @foreach ($pageOptions as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.items.'.$index.'.page_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @elseif (($item['type'] ?? '') === 'custom')
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.content.navigation.url') }}</label>
                                    <input type="text" wire:model.live="form.items.{{ $index }}.url" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="{{ __('admin.content.navigation.url_placeholder') }}" />
                                    @error('form.items.'.$index.'.url') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    {{ __('admin.content.navigation.system_route_hint') }}
                                </div>
                            @endif

                            <div class="flex flex-wrap justify-end gap-1">
                                <button type="button" wire:click="moveUp({{ $index }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">↑</button>
                                <button type="button" wire:click="moveDown({{ $index }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">↓</button>
                                <button type="button" wire:click="removeItem({{ $index }})" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ __('admin.content.navigation.remove') }}</button>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model.live="form.items.{{ $index }}.is_active" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('admin.content.navigation.is_active') }}
                            </label>

                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model.live="form.items.{{ $index }}.show_dropdown" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('admin.content.navigation.show_dropdown') }}
                            </label>

                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model.live="form.items.{{ $index }}.open_in_new_tab" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('admin.content.navigation.open_new_tab') }}
                            </label>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.common.sort') }}</label>
                                <input type="number" min="0" max="9999" wire:model.live="form.items.{{ $index }}.sort_order" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" />
                            </div>
                        </div>

                        @if (($item['show_dropdown'] ?? false) && ($item['type'] ?? '') === 'category')
                            @php
                                $currentPromoPath = (string) ($item['desktop_promo_image_path'] ?? '');
                                $currentPromoUrl = $currentPromoPath !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($currentPromoPath) : '';
                            @endphp
                            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Desktop Mega Promo</p>
                                <div class="mt-2 grid gap-3 lg:grid-cols-2">
                                    <div class="lg:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Promo Image</label>
                                        <input type="file" wire:model="desktopPromoUploads.{{ $index }}" accept="image/*" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" />
                                        @error('desktopPromoUploads.'.$index) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        @if ($currentPromoUrl !== '')
                                            <div class="mt-2 flex items-center gap-3">
                                                <img src="{{ $currentPromoUrl }}" alt="Mega promo" class="h-16 w-28 rounded-md object-cover">
                                                <button type="button" wire:click="clearDesktopPromoImage({{ $index }})" class="rounded-md border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Makni sliku</button>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Title</label>
                                        <input type="text" wire:model.live="form.items.{{ $index }}.desktop_promo_title" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="Nova kolekcija" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Subtitle</label>
                                        <input type="text" wire:model.live="form.items.{{ $index }}.desktop_promo_subtitle" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="Istaknuti komadi sezone" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">CTA Label</label>
                                        <input type="text" wire:model.live="form.items.{{ $index }}.desktop_promo_cta_label" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="Pogledaj više" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">CTA URL</label>
                                        <input type="text" wire:model.live="form.items.{{ $index }}.desktop_promo_cta_url" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="/shop ili https://..." />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 flex items-center justify-end">
            <button type="button" wire:click="save" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">{{ __('admin.content.navigation.save') }}</button>
        </div>
    </div>
</div>
