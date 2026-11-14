<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PaytrSetting extends Model
{
    // The sole configuration row is ID 1, never an order/customer resource.
    protected $table = 'paytr_settings';

    protected $fillable = [
        'merchant_id', 'merchant_key', 'merchant_salt',
    ];

    protected $hidden = [
        'merchant_key', 'merchant_salt',
    ];

    protected $casts = [
        'merchant_key' => 'encrypted',
        'merchant_salt' => 'encrypted',
        'is_enabled' => 'boolean',
        'test_mode' => 'boolean',
    ];

    public function hasCredentials(): bool
    {
        // No need to decrypt the key/salt merely to display their presence.
        return filled($this->merchant_id)
            && filled($this->getRawOriginal('merchant_key'))
            && filled($this->getRawOriginal('merchant_salt'));
    }
}
