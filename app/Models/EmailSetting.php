<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EmailSetting extends Model
{
    public const EVENTS = [
        'customer_order_received' => 'Müşteri · Sipariş alındı ve ödeme bilgileri',
        'admin_new_order' => 'Yönetici · Yeni sipariş',
        'customer_payment_notice' => 'Müşteri · Havale bildirimi alındı',
        'admin_payment_notice' => 'Yönetici · Havale kontrol bekliyor',
        'customer_payment_verified' => 'Müşteri · Ödeme onaylandı',
        'customer_payment_rejected' => 'Müşteri · Havale bildirimi reddedildi',
        'customer_order_shipped' => 'Müşteri · Kargoya verildi',
        'customer_out_for_delivery' => 'Müşteri · Dağıtıma çıktı',
        'customer_delivered' => 'Müşteri · Teslim edildi',
        'customer_cancelled' => 'Müşteri · Sipariş iptal edildi',
        'admin_shipping_exception' => 'Yönetici · Kargo sorunu / iade',
        'admin_review_pending' => 'Yönetici · İncelenecek ürün yorumu',
        'customer_review_invite' => 'Müşteri · Teslimat sonrası yorum daveti',
        'customer_review_approved' => 'Müşteri · Yorum onaylandı',
        'customer_welcome' => 'Müşteri · Üyelik hoş geldiniz',
        'customer_email_verification' => 'Müşteri · E-posta doğrulama',
        'customer_password_reset' => 'Müşteri · Şifre yenileme',
    ];

    public static function defaultEvents(): array
    {
        // Invite to write public reviews requires separate merchant approval;
        // do not enable it merely by switching on operational emails.
        return array_values(array_diff(array_keys(self::EVENTS), ['customer_review_invite']));
    }

    protected $fillable = [
        'enabled', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username',
        'smtp_password', 'from_address', 'from_name', 'reply_to',
        'admin_emails', 'test_recipient', 'review_delay_days', 'events',
        'verified_at',
    ];

    protected $hidden = ['smtp_password'];

    protected $casts = [
        'enabled' => 'boolean',
        'smtp_password' => 'encrypted',
        'events' => 'array',
        'verified_at' => 'datetime',
        'smtp_port' => 'integer',
        'review_delay_days' => 'integer',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    public function configured(): bool
    {
        return filled($this->smtp_host) && filled($this->smtp_username)
            && filled($this->getRawOriginal('smtp_password'))
            && filter_var($this->from_address, FILTER_VALIDATE_EMAIL);
    }

    public function active(): bool
    {
        return $this->enabled && $this->configured() && $this->verified_at !== null;
    }

    public function allows(string $event): bool
    {
        if (!array_key_exists($event, self::EVENTS)) return false;
        $allowed = $this->events;
        return in_array($event, is_array($allowed) ? $allowed : self::defaultEvents(), true);
    }

    public function adminRecipients(): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,;\r\n]+/', (string) $this->admin_emails) ?: []),
            fn ($email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL)
        )));
    }

    public static function previewEnvironment(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        return (bool) config('newtic.checkout_test_mode', true)
            || Str::endsWith($host, '.hostingersite.com')
            || app()->environment(['local', 'testing']);
    }

    public function safeRecipient(string $actual): ?string
    {
        if (self::previewEnvironment()) {
            return filter_var($this->test_recipient, FILTER_VALIDATE_EMAIL)
                ? strtolower(trim($this->test_recipient)) : null;
        }
        return filter_var($actual, FILTER_VALIDATE_EMAIL) ? strtolower(trim($actual)) : null;
    }
}
