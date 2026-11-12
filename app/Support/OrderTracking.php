<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PaymentNotification;

/**
 * Customer-facing order tracking. Never infer fulfillment or bank approval
 * from the mere existence of an order or an unverified customer notification.
 */
final class OrderTracking
{
    public static function for(Order $order, ?PaymentNotification $latestNotice): array
    {
        $pendingNotice = $order->status === 'pending_payment' && $latestNotice?->status === 'pending';
        $rejectedNotice = $order->status === 'pending_payment' && $latestNotice?->status === 'rejected';

        $state = match (true) {
            $order->status === 'cancelled' => [
                'key' => 'cancelled', 'icon' => 'x', 'title' => 'Siparişiniz iptal edildi',
                'description' => 'Bu siparişin işlem süreci sonlandırıldı. Yeni bir sipariş oluşturabilirsiniz.',
                'badge' => 'İptal edildi', 'progress' => 0, 'payment' => 'Sipariş iptal edildi',
            ],
            $order->status === 'completed' => [
                'key' => 'completed', 'icon' => 'thumb', 'title' => 'Siparişiniz tamamlandı!',
                'description' => 'Siparişiniz başarıyla tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.',
                'badge' => 'Tamamlandı', 'progress' => 4, 'payment' => 'Ödeme doğrulandı',
            ],
            $order->status === 'out_for_delivery' => [
                'key' => 'out_for_delivery', 'icon' => 'truck', 'title' => 'Siparişiniz dağıtıma çıktı!',
                'description' => 'Kargo firması siparişinizi teslimat için dağıtıma çıkardı. Teslimat durumunu takip edebilirsiniz.',
                'badge' => 'Dağıtımda', 'progress' => 3, 'payment' => 'Ödeme doğrulandı',
            ],
            $order->status === 'shipped' => [
                'key' => 'shipped', 'icon' => 'truck', 'title' => 'Siparişiniz yola çıktı!',
                'description' => 'Paketiniz kargoya verildi. Kargo bilgilerini aşağıda inceleyebilirsiniz.',
                'badge' => 'Kargoda', 'progress' => 3, 'payment' => 'Ödeme doğrulandı',
            ],
            $order->status === 'preparing' => [
                'key' => 'preparing', 'icon' => 'package', 'title' => 'Siparişiniz hazırlanıyor!',
                'description' => 'Ödeme onaylandı; ürünleriniz hazırlanıyor. Kargoya verildiğinde durumunuz güncellenecek.',
                'badge' => 'Hazırlanıyor', 'progress' => 2, 'payment' => 'Ödeme doğrulandı',
            ],
            $pendingNotice => [
                'key' => 'payment_review', 'icon' => 'review', 'title' => 'Ödeme bildiriminiz alındı',
                'description' => 'Banka hareketleri kontrol ediliyor. Onaylanana kadar ödeme bekleniyor durumundadır.',
                'badge' => 'Ödeme kontrol ediliyor', 'progress' => 1, 'payment' => 'Bildirim kontrol ediliyor',
            ],
            $rejectedNotice => [
                'key' => 'payment_rejected', 'icon' => 'alert', 'title' => 'Ödeme bildiriminiz doğrulanamadı',
                'description' => 'Ödeme bilgilerinizi kontrol ederek yeniden bildirim yapabilirsiniz. Siparişiniz ödeme bekliyor.',
                'badge' => 'Ödeme bekleniyor', 'progress' => 1, 'payment' => 'Bildirim doğrulanamadı',
            ],
            $order->status === 'pending_payment' && $order->payment_method === 'paytr' => [
                'key' => 'paytr_pending', 'icon' => 'review', 'title' => 'Kart ödemeniz kontrol ediliyor',
                'description' => 'PAYTR test ödemesinin kesin sonucu bekleniyor. Yalnızca doğrulanmış sunucu bildirimi sonrasında siparişiniz hazırlanır.',
                'badge' => 'Ödeme doğrulanıyor', 'progress' => 1, 'payment' => 'Kart ödemesi bekleniyor',
            ],
            $order->status === 'pending_payment' => [
                'key' => 'pending_payment', 'icon' => 'party', 'title' => 'Siparişiniz oluşturuldu!',
                'description' => 'Siparişinizi aldık. Havale/EFT ödemenizi tamamlayıp bildirim yaparak süreci başlatabilirsiniz.',
                'badge' => 'Ödeme bekleniyor', 'progress' => 1, 'payment' => 'Ödeme bekleniyor',
            ],
            default => [
                'key' => 'unknown', 'icon' => 'clock', 'title' => 'Sipariş durumunuz kontrol ediliyor',
                'description' => 'Güncel bilgiler için bu sayfayı daha sonra tekrar kontrol edebilirsiniz.',
                'badge' => 'Kontrol ediliyor', 'progress' => 0, 'payment' => 'Bilgi bekleniyor',
            ],
        };

        // Shipping is a separate state machine. Customer-facing labels always
        // reflect the latest signed provider event, not the coarse order status.
        $shipment = $order->shippingShipment;
        if ($shipment && $order->status !== 'pending_payment' && $order->status !== 'cancelled') {
            $carrierStatus = $shipment->remote_status;
            if (ShippingStatus::isException($carrierStatus)) {
                $state = match ($carrierStatus) {
                    'on_return' => [
                        'key' => 'shipping_return', 'icon' => 'truck',
                        'title' => 'Kargonuz iade sürecinde',
                        'description' => ShippingStatus::description($carrierStatus),
                        'badge' => ShippingStatus::label($carrierStatus),
                        'progress' => 3, 'payment' => 'Ödeme doğrulandı',
                    ],
                    'returned' => [
                        'key' => 'shipping_returned', 'icon' => 'alert',
                        'title' => 'Kargonuz göndericiye iade edildi',
                        'description' => ShippingStatus::description($carrierStatus),
                        'badge' => ShippingStatus::label($carrierStatus),
                        'progress' => 3, 'payment' => 'Ödeme doğrulandı',
                    ],
                    default => [
                        'key' => 'shipping_issue', 'icon' => 'alert',
                        'title' => 'Kargo gönderinizle ilgili işlem gerekiyor',
                        'description' => ShippingStatus::description($carrierStatus),
                        'badge' => ShippingStatus::label($carrierStatus),
                        'progress' => 2, 'payment' => 'Ödeme doğrulandı',
                    ],
                };
            } elseif (ShippingStatus::valid((string)$carrierStatus)) {
                $state['description'] = ShippingStatus::description($carrierStatus);
                $state['badge'] = ShippingStatus::label($carrierStatus);
                if ($carrierStatus === 'at_delivery_center') {
                    $state['title'] = 'Kargonuz dağıtım merkezinde!';
                } elseif ($carrierStatus === 'on_transit') {
                    $state['title'] = 'Kargonuz taşıma sürecinde!';
                }
            }
        }

        $stages = [
            ['name' => 'Sipariş alındı', 'icon' => 'check'],
            ['name' => 'Ödeme onayı', 'icon' => 'landmark'],
            ['name' => 'Hazırlanıyor', 'icon' => 'package'],
            ['name' => 'Kargoda', 'icon' => 'truck'],
            ['name' => 'Tamamlandı', 'icon' => 'check'],
        ];
        foreach ($stages as $index => &$stage) {
            $stage['state'] = $state['key'] === 'cancelled'
                ? ($index === 0 ? 'done' : 'stopped')
                : ($index < $state['progress'] ? 'done' : ($index === $state['progress'] ? 'current' : 'upcoming'));
        }
        unset($stage);

        return $state + [
            'stages' => $stages,
            'shipment_status' => $shipment?->remote_status,
            'shipment_status_label' => ShippingStatus::label($shipment?->remote_status),
            'shipment_status_description' => ShippingStatus::description($shipment?->remote_status),
        ];
    }
}
