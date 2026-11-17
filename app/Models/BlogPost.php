<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    protected $fillable = [
        'blog_category_id', 'title', 'slug', 'author_name', 'excerpt',
        'featured_image_path', 'featured_image_alt', 'seo_title',
        'seo_description', 'seo_noindex', 'is_published', 'published_at', 'sources',
    ];

    protected $casts = [
        'sources' => 'array', 'seo_noindex' => 'boolean',
        'is_published' => 'boolean', 'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(BlogBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('blog_category_id')
                ->orWhereHas('category', fn (Builder $cat) => $cat->where('is_active', true)));
    }
}
