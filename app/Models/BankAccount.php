<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = ['bank_name', 'account_holder', 'iban', 'logo_path', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }

    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim($iban)) ?? '');
    }

    public static function validTurkishIban(string $iban): bool
    {
        $iban = self::normalizeIban($iban);
        if (!preg_match('/^TR[0-9]{24}$/', $iban)) {
            return false;
        }

        // ISO 13616 mod-97 checksum, computed digit by digit without big integers.
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
