<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EdmSubmissionAttempt extends Model
{
    protected $guarded=['*'];
    protected $hidden=['payload_sha256','account_revision','lookup_generation','last_portal_token'];
    protected $casts=['lookup_generation'=>'integer','last_lookup_at'=>'datetime'];
}
