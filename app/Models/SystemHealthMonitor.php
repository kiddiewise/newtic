<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SystemHealthMonitor extends Model
{
    protected $fillable = [
        'key', 'status', 'detail', 'last_checked_at',
        'last_success_at', 'last_failure_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];
}
