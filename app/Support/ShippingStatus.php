<?php

namespace App\Support;

/**
 * Kargo Entegratör shipping states. These are shipping facts, not payment states.
 * Never display a raw provider identifier to a customer or admin.
 */
final class ShippingStatus
{
    public const LABELS = [
        'draft' => 'Taslak',
        'non_processed' => 'Gönderime Hazır',
        'shipped' => 'Kargoya Verildi',
        'on_transit' => 'Taşıma Sürecinde',
        'at_delivery_center' => 'Dağıtım Merkezinde',
        'in_courier' => 'Dağıtıma Çıktı',
        'delivered' => 'Teslim Edildi',
        'on_return' => 'İade Sürecinde',
        'returned' => 'İade Edildi',
        'canceled' => 'Kargo Gönderisi İptal Edildi',
        'failed' => 'Kargo İşleminde Sorun',
    ];

    public const DESCRIPTIONS = [
        'draft' => 'Gönderi kaydı henüz tamamlanmadı.',
        'non_processed' => 'Gönderiniz hazırlandı; kargo firmasına henüz teslim edilmedi.',
        'shipped' => 'Paketiniz kargo firması tarafından teslim alındı.',
        'on_transit' => 'Paketiniz kargo firmasının taşıma ağında ilerliyor.',
        'at_delivery_center' => 'Paketiniz teslimat bölgesindeki dağıtım merkezine ulaştı.',
        'in_courier' => 'Paketiniz teslimat için dağıtım görevlisine verildi.',
        'delivered' => 'Paketiniz teslim edildi.',
        'on_return' => 'Paketinizin göndericiye iade süreci başladı.',
        'returned' => 'Paketiniz göndericiye iade edildi. Ödeme iadesi bu durumdan bağımsızdır.',
        'canceled' => 'Kargo gönderisi iptal edildi. Siparişinizin ödeme veya iade durumunu değiştirmez.',
        'failed' => 'Kargo işlemi tamamlanamadı. Destek için mağazayla iletişime geçebilirsiniz.',
    ];

    public const EXCEPTIONS = ['on_return', 'returned', 'canceled', 'failed'];

    public static function valid(string $status): bool
    {
        return isset(self::LABELS[$status]);
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? 'Kargo durumu bekleniyor';
    }

    public static function description(?string $status): string
    {
        return self::DESCRIPTIONS[$status ?? ''] ?? 'Kargo firması durum bilgisi bekleniyor.';
    }

    public static function isException(?string $status): bool
    {
        return in_array($status, self::EXCEPTIONS, true);
    }

    /**
     * The legacy order state is coarse. Full carrier status always stays in
     * shipping_shipments.remote_status and shipping_status_events.
     *
     * A newer signed carrier update can correct a previous delivery stage.
     * "canceled" here is a CARGO cancellation, not a payment/order refund.
     */
    public static function coarseOrderStatus(string $status): ?string
    {
        return match ($status) {
            'draft', 'non_processed' => 'preparing',
            'shipped', 'on_transit', 'at_delivery_center' => 'shipped',
            'in_courier' => 'out_for_delivery',
            'delivered' => 'completed',
            default => null,
        };
    }
}
