<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'primary_category_id', 'sku', 'name', 'slug', 'description',
        'price', 'compare_at_price', 'stock_quantity', 'image_path', 'is_active', 'is_demo', 'option_label',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'view_count' => 'integer',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
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
