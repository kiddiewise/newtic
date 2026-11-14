<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImportRow extends Model
{
    protected $fillable = [
        'catalog_import_id','row_number','woo_id','row_type','status','product_id','payload','message',
    ];
    protected $casts = ['payload' => 'array'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CatalogImport::class, 'catalog_import_id');
    }
}
