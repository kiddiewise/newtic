<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeSection extends Model
{
    public const TYPE_SLIDER = 'slider';
    public const TYPE_CATEGORIES = 'categories';
    public const TYPE_PRODUCTS = 'products';
    public const TYPE_BANNER = 'banner';

    protected $fillable = ['type', 'title', 'sort_order', 'is_active', 'settings'];
    protected $casts = ['settings' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];

    public function slides(): HasMany
    {
        return $this->hasMany(HomeSlide::class, 'home_section_id')->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'home_section_categories')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'home_section_products')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
