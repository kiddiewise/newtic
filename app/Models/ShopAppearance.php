<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAppearance extends Model
{
    protected $fillable = ['desktop_logo_path', 'mobile_logo_path', 'auth_image_paths'];

    protected $casts = ['auth_image_paths' => 'array'];

    // Built-in NEWTIC identity is shared by every installation but never
    // stored in a merchant's upload fields. Uploaded logos always win.
    public const DEFAULT_DESKTOP_LOGO = 'assets/branding/newtic-logo.webp';
    public const DEFAULT_MOBILE_LOGO = 'assets/branding/newtic-mark.webp';

    public function desktopLogo(): string
    {
        return $this->desktop_logo_path ?: ($this->mobile_logo_path ?: self::DEFAULT_DESKTOP_LOGO);
    }

    public function mobileLogo(): string
    {
        return $this->mobile_logo_path ?: ($this->desktop_logo_path ?: self::DEFAULT_MOBILE_LOGO);
    }

    public function compactLogo(): string
    {
        return $this->mobileLogo();
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
