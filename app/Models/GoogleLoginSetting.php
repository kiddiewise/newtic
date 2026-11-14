<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GoogleLoginSetting extends Model
{
    protected $fillable = ['client_id', 'client_secret', 'enabled'];

    protected $hidden = ['client_secret'];

    protected $casts = ['client_secret' => 'encrypted', 'enabled' => 'boolean'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function configured(): bool
    {
        return filled($this->client_id) && filled($this->getRawOriginal('client_secret'));
    }

    public function available(): bool
    {
        return $this->enabled && $this->configured();
    }
}