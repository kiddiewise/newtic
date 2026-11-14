<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductBadge extends Model
{
    public const COLORS = [
        'mint' => 'Pastel yeşil',
        'lilac' => 'Pastel mor',
        'rose' => 'Pastel kırmızı',
        'lemon' => 'Pastel sarı',
        'sky' => 'Pastel mavi',
        'peach' => 'Pastel şeftali',
        'aqua' => 'Pastel turkuaz',
        'pink' => 'Pastel pembe',
        'slate' => 'Pastel gri',
    ];

    protected $fillable = ['name', 'color', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'badge_product', 'product_badge_id', 'product_id');
    }
}
