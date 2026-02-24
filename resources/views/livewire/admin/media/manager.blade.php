<div class="admin-panel admin-form-panel p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="admin-section-title">{{ __('Images') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Manage main, banner/icon and gallery images with per-locale alt/caption metadata.') }}</p>
        </div>
        <span class="admin-chip">{{ __('Locale:') }} {{ $locale }}</span>
    </div>

    @if (! $recordExists)
        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
            {{ __('Save this record first, then upload and organize images.') }}
        </div>
    @else
        <div class="space-y-4">
            @foreach ($collections as $collectionName => $collectionConfig)
                @php
                    $collectionMedia = $mediaByCollection[$collectionName] ?? collect();
                    $isSingle = (bool) ($collectionConfig['single_file'] ?? false);
                    $acceptMime = (array) ($collectionConfig['accept_mime_types'] ?? []);
                    $maxUploadKb = max(1, (int) ($collectionConfig['max_upload_kb'] ?? 8192));
                    $previewConversion = (string) ($collectionConfig['preview_conversion'] ?? '');
                    $mainCollection = (string) ($modelProfile['main_collection'] ?? '');
                    $isMainCollection = $mainCollection !== '' && $mainCollection === $collectionName;
                @endphp

                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-800">{{ $collectionConfig['label'] ?? $collectionName }}</h3>
                            <span class="admin-chip">{{ $collectionName }}</span>
                            <span class="admin-chip">{{ $collectionMedia->count() }} {{ $collectionMedia->count() === 1 ? __('image') : __('images') }}</span>
                            @if ($isMainCollection)
                                <span class="admin-chip">{{ __('Main') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                        <div>
                            <input
                                type="file"
                                wire:model="uploads.{{ $collectionName }}"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                                accept="{{ implode(',', $acceptMime) }}"
                                @if (! $isSingle) multiple @endif
                            />
                            @error("uploads.$collectionName") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            @error("uploads.$collectionName.*") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">
                                {{ __('Max upload:') }} {{ number_format($maxUploadKb / 1024, 1) }} MB
                                @if ($acceptMime !== [])
                                    | {{ implode(', ', $acceptMime) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-start">
                            <button
                                type="button"
                                wire:click="uploadCollection('{{ $collectionName }}')"
                                class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
                            >
                                {{ __('Upload') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="admin-items-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">{{ __('Preview') }}</th>
                                    <th class="px-3 py-2 text-left">{{ __('Meta') }}</th>
                                    <th class="px-3 py-2 text-center">{{ __('Sort') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($collectionMedia as $media)
                                    @php
                                        $mediaMeta = (array) ($meta[$media->id] ?? []);
                                        $focalX = (float) ($mediaMeta['focal_x'] ?? 50);
                                        $focalY = (float) ($mediaMeta['focal_y'] ?? 50);
                                        $cropEnabled = (bool) ($mediaMeta['crop_enabled'] ?? false);
                                        $cropX = (float) ($mediaMeta['crop_x'] ?? 0);
                                        $cropY = (float) ($mediaMeta['crop_y'] ?? 0);
                                        $cropWidth = (float) ($mediaMeta['crop_width'] ?? 100);
                                        $cropHeight = (float) ($mediaMeta['crop_height'] ?? 100);
                                        $previewUrl = $previewConversion !== '' && $media->hasGeneratedConversion($previewConversion)
                                            ? $media->getUrl($previewConversion)
                                            : $media->getUrl();
                                    @endphp
                                    <tr wire:key="media-{{ $collectionName }}-{{ $media->id }}">
                                        <td class="px-3 py-3 align-top">
                                            <img src="{{ $previewUrl }}" alt="" class="h-20 w-28 rounded-lg border border-slate-200 bg-slate-100 object-cover" />
                                            <p class="mt-1 text-[11px] text-slate-500">{{ $media->file_name }}</p>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            @if ($isDualImageCtaBlock && $collectionName === 'block_slides')
                                                <div class="grid gap-2 md:grid-cols-2">
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.block_title" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs md:col-span-2" placeholder="{{ __('Block title') }} ({{ $locale }})" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.cta_1_label" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('CTA 1 label') }} ({{ $locale }})" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.cta_1_url" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('CTA 1 URL') }} ({{ $locale }})" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.cta_2_label" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('CTA 2 label') }} ({{ $locale }})" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.cta_2_url" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('CTA 2 URL') }} ({{ $locale }})" />
                                                </div>
                                            @else
                                                <div class="grid gap-2 md:grid-cols-3">
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.name" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('Name') }}" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.alt" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('Alt') }} ({{ $locale }})" />
                                                    <input type="text" wire:model.defer="meta.{{ $media->id }}.caption" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('Caption') }} ({{ $locale }})" />
                                                    @if ($isLinkableSliderBlock && $collectionName === 'block_slides')
                                                        <input type="text" wire:model.defer="meta.{{ $media->id }}.link_url" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="{{ __('Link URL') }} ({{ $locale }})" />
                                                    @endif
                                                </div>
                                            @endif
                                            <p class="mt-1 text-[11px] text-slate-500">
                                                {{ number_format($media->size / 1024, 0) }} KB
                                                @if ($media->width && $media->height)
                                                    | {{ $media->width }}x{{ $media->height }}
                                                @endif
                                            </p>
                                            <div class="mt-2 flex flex-wrap items-center gap-1 text-[11px]">
                                                <span class="admin-chip">{{ __('Focal:') }} {{ number_format($focalX, 1) }} / {{ number_format($focalY, 1) }}</span>
                                                @if ($cropEnabled)
                                                    <span class="admin-chip">{{ __('Crop:') }} {{ number_format($cropX, 1) }},{{ number_format($cropY, 1) }} / {{ number_format($cropWidth, 1) }}x{{ number_format($cropHeight, 1) }}</span>
                                                @else
                                                    <span class="admin-chip">{{ __('Crop: Off') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top text-center">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" wire:click="moveUp({{ $media->id }})" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">↑</button>
                                                <button type="button" wire:click="moveDown({{ $media->id }})" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">↓</button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex flex-wrap justify-end gap-1">
                                                <button type="button" wire:click="saveMeta({{ $media->id }})" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Save Meta') }}</button>
                                                <button
                                                    type="button"
                                                    data-image-edit-open
                                                    data-media-id="{{ $media->id }}"
                                                    data-image-url="{{ $media->getUrl() }}"
                                                    data-focal-x="{{ $focalX }}"
                                                    data-focal-y="{{ $focalY }}"
                                                    data-crop-enabled="{{ $cropEnabled ? '1' : '0' }}"
                                                    data-crop-x="{{ $cropX }}"
                                                    data-crop-y="{{ $cropY }}"
                                                    data-crop-width="{{ $cropWidth }}"
                                                    data-crop-height="{{ $cropHeight }}"
                                                    class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                                >
                                                    {{ __('Edit Crop/Focus') }}
                                                </button>
                                                @if ($mainCollection !== '' && ! $isMainCollection)
                                                    <button type="button" wire:click="copyToMain({{ $media->id }})" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Copy to Main') }}</button>
                                                @endif
                                                <a href="{{ $media->getUrl() }}" target="_blank" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Open') }}</a>
                                                <button
                                                    type="button"
                                                    wire:click="delete({{ $media->id }})"
                                                    wire:confirm="{{ __('Delete this image?') }}"
                                                    class="rounded border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-5 text-center text-sm text-slate-500">
                                            {{ __('No images in this collection yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
