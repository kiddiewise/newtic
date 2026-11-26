<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EdmInvoiceRehearsal extends Model
{
    protected $fillable=['invoice_preparation_id','uuid','profile','state','content_sha256','routing_digest'];
    protected $hidden=['content_sha256','routing_digest'];
}
