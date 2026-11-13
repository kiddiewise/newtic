<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MegaMenuSetting extends Model
{
    protected $fillable = [
        'hero_image_path', 'instagram_url', 'youtube_url', 'facebook_url',
        'x_url', 'whatsapp_number', 'whatsapp_message', 'footer_text',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp_number);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '90'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            $digits = '90'.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        $url = 'https://wa.me/'.$digits;
        if (filled($this->whatsapp_message)) {
            $url .= '?text='.rawurlencode($this->whatsapp_message);
        }

        return $url;
    }
}
