<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogImport extends Model
{
    protected $fillable = [
        'user_id', 'source_site', 'original_filename', 'file_path', 'status', 'total_rows',
        'processed_rows', 'created_rows', 'updated_rows', 'skipped_rows',
        'failed_rows', 'publish_products',
    ];

    protected $casts = ['publish_products' => 'boolean'];

    public function rows(): HasMany
    {
        return $this->hasMany(CatalogImportRow::class);
    }
}
