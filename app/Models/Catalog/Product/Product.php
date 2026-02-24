<?php

namespace App\Models\Catalog\Product;

use App\Models\Concerns\HasConfiguredMedia;
use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Attribute\Attribute;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Option\Option;
use App\Models\Content\Support\Comment;
use App\Models\Settings\Local\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;

class Product extends Model implements HasMedia
{
    use HasConfiguredMedia;

    protected $fillable = [
        'code',
        'sku',
        'is_active',
        'manufacturer_id',
        'tax_rate_id',
        'base_price',
        'stock_qty',
        'payload',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'manufacturer_id' => 'int',
        'tax_rate_id' => 'int',
        'base_price' => 'decimal:2',
        'stock_qty' => 'int',
        'payload' => 'array',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function translation(string $locale): HasOne
    {
        return $this->hasOne(ProductTranslation::class)->where('locale', $locale);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps();
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'catalog_option_product', 'product_id', 'option_id')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps();
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'catalog_attribute_product', 'product_id', 'attribute_id')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function directActions(): BelongsToMany
    {
        return $this->belongsToMany(CatalogAction::class, 'catalog_action_targets', 'target_id', 'action_id')
            ->wherePivot('target_type', CatalogAction::TARGET_PRODUCT)
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
