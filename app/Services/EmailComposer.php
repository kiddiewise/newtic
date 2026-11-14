<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\EmailDelivery;
use App\Models\Order;
use App\Models\ProductReview;
use App\Support\ShippingStatus;
use App\Support\StoreIdentity;
use Illuminate\Support\Facades\URL;

final class EmailComposer
{
    /** @return array{subject:string,heading:string,body:string,lines:array,action:?array,brand:string,note:string} | null */
    public function build(EmailDelivery $delivery): ?array
    {
        $order = $delivery->order;
        $user = $delivery->user;
        $event = $delivery->event;
        $action = null;
        $lines = [];
        $productsForReview = [];
        $brand = StoreIdentity::name();
        $note = 'Bu e-posta siparişiniz veya hesabınız hakkında bilgilendirme amacıyla gönderilmiştir. Şifrenizi ve ödeme bilgilerinizi e-posta yoluyla paylaşmayın.';
        $orderUrl = $order ? ($order->user_id
            ? route('account.index')
            : url(URL::temporarySignedRoute('checkout.success', now()->addDays(14), ['order' => $order->id], absolute: false)))
            : null;

        if ($order) {
            $lines[] = 'Sipariş: '.$order->order_number;
            $lines[] = 'Tutar: '.number_format($order->total_cents / 100, 2, ',', '.').' TL';
        }

        $subject = $heading = $body = '';
        switch ($event) {
            case 'customer_order_received':
                if (!$order) return null;
                $subject = 'Siparişiniz alındı · '.$order->order_number;
                $heading = 'Siparişiniz başarıyla oluşturuldu';
                $body = $order->payment_method === 'bank_transfer'
                    ? 'Havale/EFT ödemeniz bekleniyor. Lütfen sipariş numaranızla ödemenizi tamamlayın; ödeme onaylanmadan siparişiniz hazırlanmaz.'
                    : 'Siparişinizi aldık. Kartla ödeme doğrulaması tamamlandıktan sonra hazırlık sürecine geçeceğiz.';
                foreach ($order->items()->orderBy('id')->get() as $item) {
                    $lines[] = $item->product_name.($item->option_value ? ' ('.$item->option_value.')' : '').' × '.$item->quantity;
                }
                if ($order->payment_method === 'bank_transfer') {
                    foreach (BankAccount::query()->where('is_active', true)->orderBy('sort_order')->limit(4)->get() as $bank) {
                        $lines[] = $bank->bank_name.' · '.$bank->account_holder.' · '.$bank->iban;
                    }
                }
                $action = ['title' => 'Siparişimi görüntüle', 'url' => $orderUrl];
                break;
            case 'admin_new_order':
                if (!$order) return null;
                $subject = 'Yeni sipariş · '.$order->order_number;
                $heading = 'Yeni sipariş alındı';
                $body = 'Siparişin ödeme ve hazırlık durumunu kontrol edin.';
                $action = ['title' => 'Siparişi yönet', 'url' => route('admin.orders.show', $order)];
                break;
            case 'customer_payment_notice':
                if (!$order || !$order->paymentNotifications()->whereKey($delivery->reference_id)
                    ->where('status', 'pending')->exists()) return null;
                $subject = 'Ödeme bildiriminiz alındı · '.$order->order_number;
                $heading = 'Bildiriminiz kontrol sırasında';
                $body = 'Havale/EFT bildiriminizi aldık. Banka hareketleri kontrol edildikten sonra ödeme durumunuz güncellenecektir.';
                $action = ['title' => 'Sipariş durumunu gör', 'url' => $orderUrl];
                break;
            case 'admin_payment_notice':
                if (!$order || !$order->paymentNotifications()->whereKey($delivery->reference_id)->where('status', 'pending')->exists()) return null;
                $subject = 'Ödeme kontrolü bekliyor · '.$order->order_number;
                $heading = 'Havale bildirimi incelenmeli';
                $body = 'Müşteri ödeme yaptığını bildirdi. Banka hareketlerini doğrulamadan ödemeyi onaylamayın.';
                $action = ['title' => 'Ödemeyi incele', 'url' => route('admin.orders.show', $order)];
                break;
            case 'customer_payment_verified':
                if (!$order || !in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true)) return null;
                $subject = 'Ödemeniz onaylandı · '.$order->order_number;
                $heading = 'Ödemeniz doğrulandı';
                $body = 'Ödemeniz onaylandı. Siparişinizin hazırlanmasına geçildi.';
                $action = ['title' => 'Siparişimi görüntüle', 'url' => $orderUrl];
                break;
            case 'customer_payment_rejected':
                if (!$order || !$order->paymentNotifications()->whereKey($delivery->reference_id)->where('status', 'rejected')->exists()) return null;
                $subject = 'Ödeme bildiriminiz doğrulanamadı · '.$order->order_number;
                $heading = 'Ödeme bildirimini kontrol edin';
                $body = 'Banka kontrolünde bildiriminiz doğrulanamadı. Sipariş sayfanızdan bilgileri kontrol edip tekrar bildirim yapabilirsiniz.';
                $action = ['title' => 'Siparişi kontrol et', 'url' => $orderUrl];
                break;
            case 'customer_order_shipped':
                if (!$order || !in_array($order->status, ['shipped','out_for_delivery','completed'], true)
                    || $order->shippingShipment && ShippingStatus::isException($order->shippingShipment->remote_status)) return null;
                $subject = 'Siparişiniz kargoya verildi · '.$order->order_number;
                $heading = 'Paketiniz yola çıktı';
                $body = 'Siparişiniz kargoya verildi. Güncel taşıma durumunu sipariş sayfanızdan takip edebilirsiniz.';
                if ($order->carrier_name) $lines[] = 'Kargo firması: '.$order->carrier_name;
                if ($order->tracking_number) $lines[] = 'Takip numarası: '.$order->tracking_number;
                $action = ['title' => 'Kargomu takip et', 'url' => $orderUrl];
                break;
            case 'customer_out_for_delivery':
                if (!$order || !in_array($order->status, ['out_for_delivery','completed'], true)
                    || $order->shippingShipment && $order->shippingShipment->remote_status !== 'in_courier'
                        && $order->shippingShipment->remote_status !== 'delivered') return null;
                $subject = 'Paketiniz dağıtıma çıktı · '.$order->order_number;
                $heading = 'Siparişiniz dağıtımda';
                $body = 'Kargo firmanız gönderinizi teslimat için dağıtıma çıkardı.';
                $action = ['title' => 'Teslimat durumunu gör', 'url' => $orderUrl];
                break;
            case 'customer_delivered':
                if (!$order || $order->status !== 'completed'
                    || $order->shippingShipment && $order->shippingShipment->remote_status !== 'delivered') return null;
                $subject = 'Siparişiniz teslim edildi · '.$order->order_number;
                $heading = 'Siparişiniz teslim edildi';
                $body = 'Siparişinizin teslimat süreci tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.';
                $action = ['title' => 'Siparişimi görüntüle', 'url' => $orderUrl];
                break;
            case 'customer_payment_failed':
                if (!$order || $order->status !== 'cancelled' || $order->payment_method !== 'paytr') return null;
                $subject = 'Kart ödemeniz tamamlanamadı · '.$order->order_number;
                $heading = 'Kart ödemeniz başarısız oldu';
                $body = 'Kartlı ödeme işleminiz tamamlanamadığı için bu sipariş kapatıldı. Tahsilat yapılmış olarak işaretlenmedi. Yeni bir sipariş oluşturabilirsiniz.';
                $action = ['title' => 'Mağazaya dön', 'url' => route('home')];
                break;
            case 'customer_cancelled':
                if (!$order || $order->status !== 'cancelled') return null;
                $subject = 'Siparişiniz iptal edildi · '.$order->order_number;
                $heading = 'Siparişiniz iptal edildi';
                $body = 'Siparişinizin durumu iptal edildi olarak güncellendi. Ödeme/iade bilgileri için mağazamızla iletişime geçebilirsiniz.';
                $action = ['title' => 'Siparişimi görüntüle', 'url' => $orderUrl];
                break;
            case 'admin_shipping_exception':
                if (!$order || !$order->shippingShipment
                    || !ShippingStatus::isException($order->shippingShipment->remote_status)) return null;
                $subject = 'Kargo işlemi gerekiyor · '.$order->order_number;
                $heading = 'Kargo durumunu inceleyin';
                $body = 'Kargo Entegratör sorun/iade durumu bildirdi: '
                    .ShippingStatus::label($order->shippingShipment->remote_status).'.';
                $action = ['title' => 'Kargo kaydını aç', 'url' => route('admin.orders.show', $order)];
                break;
            case 'admin_review_pending':
                $review = ProductReview::query()->find($delivery->reference_id);
                if (!$review || $review->status !== 'pending') return null;
                $subject = 'Ürün yorumu onay bekliyor';
                $heading = 'Yeni ürün değerlendirmesi';
                $body = 'Bir müşteri satın aldığı ürünü değerlendirdi. Yorumu inceleyerek yayınlayabilirsiniz.';
                $lines[] = 'Ürün: '.($review->product?->name ?? 'Ürün');
                $action = ['title' => 'Yorumları incele', 'url' => route('admin.reviews.index')];
                break;
            case 'customer_review_invite':
                if (!$order || !$order->user_id || $order->status !== 'completed'
                    || $order->user?->is_admin || !$order->shippingShipment
                    || $order->shippingShipment->remote_status !== 'delivered') return null;
                $products = $order->items()->with('product')->get()
                    ->pluck('product')->filter(fn ($p) => $p && $p->is_active)->unique('id')
                    ->filter(fn ($p) => !ProductReview::query()->where('user_id', $order->user_id)
                        ->where('product_id', $p->id)->exists())->values();
                if ($products->isEmpty()) return null;
                $subject = 'Satın aldığınız ürünleri değerlendirmek ister misiniz?';
                $heading = 'Ürünlerinizi nasıl buldunuz?';
                $body = 'Ürünlerinizi teslim almanızın üzerinden birkaç gün geçti. Deneyiminizi diğer alıcılarla paylaşmak ister misiniz?';
                foreach ($products->take(5) as $product) {
                    $lines[] = $product->name;
                    $productsForReview[] = [
                        'name' => $product->name,
                        'url' => route('product.show', $product->slug).'#urun-yorumlari',
                        'image' => $product->image_path ? asset($product->image_path) : null,
                    ];
                }
                $action = ['title' => 'İlk ürünü değerlendir',
                    'url' => route('product.show', $products->first()->slug).'#urun-yorumlari'];
                $note = 'Bu mesaj yalnızca satın alma deneyimiyle ilgili tek seferlik bir değerlendirme davetidir. Kampanya veya ürün tanıtımı içermez.';
                break;
            case 'customer_review_approved':
                $review = ProductReview::query()->find($delivery->reference_id);
                if (!$review || $review->status !== 'approved' || !$review->product) return null;
                $subject = 'Ürün yorumunuz yayınlandı';
                $heading = 'Değerlendirmeniz için teşekkürler';
                $body = 'Ürün yorumunuz incelendi ve yayınlandı.';
                $lines[] = 'Ürün: '.$review->product->name;
                $action = ['title' => 'Yorumunuzu görüntüle',
                    'url' => route('product.show', $review->product->slug).'#urun-yorumlari'];
                break;
            case 'customer_email_verification':
                if (!$user || $user->is_admin || $user->email_verified_at) return null;
                $subject = 'E-posta adresinizi doğrulayın · '.$brand;
                $heading = 'E-posta adresinizi doğrulayın';
                $body = 'Hesabınız için e-posta adresinizi doğrulamak üzere aşağıdaki güvenli bağlantıyı kullanın. Bağlantı 60 dakika geçerlidir.';
                $action = ['title'=>'E-posta adresimi doğrula', 'url'=>url(
                    URL::temporarySignedRoute('account.email.verify', now()->addMinutes(60),
                    ['id'=>$user->id,'hash'=>sha1(strtolower($user->email))], absolute: false)
                )];
                break;
            case 'customer_password_reset':
                if (!$user || $user->is_admin || !$delivery->secret_payload
                    || $delivery->created_at?->lt(now()->subMinutes(55))) return null;
                $subject = 'Şifrenizi yenileyin · '.$brand;
                $heading = 'Şifre yenileme isteğiniz';
                $body = 'Hesabınız için şifre yenileme talebi aldık. Bu işlemi siz başlatmadıysanız mesajı görmezden gelebilirsiniz. Bağlantı 60 dakika geçerlidir.';
                $action = ['title'=>'Yeni şifre belirle', 'url'=>route('account.password.reset', [
                    'token'=>$delivery->secret_payload, 'email'=>$user->email,
                ])];
                $note = 'Şifre sıfırlama bağlantısını kimseyle paylaşmayın. Bu e-postaya yanıt vererek şifre değiştiremezsiniz.';
                break;
            case 'customer_welcome':
                if (!$user || $user->is_admin) return null;
                $subject = 'Hoş geldiniz · '.$brand;
                $heading = 'Mağazamıza hoş geldiniz';
                $body = 'Hesabınız oluşturuldu. Siparişlerinizi, kayıtlı adreslerinizi ve favori ürünlerinizi hesabınızdan yönetebilirsiniz.';
                $action = ['title' => 'Hesabıma git', 'url' => route('account.index')];
                break;
            default:
                return null;
        }
        return compact('subject','heading','body','lines','action','brand','note')
            + ['productsForReview'=>$productsForReview];
    }
}
