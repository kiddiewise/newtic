<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'primary_category_id', 'sku', 'name', 'slug', 'description',
        'price', 'stock_quantity', 'image_path', 'is_active', 'is_demo',
    ];
    protected $casts = [
        'price' => 'decimal:2', 'stock_quantity' => 'integer',
        'is_active' => 'boolean', 'is_demo' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }
}
