<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')->orderBy('name');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function childrenRecursive(): HasMany
    {
        return $this->activeChildren()->with('childrenRecursive');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'primary_category_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(CategoryBanner::class)->orderBy('sort_order')->orderBy('id');
    }

    public function homeSections(): BelongsToMany
    {
        return $this->belongsToMany(HomeSection::class, 'home_section_categories')
            ->withPivot('sort_order');
    }
}
