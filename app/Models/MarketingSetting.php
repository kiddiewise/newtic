<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MarketingSetting extends Model
{
    protected $fillable = [
        'business_name', 'business_identifier', 'contact_email',
        'business_address', 'iys_service_provider_id', 'iys_checked_at', 'enabled',
    ];
    protected $casts = ['enabled'=>'boolean', 'iys_checked_at'=>'datetime'];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id'=>1]);
    }

    public function ready(): bool
    {
        return $this->enabled
            && filled($this->business_name)
            && filled($this->business_identifier)
            && filled($this->business_address)
            && filter_var($this->contact_email, FILTER_VALIDATE_EMAIL)
            && filled($this->iys_service_provider_id)
            && $this->iys_checked_at !== null;
    }
}
