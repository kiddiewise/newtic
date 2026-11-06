<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'image_path', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'primary_category_id');
    }

    public function homeSections(): BelongsToMany
    {
        return $this->belongsToMany(HomeSection::class, 'home_section_categories')
            ->withPivot('sort_order');
    }
}
