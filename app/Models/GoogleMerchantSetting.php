<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GoogleMerchantSetting extends Model
{
    protected $fillable = ['client_id', 'client_secret', 'refresh_token', 'merchant_id', 'data_source_name',
        'auto_sync', 'sync_status', 'sync_cursor', 'sync_sent', 'sync_unchanged',
        'sync_removed', 'sync_errors', 'sync_total', 'last_synced_at', 'last_error'];

    protected $hidden = ['client_secret', 'refresh_token'];

    protected $casts = [
        'client_secret' => 'encrypted',
        'refresh_token' => 'encrypted',
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
        'sync_cursor' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return filled($this->client_id) && filled($this->getRawOriginal('client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && filled($this->getRawOriginal('refresh_token'));
    }

    public function isReady(): bool
    {
        return $this->isConnected() && filled($this->merchant_id) && filled($this->data_source_name);
    }
}