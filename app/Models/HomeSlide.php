<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSlide extends Model
{
    protected $fillable = [
        'home_section_id', 'desktop_image_path', 'mobile_image_path',
        'link_url', 'sort_order', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function getDesktopImageUrlAttribute(): string
    {
        return asset($this->desktop_image_path);
    }

    public function getMobileImageUrlAttribute(): string
    {
        return asset($this->mobile_image_path ?: $this->desktop_image_path);
    }
}
