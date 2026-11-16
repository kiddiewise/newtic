<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MerchantLegalProfile extends Model
{
    protected $table = 'merchant_legal_profiles';

    protected $fillable = [
        'legal_name', 'contact_email', 'contact_phone', 'kep_address',
        'mersis_number', 'tax_office', 'tax_number', 'registered_address',
        'support_hours', 'show_paytr_disclosure',
    ];

    protected $casts = ['show_paytr_disclosure' => 'boolean'];

    public static function current(): self
    {
        return static::query()->find(1) ?? new self();
    }

    public function ready(): bool
    {
        return filled($this->legal_name)
            && filled($this->registered_address)
            && filled($this->contact_email)
            && filled($this->contact_phone);
    }
}
