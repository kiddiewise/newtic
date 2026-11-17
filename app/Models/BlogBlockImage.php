<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogBlockImage extends Model
{
    protected $fillable = ['blog_block_id', 'path', 'alt', 'caption', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(BlogBlock::class, 'blog_block_id');
    }
}
