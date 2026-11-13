<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'primary_category_id', 'sku', 'name', 'slug', 'description',
        'price', 'compare_at_price', 'stock_quantity', 'tracks_stock', 'image_path', 'is_active', 'is_demo', 'option_label',
        'short_description', 'weight_kg', 'dimensions_cm', 'seo_title', 'seo_description', 'seo_focus_keyword',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'tracks_stock' => 'boolean',
        'view_count' => 'integer',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'dimensions_cm' => 'array',
        'weight_kg' => 'decimal:3',
    ];

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(ProductBadge::class, 'badge_product', 'product_id', 'product_badge_id')
            ->orderBy('product_badges.sort_order')->orderBy('product_badges.id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function importSource(): HasOne
    {
        return $this->hasOne(CatalogImportSource::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getDiscountPercentAttribute(): int
    {
        $regular = (float) $this->compare_at_price;
        $sale = (float) $this->price;

        return $regular > 0 && $sale > 0 && $sale < $regular
            ? max(1, min(99, (int) round(100 * ($regular - $sale) / $regular)))
            : 0;
    }
}
