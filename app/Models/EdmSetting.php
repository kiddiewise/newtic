<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EdmSetting extends Model
{
    protected $table = 'edm_settings';
    protected $fillable = ['id'];
    protected $hidden = ['username','password','revision'];
    protected $casts = [
        'username' => 'encrypted',
        'password' => 'encrypted',
        'counter_left' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function configured(): bool
    {
        return filled($this->getRawOriginal('username')) && filled($this->getRawOriginal('password'));
    }
}
