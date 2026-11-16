<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MerchantLegalPage extends Model
{
    protected $table = 'merchant_legal_pages';

    public const LABELS = [
        'hakkimizda' => 'Hakkımızda',
        'iletisim' => 'İletişim',
        'kvkk-aydinlatma' => 'KVKK Aydınlatma Metni',
        'gizlilik-politikasi' => 'Gizlilik Politikası',
        'cerez-politikasi' => 'Çerez Politikası',
        'mesafeli-satis-sozlesmesi' => 'Mesafeli Satış Sözleşmesi',
        'on-bilgilendirme-formu' => 'Ön Bilgilendirme Formu',
        'teslimat-ve-iade' => 'Teslimat ve İade Şartları',
        'uyelik-sozlesmesi' => 'Üyelik Koşulları',
        'islem-rehberi' => 'İşlem Rehberi',
    ];

    public const CHECKOUT_DOCUMENTS = [
        'mesafeli-satis-sozlesmesi', 'on-bilgilendirme-formu',
    ];

    public const PAYTR_PAGES = [
        'hakkimizda', 'iletisim', 'gizlilik-politikasi',
        'mesafeli-satis-sozlesmesi', 'teslimat-ve-iade',
    ];

    protected $fillable = ['slug', 'title', 'body', 'version', 'is_published', 'published_at'];

    protected $attributes = ['version' => 1, 'is_published' => false];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime'];

    public function revisionHash(): string
    {
        return hash('sha256', implode('|', [
            $this->slug, (string) $this->title, (string) $this->body, (string) $this->version,
        ]));
    }

    public static function published(string $slug): ?self
    {
        if (!array_key_exists($slug, self::LABELS)) return null;

        return static::query()->where('slug', $slug)->where('is_published', true)->first();
    }

    public static function publishedLinks(): array
    {
        return static::query()->where('is_published', true)->whereIn('slug', array_keys(self::LABELS))
            ->get()->keyBy('slug')->all();
    }

    public static function checkoutDocuments(): array
    {
        return static::query()->whereIn('slug', self::CHECKOUT_DOCUMENTS)
            ->where('is_published', true)->get()->keyBy('slug')->all();
    }
}
