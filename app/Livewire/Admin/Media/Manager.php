<?php

namespace App\Livewire\Admin\Media;

use App\Support\Media\MediaProfileRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     *     alt: string,
     *     caption: string,
     *     block_title: string,
     *     cta_1_label: string,
     *     cta_1_url: string,
     *     cta_2_label: string,
     *     cta_2_url: string,
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

            $record->addMedia($file->getRealPath())
                ->usingName($originalName !== '' ? $originalName : $safeBaseName)
                ->usingFileName($fileName)
                ->toMediaCollection($collectionName);
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

    public function saveMeta(int $mediaId): void
    {
        $media = $this->findMedia($mediaId);
        if (! $media) {
            $this->dispatch('notify', type: 'warning', message: __('Image not found.'));
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

        $custom = (array) ($media->custom_properties ?? []);
        $locale = trim($this->locale) !== '' ? $this->locale : (string) config('app.locale', 'en');

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

        $media->name = $name !== '' ? $name : $media->name;
        $media->custom_properties = $custom;
        $media->save();

        $this->dispatch('notify', type: 'success', message: __('Image metadata saved.'));
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
        $isContentBlock = $record instanceof \App\Models\Content\ContentBlock;
        $blockType = $isContentBlock ? (string) ($record->type ?? '') : '';
        $isDualImageCtaBlock = $blockType === 'dual_image_cta';
        $isLinkableSliderBlock = in_array($blockType, ['full_width_image_slider', 'desktopfullwidthimageslider'], true);

        return view('livewire.admin.media.manager', [
            'collections' => $this->collections,
            'modelProfile' => $this->modelProfile,
            'mediaByCollection' => $mediaByCollection,
            'recordExists' => (bool) $this->record,
            'isDualImageCtaBlock' => $isDualImageCtaBlock,
            'isLinkableSliderBlock' => $isLinkableSliderBlock,
        ]);
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

                $this->meta[$media->id] = [
                    'name' => (string) $media->name,
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
}
