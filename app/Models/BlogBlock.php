<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogBlock extends Model
{
    public const TYPES = ['heading', 'text', 'image', 'slider'];

    protected $fillable = ['blog_post_id', 'type', 'content', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(BlogBlockImage::class)->orderBy('sort_order')->orderBy('id');
    }
}
