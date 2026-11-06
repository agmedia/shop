<?php

namespace App\Livewire\Admin\Media;

use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\ContentBlock;
use App\Services\Content\InstagramPostOEmbedService;
use App\Services\Integrations\Kipos\KiposSyncService;
use App\Support\Media\MediaProfileRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Manager extends Component
{
    use WithFileUploads;

    public string $modelClass = '';
    public ?int $modelId = null;
    public string $locale = '';

    /**
     * @var array<string, mixed>
     */
    public array $uploads = [];

    /**
     * @var array<int, array{
     *     name: string,
     *     locale: string,
     *     alt: string,
     *     caption: string,
     *     link_url: string,
     *     block_title: string,
     *     cta_1_label: string,
     *     cta_1_url: string,
     *     cta_2_label: string,
     *     cta_2_url: string,
     *     hotspots: array<int, array{product_id:int|string|null,x:float|int|string,y:float|int|string}>,
     *     focal_x: float|int,
     *     focal_y: float|int,
     *     crop_enabled: bool,
     *     crop_x: float|int,
     *     crop_y: float|int,
     *     crop_width: float|int,
     *     crop_height: float|int
     * }>
     */
    public array $meta = [];

    public string $kiposImageAction = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $kiposImageResult = null;

    public ?string $kiposImageError = null;

    public function mount(string $modelClass, ?int $modelId = null, string $locale = ''): void
    {
        $knownModels = MediaProfileRegistry::modelClasses();
        abort_unless(in_array($modelClass, $knownModels, true), 404);

        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->locale = trim($locale) !== '' ? $locale : (string) config('app.locale', 'en');
    }

    public function uploadCollection(string $collectionName): void
    {
        $record = $this->record;
        if (! $record) {
            $this->dispatch('notify', type: 'warning', message: __('Save record first, then upload images.'));
            return;
        }

        $collectionConfig = MediaProfileRegistry::collectionForModel($this->modelClass, $collectionName);
        if ($collectionConfig === []) {
            $this->dispatch('notify', type: 'danger', message: __('Unknown media collection.'));
            return;
        }

        $isSingle = (bool) ($collectionConfig['single_file'] ?? false);
        $maxUploadKb = max(1, (int) ($collectionConfig['max_upload_kb'] ?? 8192));
        $mimeTypes = array_values(array_filter((array) ($collectionConfig['accept_mime_types'] ?? [])));
        $mimeRule = $mimeTypes !== [] ? ['mimetypes:'.implode(',', $mimeTypes)] : [];

        if ($isSingle) {
            $this->validate([
                "uploads.$collectionName" => array_merge(['required', 'file', "max:$maxUploadKb"], $mimeRule),
            ]);
        } else {
            $this->validate([
                "uploads.$collectionName" => ['required', 'array', 'min:1'],
                "uploads.$collectionName.*" => array_merge(['required', 'file', "max:$maxUploadKb"], $mimeRule),
            ]);
        }

        $input = $this->uploads[$collectionName] ?? null;
        $files = $this->normalizeFiles($input, $isSingle);

        if ($files === []) {
            $this->dispatch('notify', type: 'warning', message: __('Choose at least one image first.'));
            return;
        }

        foreach ($files as $file) {
            $originalName = (string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeBaseName = Str::slug($originalName) ?: 'image';
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $fileName = $safeBaseName.'-'.Str::lower(Str::random(6)).'.'.$ext;

            $mediaAdder = $record->addMedia($file->getRealPath())
                ->usingName($originalName !== '' ? $originalName : $safeBaseName)
                ->usingFileName($fileName);

            if ($record instanceof ContentBlock && $record->hasLocalizedSlides() && $collectionName === 'block_slides') {
                $mediaAdder->withCustomProperties(['locale' => strtolower(trim($this->locale))]);
            }

            $mediaAdder->toMediaCollection($collectionName);
        }

        $mainCollection = (string) ($this->modelProfile['main_collection'] ?? '');
        if ($mainCollection !== '' && $collectionName !== $mainCollection && ! $record->getFirstMedia($mainCollection)) {
            $firstUploaded = $record->getMedia($collectionName)->first();
            if ($firstUploaded) {
                $firstUploaded->copy($record, $mainCollection);
            }
        }

        unset($this->uploads[$collectionName]);
        $this->meta = [];

        $this->dispatch('notify', type: 'success', message: __('Images uploaded.'));
    }

    public function addInstagramPostSlide(string $collectionName): void
    {
        $record = $this->record;
        if (! $record instanceof ContentBlock) {
            $this->dispatch('notify', type: 'warning', message: __('Save record first, then add Instagram posts.'));
            return;
        }

        if ((string) $record->type !== 'instagram_curated_grid' || $collectionName !== 'block_slides') {
            $this->dispatch('notify', type: 'warning', message: __('Instagram posts can only be added to the Instagram widget slides.'));
            return;
        }

        $placeholderPath = public_path('front-theme/images/demo_img.png');
        if (! is_file($placeholderPath)) {
            $this->dispatch('notify', type: 'danger', message: __('Could not find the Instagram slide placeholder image.'));
            return;
        }

        $record->addMedia($placeholderPath)
            ->usingName('Instagram post')
            ->usingFileName('instagram-post-'.Str::lower(Str::random(6)).'.png')
            ->withCustomProperties([
                'link_url' => [],
                'link_url_value' => '',
                'caption' => [],
                'block_title' => [],
                'alt' => [],
            ])
            ->toMediaCollection($collectionName);

        unset($this->uploads[$collectionName]);
        $this->meta = [];

        $this->dispatch('notify', type: 'success', message: __('New Instagram post slot added. Paste the post URL and click Save Meta.'));
    }

    public function delete(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);
        if (! $media) {
            $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
            return;
        }

        $media->delete();
        unset($this->meta[$mediaId]);
        $this->dispatch('notify', type: 'success', message: __('Image deleted.'));
    }

    public function moveUp(int $mediaId): void
    {
        $this->reorderInCollection($mediaId, -1);
    }

    public function moveDown(int $mediaId): void
    {
        $this->reorderInCollection($mediaId, 1);
    }

    public function copyToMain(int $mediaId): void
    {
        $record = $this->record;
        if (! $record) {
            return;
        }

        $mainCollection = (string) (MediaProfileRegistry::forModel($this->modelClass)['main_collection'] ?? '');
        if ($mainCollection === '') {
            return;
        }

        $media = $this->findMedia($mediaId);
        if (! $media) {
            $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
            return;
        }

        if ($media->collection_name === $mainCollection) {
            return;
        }

        $media->copy($record, $mainCollection);
        $this->meta = [];

        $this->dispatch('notify', type: 'success', message: __('Image copied to main collection.'));
    }

    public function importProductImagesFromKipos(KiposSyncService $kiposSync): void
    {
        $this->syncProductImagesFromKipos(false, $kiposSync);
    }

    public function replaceProductImagesFromKipos(KiposSyncService $kiposSync): void
    {
        $this->syncProductImagesFromKipos(true, $kiposSync);
    }

    public function updatedMeta(mixed $value, string $key): void
    {
        [$mediaId, $field] = array_pad(explode('.', $key, 2), 2, '');
        $mediaId = (int) $mediaId;
        $field = (string) $field;

        if ($mediaId <= 0 || ! $this->shouldAutosaveMetaField($field)) {
            return;
        }

        $this->saveMeta($mediaId, false);
    }

    public function saveMeta(int $mediaId, bool $notify = true): void
    {
        $media = $this->findMedia($mediaId);
        if (! $media) {
            if ($notify) {
                $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
            }

            return;
        }

        $meta = (array) ($this->meta[$mediaId] ?? []);
        $name = trim((string) ($meta['name'] ?? $media->name));
        $alt = trim((string) ($meta['alt'] ?? ''));
        $caption = trim((string) ($meta['caption'] ?? ''));
        $linkUrl = trim((string) ($meta['link_url'] ?? ''));
        $blockTitle = trim((string) ($meta['block_title'] ?? ''));
        $cta1Label = trim((string) ($meta['cta_1_label'] ?? ''));
        $cta1Url = trim((string) ($meta['cta_1_url'] ?? ''));
        $cta2Label = trim((string) ($meta['cta_2_label'] ?? ''));
        $cta2Url = trim((string) ($meta['cta_2_url'] ?? ''));
        $hotspots = is_array($meta['hotspots'] ?? null) ? $meta['hotspots'] : [];

        $instagramImportError = null;
        $instagramImported = false;
        $custom = (array) ($media->custom_properties ?? []);
        $locale = trim($this->locale) !== '' ? $this->locale : (string) config('app.locale', 'en');

        $record = $this->record;
        if ($record instanceof ContentBlock && $record->hasLocalizedSlides() && $media->collection_name === 'block_slides') {
            $this->validate([
                "meta.$mediaId.locale" => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:[_-][a-zA-Z]{2,4})?$/'],
            ]);
            $imageLocale = strtolower(trim((string) ($meta['locale'] ?? $media->getCustomProperty('locale', ''))));
            if ($imageLocale === '') {
                Arr::forget($custom, 'locale');
            } else {
                data_set($custom, 'locale', $imageLocale);
            }
        }

        if ($notify && $this->shouldImportInstagramPreview($media, $linkUrl)) {
            try {
                $instagramData = $this->importInstagramPreview($media, $linkUrl);
                $linkUrl = (string) ($instagramData['canonical_url'] ?? $linkUrl);
                $caption = (string) ($instagramData['caption'] ?? $caption);
                $alt = (string) ($instagramData['alt'] ?? $alt);
                $blockTitle = (string) ($instagramData['block_title'] ?? $blockTitle);

                if ($name === '' || Str::startsWith(Str::lower($name), 'instagram post')) {
                    $name = (string) ($instagramData['name'] ?? $name);
                }

                data_set($custom, 'instagram_shortcode', (string) ($instagramData['shortcode'] ?? ''));
                data_set($custom, 'instagram_author_name', (string) ($instagramData['author_name'] ?? ''));
                data_set($custom, 'instagram_media_kind', (string) ($instagramData['media_kind'] ?? 'image'));
                data_set($custom, 'instagram_thumbnail_url', (string) ($instagramData['thumbnail_url'] ?? ''));

                $instagramImported = true;
            } catch (\Throwable $e) {
                report($e);
                $instagramImportError = $e->getMessage();
            }
        }

        if ($alt === '') {
            Arr::forget($custom, "alt.$locale");
        } else {
            data_set($custom, "alt.$locale", $alt);
        }

        if ($caption === '') {
            Arr::forget($custom, "caption.$locale");
        } else {
            data_set($custom, "caption.$locale", $caption);
        }

        if ($linkUrl === '') {
            Arr::forget($custom, "link_url.$locale");
            Arr::forget($custom, 'link_url_value');
        } else {
            data_set($custom, "link_url.$locale", $linkUrl);
            data_set($custom, 'link_url_value', $linkUrl);
        }

        if ($blockTitle === '') {
            Arr::forget($custom, "block_title.$locale");
        } else {
            data_set($custom, "block_title.$locale", $blockTitle);
        }

        if ($cta1Label === '') {
            Arr::forget($custom, "cta_1_label.$locale");
        } else {
            data_set($custom, "cta_1_label.$locale", $cta1Label);
        }

        if ($cta1Url === '') {
            Arr::forget($custom, "cta_1_url.$locale");
        } else {
            data_set($custom, "cta_1_url.$locale", $cta1Url);
        }

        if ($cta2Label === '') {
            Arr::forget($custom, "cta_2_label.$locale");
        } else {
            data_set($custom, "cta_2_label.$locale", $cta2Label);
        }

        if ($cta2Url === '') {
            Arr::forget($custom, "cta_2_url.$locale");
        } else {
            data_set($custom, "cta_2_url.$locale", $cta2Url);
        }

        $normalizedHotspots = collect($hotspots)
            ->map(function (mixed $row): array {
                $row = is_array($row) ? $row : [];

                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'x' => $this->normalizePercent($row['x'] ?? 50, 50),
                    'y' => $this->normalizePercent($row['y'] ?? 50, 50),
                ];
            })
            ->filter(fn (array $row): bool => (int) ($row['product_id'] ?? 0) > 0)
            ->values()
            ->take(3);

        if ($normalizedHotspots->isNotEmpty()) {
            $validIds = Product::query()
                ->whereIn('id', $normalizedHotspots->pluck('product_id')->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $normalizedHotspots = $normalizedHotspots
                ->filter(fn (array $row): bool => in_array((int) $row['product_id'], $validIds, true))
                ->values()
                ->take(3);
        }

        if ($normalizedHotspots->isEmpty()) {
            Arr::forget($custom, 'product_hotspots');
        } else {
            data_set($custom, 'product_hotspots', $normalizedHotspots->all());
        }

        $media->name = $name !== '' ? $name : $media->name;
        $media->custom_properties = $custom;
        $media->save();
        $this->meta[$mediaId] = array_merge($meta, [
            'name' => (string) $media->name,
            'alt' => $alt,
            'caption' => $caption,
            'link_url' => $linkUrl,
            'block_title' => $blockTitle,
        ]);

        if ($notify) {
            if ($instagramImported) {
                $this->dispatch('notify', type: 'success', message: __('Image metadata saved and Instagram preview refreshed.'));
            } elseif ($instagramImportError !== null) {
                $this->dispatch('notify', type: 'warning', message: __('Image metadata saved, but Instagram import failed: :message', ['message' => $instagramImportError]));
            } else {
                $this->dispatch('notify', type: 'success', message: __('Image metadata saved.'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $edit
     */
    public function saveImageEditFromModal(int $mediaId, array $edit): void
    {
        $media = $this->findMedia($mediaId);
        if (! $media) {
            $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
            return;
        }

        $focalX = $this->normalizePercent($edit['focal_x'] ?? 50, 50);
        $focalY = $this->normalizePercent($edit['focal_y'] ?? 50, 50);
        $cropEnabled = filter_var($edit['crop_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $cropX = $this->normalizePercent($edit['crop_x'] ?? 0, 0);
        $cropY = $this->normalizePercent($edit['crop_y'] ?? 0, 0);
        $cropWidth = $this->normalizePercent($edit['crop_width'] ?? 100, 100, 1);
        $cropHeight = $this->normalizePercent($edit['crop_height'] ?? 100, 100, 1);

        $custom = (array) ($media->custom_properties ?? []);
        data_set($custom, 'image_edit.focal_point', [
            'x' => $focalX,
            'y' => $focalY,
        ]);
        data_set($custom, 'image_edit.crop_box', [
            'enabled' => $cropEnabled,
            'x' => $cropX,
            'y' => $cropY,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);

        $media->custom_properties = $custom;
        $media->save();
        app(FileManipulator::class)->createDerivedFiles($media, onlyMissing: false);

        $meta = (array) ($this->meta[$mediaId] ?? []);
        $meta['focal_x'] = $focalX;
        $meta['focal_y'] = $focalY;
        $meta['crop_enabled'] = $cropEnabled;
        $meta['crop_x'] = $cropX;
        $meta['crop_y'] = $cropY;
        $meta['crop_width'] = $cropWidth;
        $meta['crop_height'] = $cropHeight;
        $this->meta[$mediaId] = $meta;

        $this->dispatch('notify', type: 'success', message: __('Crop/focal saved and conversions regenerated.'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCollectionsProperty(): array
    {
        return MediaProfileRegistry::collectionsForModel($this->modelClass);
    }

    /**
     * @return array<string, Collection<int, Media>>
     */
    public function getMediaByCollectionProperty(): array
    {
        $record = $this->record;
        $result = [];

        foreach ($this->collections as $collectionName => $_config) {
            if (! $record) {
                $result[$collectionName] = collect();
                continue;
            }

            $result[$collectionName] = $record->media()
                ->where('collection_name', $collectionName)
                ->orderBy('order_column')
                ->orderBy('id')
                ->get();
        }

        return $result;
    }

    public function getModelProfileProperty(): array
    {
        return MediaProfileRegistry::forModel($this->modelClass);
    }

    public function render()
    {
        $mediaByCollection = $this->mediaByCollection;
        $this->primeMetaInputs($mediaByCollection);
        $record = $this->record;
        $kiposSync = app(KiposSyncService::class);
        $isContentBlock = $record instanceof \App\Models\Content\ContentBlock;
        $blockType = $isContentBlock ? (string) ($record->type ?? '') : '';
        $isDualImageCtaBlock = $blockType === 'dual_image_cta';
        $isCategoryEditorialTilesBlock = $blockType === 'category_editorial_tiles';
        $isInstagramCuratedBlock = $blockType === 'instagram_curated_grid';
        $isLinkableSliderBlock = in_array($blockType, ['full_width_image_slider', 'desktopfullwidthimageslider'], true);
        $isBlogPostMedia = $record instanceof BlogPost;
        $supportsProductHotspots = $isBlogPostMedia || $isLinkableSliderBlock;
        $isProductMedia = $record instanceof Product;

        return view('livewire.admin.media.manager', [
            'collections' => $this->collections,
            'modelProfile' => $this->modelProfile,
            'mediaByCollection' => $mediaByCollection,
            'recordExists' => (bool) $this->record,
            'isDualImageCtaBlock' => $isDualImageCtaBlock,
            'isCategoryEditorialTilesBlock' => $isCategoryEditorialTilesBlock,
            'isInstagramCuratedBlock' => $isInstagramCuratedBlock,
            'isLinkableSliderBlock' => $isLinkableSliderBlock,
            'isBlogPostMedia' => $isBlogPostMedia,
            'supportsProductHotspots' => $supportsProductHotspots,
            'hotspotProductOptions' => $supportsProductHotspots ? $this->hotspotProductOptions : collect(),
            'isProductMedia' => $isProductMedia,
            'kiposProductImagesEnabled' => $isProductMedia && $kiposSync->connectorEnabled(),
            'kiposProductCode' => $isProductMedia ? strtoupper(trim((string) ($record->code ?? ''))) : '',
        ]);
    }

    public function getHotspotProductOptionsProperty(): Collection
    {
        $locale = trim($this->locale) !== '' ? $this->locale : (string) config('app.locale', 'en');
        $fallbackLocale = (string) config('app.locale', 'en');
        $record = $this->record;

        $pinnedIds = collect();
        if ($record) {
            $pinnedIds = $record->media()
                ->get()
                ->flatMap(function (Media $media): array {
                    return collect((array) data_get($media->custom_properties, 'product_hotspots', []))
                        ->pluck('product_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                })
                ->filter()
                ->unique()
                ->values();
        }

        $recentIds = Product::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(400)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $optionIds = $recentIds
            ->concat($pinnedIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Product::query()
            ->select(['id', 'code', 'sku'])
            ->where('is_active', true)
            ->when($optionIds !== [], fn ($q) => $q->whereIn('id', $optionIds))
            ->with([
                'translations' => fn ($q) => $q
                    ->whereIn('locale', [$locale, $fallbackLocale])
                    ->select(['product_id', 'locale', 'name']),
            ])
            ->orderByDesc('id')
            ->limit(max(400, count($optionIds)))
            ->get()
            ->map(function (Product $product) use ($locale, $fallbackLocale): array {
                $translation = $product->translations->firstWhere('locale', $locale)
                    ?? $product->translations->firstWhere('locale', $fallbackLocale);

                $name = trim((string) ($translation?->name ?? $product->code));
                $sku = trim((string) ($product->sku ?? ''));
                $code = trim((string) ($product->code ?? ''));
                $suffix = trim(($sku !== '' ? '/ '.$sku.' ' : '').($code !== '' ? '('.$code.')' : ''));

                return [
                    'id' => (int) $product->id,
                    'label' => trim($name.' '.$suffix),
                ];
            });
    }

    public function getRecordProperty(): ?Model
    {
        if (! $this->modelId) {
            return null;
        }

        $class = $this->modelClass;
        if (! class_exists($class)) {
            return null;
        }

        return $class::query()->find($this->modelId);
    }

    /**
     * @param  array<string, Collection<int, Media>>  $mediaByCollection
     */
    private function primeMetaInputs(array $mediaByCollection): void
    {
        foreach ($mediaByCollection as $collectionMedia) {
            foreach ($collectionMedia as $media) {
                if (isset($this->meta[$media->id])) {
                    continue;
                }

                $custom = (array) ($media->custom_properties ?? []);
                $locale = trim($this->locale) !== '' ? $this->locale : (string) config('app.locale', 'en');
                $edit = (array) data_get($custom, 'image_edit', []);
                $focal = (array) ($edit['focal_point'] ?? []);
                $crop = (array) ($edit['crop_box'] ?? []);
                $hotspots = collect((array) data_get($custom, 'product_hotspots', []))
                    ->map(function (mixed $row): array {
                        $row = is_array($row) ? $row : [];

                        return [
                            'product_id' => (int) ($row['product_id'] ?? 0),
                            'x' => $this->normalizePercent($row['x'] ?? 50, 50),
                            'y' => $this->normalizePercent($row['y'] ?? 50, 50),
                        ];
                    })
                    ->filter(fn (array $row): bool => (int) ($row['product_id'] ?? 0) > 0)
                    ->values()
                    ->take(3)
                    ->all();

                $this->meta[$media->id] = [
                    'name' => (string) $media->name,
                    'locale' => (string) data_get($custom, 'locale', ''),
                    'alt' => (string) data_get($custom, "alt.$locale", ''),
                    'caption' => (string) data_get($custom, "caption.$locale", ''),
                    'link_url' => (string) (
                        data_get($custom, "link_url.$locale")
                        ?? data_get($custom, 'link_url_value', '')
                    ),
                    'block_title' => (string) data_get($custom, "block_title.$locale", ''),
                    'cta_1_label' => (string) data_get($custom, "cta_1_label.$locale", ''),
                    'cta_1_url' => (string) data_get($custom, "cta_1_url.$locale", ''),
                    'cta_2_label' => (string) data_get($custom, "cta_2_label.$locale", ''),
                    'cta_2_url' => (string) data_get($custom, "cta_2_url.$locale", ''),
                    'hotspots' => $hotspots,
                    'focal_x' => $this->normalizePercent($focal['x'] ?? 50, 50),
                    'focal_y' => $this->normalizePercent($focal['y'] ?? 50, 50),
                    'crop_enabled' => (bool) ($crop['enabled'] ?? false),
                    'crop_x' => $this->normalizePercent($crop['x'] ?? 0, 0),
                    'crop_y' => $this->normalizePercent($crop['y'] ?? 0, 0),
                    'crop_width' => $this->normalizePercent($crop['width'] ?? 100, 100, 1),
                    'crop_height' => $this->normalizePercent($crop['height'] ?? 100, 100, 1),
                ];
            }
        }
    }

    private function findMedia(int $mediaId): ?Media
    {
        $record = $this->record;
        if (! $record) {
            return null;
        }

        return $record->media()
            ->whereKey($mediaId)
            ->first();
    }

    private function reorderInCollection(int $mediaId, int $direction): void
    {
        $media = $this->findMedia($mediaId);
        if (! $media) {
            $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
            return;
        }

        $record = $this->record;
        if (! $record) {
            return;
        }

        $ids = $record->media()
            ->where('collection_name', $media->collection_name)
            ->orderBy('order_column')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $currentIndex = array_search($media->id, $ids, true);
        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $currentIndex + $direction;
        if (! isset($ids[$targetIndex])) {
            return;
        }

        [$ids[$currentIndex], $ids[$targetIndex]] = [$ids[$targetIndex], $ids[$currentIndex]];
        Media::setNewOrder($ids);

        $this->dispatch('notify', type: 'success', message: __('Image order updated.'));
    }

    /**
     * @param  mixed  $value
     * @return array<int, TemporaryUploadedFile>
     */
    private function normalizeFiles(mixed $value, bool $singleFile): array
    {
        if ($value instanceof TemporaryUploadedFile) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $files = array_values(array_filter(
            $value,
            fn ($file) => $file instanceof TemporaryUploadedFile
        ));

        if ($singleFile && $files !== []) {
            return [end($files)];
        }

        return $files;
    }

    private function normalizePercent(mixed $value, float $default, float $min = 0.0): float
    {
        $number = is_numeric($value) ? (float) $value : $default;
        $number = max($min, min(100.0, $number));

        return round($number, 2);
    }

    private function syncProductImagesFromKipos(bool $replaceExisting, KiposSyncService $kiposSync): void
    {
        $record = $this->record;
        if (! $record instanceof Product) {
            $this->dispatch('notify', type: 'warning', message: __('Kipos image sync is available only for product media.'));
            return;
        }

        $this->kiposImageAction = $replaceExisting ? 'replace' : 'import';

        try {
            $this->kiposImageResult = $kiposSync->syncProductImages($record, $replaceExisting, $this->locale);
            $this->kiposImageError = null;
            $this->meta = [];

            $updated = (int) ($this->kiposImageResult['updated_products'] ?? 0);
            $message = (string) ($this->kiposImageResult['summary'] ?? __('Kipos product image sync completed.'));

            $this->dispatch('notify', type: $updated > 0 ? 'success' : 'info', message: $message);
        } catch (\Throwable $exception) {
            $this->kiposImageError = $exception->getMessage();
            $this->dispatch('notify', type: 'error', message: __('Kipos product image sync failed: :error', ['error' => $exception->getMessage()]));
        } finally {
            $this->kiposImageAction = '';
        }
    }

    private function shouldImportInstagramPreview(Media $media, string $linkUrl): bool
    {
        $record = $this->record;

        return $record instanceof ContentBlock
            && (string) $record->type === 'instagram_curated_grid'
            && $media->collection_name === 'block_slides'
            && trim($linkUrl) !== '';
    }

    /**
     * @return array{
     *     canonical_url: string,
     *     caption: string,
     *     alt: string,
     *     block_title: string,
     *     name: string,
     *     shortcode: string,
     *     author_name: string,
     *     media_kind: string,
     *     thumbnail_url: string
     * }
     */
    private function importInstagramPreview(Media $media, string $linkUrl): array
    {
        $instagramData = app(InstagramPostOEmbedService::class)->fetch($linkUrl);
        $newExtension = $this->extensionForMimeType((string) ($instagramData['mime_type'] ?? ''));
        $currentPathRelative = $media->getPathRelativeToRoot();
        $currentExtension = strtolower((string) pathinfo($media->file_name, PATHINFO_EXTENSION));

        if (
            $newExtension !== null
            && $newExtension !== $currentExtension
            && Storage::disk($media->disk)->exists($currentPathRelative)
        ) {
            $baseName = pathinfo($media->file_name, PATHINFO_FILENAME);
            $baseName = Str::slug((string) $baseName) ?: 'instagram-'.(string) ($instagramData['shortcode'] ?? $media->id);
            $media->file_name = $baseName.'.'.$newExtension;
        }

        $media->mime_type = (string) ($instagramData['mime_type'] ?? $media->mime_type);
        $media->responsive_images = [];
        $media->generated_conversions = [];
        $media->save();

        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), (string) $instagramData['thumbnail_bytes']);
        clearstatcache(true, $media->getPath());
        $media->size = max(1, (int) filesize($media->getPath()));
        $media->save();

        app(FileManipulator::class)->createDerivedFiles($media, onlyMissing: false);

        return [
            'canonical_url' => (string) ($instagramData['canonical_url'] ?? $linkUrl),
            'caption' => (string) ($instagramData['caption'] ?? ''),
            'alt' => (string) ($instagramData['alt'] ?? ''),
            'block_title' => (string) ($instagramData['block_title'] ?? ''),
            'name' => 'Instagram post '.(string) ($instagramData['shortcode'] ?? $media->id),
            'shortcode' => (string) ($instagramData['shortcode'] ?? ''),
            'author_name' => (string) ($instagramData['author_name'] ?? ''),
            'media_kind' => (string) ($instagramData['media_kind'] ?? 'image'),
            'thumbnail_url' => (string) ($instagramData['thumbnail_url'] ?? ''),
        ];
    }

    private function extensionForMimeType(string $mimeType): ?string
    {
        return match (strtolower(trim($mimeType))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => null,
        };
    }

    private function shouldAutosaveMetaField(string $field): bool
    {
        $record = $this->record;

        if (! $record instanceof ContentBlock) {
            return false;
        }

        $blockType = (string) $record->type;

        if ($blockType === 'dual_image_cta') {
            return in_array($field, [
                'link_url',
                'block_title',
                'cta_1_label',
                'cta_1_url',
                'cta_2_label',
                'cta_2_url',
            ], true);
        }

        if ($blockType === 'category_editorial_tiles') {
            return in_array($field, [
                'link_url',
                'block_title',
            ], true);
        }

        if ($blockType === 'instagram_curated_grid') {
            return in_array($field, [
                'link_url',
                'block_title',
            ], true);
        }

        return false;
    }
}
