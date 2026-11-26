<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InvoicePreparation extends Model
{
    protected $fillable = ['order_id','uuid','gross_cents','shipping_rate','snapshot_hash','amounts'];
    protected $hidden = ['amounts','snapshot_hash'];
    protected $casts = ['amounts'=>'encrypted:array','gross_cents'=>'integer','shipping_rate'=>'integer'];
}
