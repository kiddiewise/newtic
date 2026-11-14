<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImportSource extends Model
{
    protected $fillable = [
        'product_id','source_site','source_key','source_permalink','original_data','last_imported_at',
    ];
    protected $casts = ['original_data' => 'array', 'last_imported_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
