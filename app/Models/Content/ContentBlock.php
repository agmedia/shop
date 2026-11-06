<?php

namespace App\Models\Content;

use App\Models\Concerns\HasConfiguredMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;

class ContentBlock extends Model implements HasMedia
{
    use HasConfiguredMedia {
        registerMediaCollections as private registerConfiguredMediaCollections;
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'payload',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'payload' => 'array',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ContentBlockTranslation::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ContentBlockSlot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentBlockItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function translation(string $locale): HasOne
    {
        return $this->hasOne(ContentBlockTranslation::class)->where('locale', $locale);
    }

    public function hasLocalizedSlides(): bool
    {
        return in_array($this->type, ['full_width_image_slider', 'desktopfullwidthimageslider'], true);
    }

    public function registerMediaCollections(): void
    {
        $this->registerConfiguredMediaCollections();

        if ($this->hasLocalizedSlides()) {
            foreach ($this->mediaCollections as $collection) {
                if ($collection->name === 'block_slides') {
                    // Uploading a translated set must not prune another language's slides.
                    $collection->collectionSizeLimit = false;
                }
            }
        }
    }

    /**
     * A translated slide set replaces the shared set; another language's images
     * must never appear merely because the current language has no uploads yet.
     */
    public function slidesForLocale(?string $locale = null): Collection
    {
        $slides = $this->getMedia('block_slides');
        $locale = strtolower(trim($locale ?? app()->getLocale()));
        $translated = $slides->filter(fn ($media): bool => strtolower(trim((string) $media->getCustomProperty('locale', ''))) === $locale);

        return ($translated->isNotEmpty()
            ? $translated
            : $slides->filter(fn ($media): bool => trim((string) $media->getCustomProperty('locale', '')) === ''))
            ->values();
    }
}
