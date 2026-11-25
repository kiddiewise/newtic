<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Storage;
use App\Models\EmailDelivery;
use App\Console\Commands\ExpireUnpaidBankOrders;
use Illuminate\Support\Facades\Artisan;
use App\Models\OrderStatusChange;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\ShippingProvider;
use App\Support\ShippingTestAccess;
use App\Support\OrderPaymentBreakdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Photos are private order documents, never exposed under public/storage. */
    public function customerPhoto(Order $order, OrderItem $item, int $index): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($item->order_id === $order->id, 404);
        $photos = $item->customer_photos ?? [];
        $photo = $photos[$index] ?? null;
        $path = is_array($photo) ? ($photo['path'] ?? null) : null;
        $prefix = 'customer-photos/orders/'.$order->id.'/'.$item->id.'/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp', 'avif'=>'image/avif'];
        abort_unless(isset($types[$extension]) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $types[$extension],
            'Content-Disposition' => 'inline; filename="musteri-fotografi-'.($index+1).'.'.$extension.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private const CORRECTABLE_STATES = ['preparing','shipped','out_for_delivery','completed'];

    private const TRANSITIONS = [
        'pending_payment' => ['cancelled'], // Preparing requires bank verification via payment inbox.
        'preparing' => ['shipped', 'cancelled'],
        'shipped' => ['out_for_delivery', 'completed'],
        'out_for_delivery' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function expireUnpaid(Request $request): RedirectResponse
    {
        // Protected admin-only fallback when the host has no scheduler cron.
        $exitCode = Artisan::call('orders:expire-unpaid', ['--limit' => 50]);
        if ($exitCode !== 0) {
            return redirect()->route('admin.orders.index')->with('error', 'Ödenmeyen siparişler kontrol edilemedi. Sunucu kayıtlarını inceleyin.');
        }
        return redirect()->route('admin.orders.index')
            ->with('success', 'Süresi dolmuş havale siparişleri kontrol edildi. Ödeme bildirimi bekleyen siparişler korunur.');
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        abort_if($status !== '' && ! array_key_exists($status, self::TRANSITIONS), 422);
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);
        $payment = (string)$request->query('payment', '');
        abort_if($payment !== '' && !in_array($payment, ['bank_transfer','paytr'], true), 422);
        $unreadOnly = $request->boolean('unread');
        $sort = (string)$request->query('sort', 'newest');
        abort_unless(in_array($sort, ['newest','oldest','highest','lowest'], true), 422);
        $unreadQuery = \App\Support\AdminOrderNotifications::unread($request->user());
        $orders = Order::query()->with('shippingShipment')->withCount(['paymentNotifications as pending_payment_notifications_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($payment !== '', fn ($q) => $q->where('payment_method', $payment))
            ->when($unreadOnly, fn ($q) => $q->whereIn('orders.id', (clone $unreadQuery)->select('orders.id')))
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
                $safe = '%'.addcslashes($term, '%_\\').'%';
                $q->where('order_number', 'like', $safe)->orWhere('customer_name', 'like', $safe)
                    ->orWhere('customer_email', 'like', $safe)->orWhere('phone', 'like', $safe);
            }))
            ->when(in_array($sort,['highest','lowest'],true), fn ($q) => $q->orderBy('total_cents', $sort === 'highest' ? 'desc' : 'asc'))
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')->paginate(20)->withQueryString();
        $unreadIds = (clone $unreadQuery)->whereIn('orders.id',$orders->getCollection()->modelKeys())->pluck('orders.id')->flip();
        $unreadCount = (clone $unreadQuery)->count();
        return view('admin.orders.index', compact('orders', 'status', 'term','payment','unreadOnly','sort','unreadIds','unreadCount'));
    }

    public function show(Order $order): View
    {
        \App\Support\AdminOrderNotifications::markOrderSeen(request()->user(), $order);
        $emailDeliveries = EmailDelivery::query()->where('order_id', $order->id)->latest('id')->limit(15)->get();
        $order->load(['items', 'paymentNotifications.reviewer', 'paytrAttempts',
            'shippingShipment.carrier', 'shippingShipment.warehouse', 'shippingShipment.provider',
            'shippingShipment.events', 'statusChanges.user']);
        $allowed = $order->shippingShipment ? [] : (self::TRANSITIONS[$order->status] ?? []);
        $verifiedBankNotice = $order->payment_method === 'bank_transfer'
            ? $order->paymentNotifications->firstWhere('status', 'verified') : null;
        $verifiedPaytr = $order->payment_method === 'paytr'
            && $order->paytrAttempts->contains(fn ($attempt) => $attempt->status === 'success');
        $paymentVerified = (bool) $verifiedBankNotice || $verifiedPaytr;
        $paymentBreakdown = OrderPaymentBreakdown::fromOrder($order);
        $canCorrectFulfillment = !$order->shippingShipment && $paymentVerified
            && in_array($order->status, self::CORRECTABLE_STATES, true);
        $canCorrectBankPayment = $order->status === 'preparing'
            && !$order->shippingShipment && (bool)$verifiedBankNotice;

        $shippingProvider = ShippingProvider::where('provider', 'kargoentegrator')->first();
        $shippingCarriers = $shippingProvider?->carriers()->orderBy('name')->get() ?? collect();
        $shippingWarehouses = $shippingProvider?->warehouses()->orderBy('name')->get() ?? collect();
        $shippingTestAllowed = ShippingTestAccess::allowed();

        return view('admin.orders.show', compact(
            'order','allowed','shippingProvider','shippingCarriers',
            'shippingWarehouses','shippingTestAllowed', 'paymentVerified',
            'canCorrectFulfillment','canCorrectBankPayment','verifiedBankNotice','emailDeliveries','paymentBreakdown'
        ));
    }

    /**
     * Separate editable shipping metadata from status changes. A tracking URL
     * is shown to buyers only if an administrator actually provides HTTPS.
     */
    public function shipping(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'carrier_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'string', 'url', 'starts_with:https://', 'max:2000'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->shippingShipment()->exists()) {
                throw ValidationException::withMessages([
                    'shipping' => 'Bu siparişin kargo bilgileri entegratör tarafından yönetiliyor.',
                ]);
            }
            if (! in_array($locked->status, ['shipped', 'out_for_delivery', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'shipping' => 'Kargo bilgileri ancak sipariş kargoya verildiğinde girilebilir.',
                ]);
            }
            $locked->update([
                'carrier_name' => trim((string) ($data['carrier_name'] ?? '')) ?: null,
                'tracking_number' => trim((string) ($data['tracking_number'] ?? '')) ?: null,
                'tracking_url' => trim((string) ($data['tracking_url'] ?? '')) ?: null,
            ]);
        }, 3);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Kargo bilgileri kaydedildi.');
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::TRANSITIONS))],
        ]);
        DB::transaction(function () use ($order, $data, $request) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $next = $data['status'];
            if ($locked->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['status'=>'Açık iptal/iade talebi var. Önce talebi sonuçlandırın.']);
            }
            if ($locked->shippingShipment()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Entegratöre bağlı siparişin kargo aşamasını sağlayıcı yönetir. Kargo durumunu sağlayıcıdan düzeltip senkronize edin.',
                ]);
            }
            if (! in_array($next, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Bu sipariş durumu değişikliği yapılamaz.',
                ]);
            }

            if ($next === 'cancelled') {
                // PAYTR may still report a successful payment asynchronously.
                // Never release reserved stock while its signed callback is pending.
                if ($locked->payment_method === 'paytr'
                    && $locked->paytrAttempts()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'PAYTR ödeme sonucu bekleniyor. Bildirim doğrulanmadan bu sipariş iptal edilemez.',
                    ]);
                }
                // A verified bank transfer requires a separate refund procedure.
                // Never silently mark a paid order cancelled and leave funds unrefunded.
                if ($locked->payment_method === 'paytr'
                    && $locked->paytrAttempts()->where('status', 'success')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Doğrulanmış PAYTR kart işlemi iade kaydı olmadan iptal edilemez.',
                    ]);
                }
                if ($locked->paymentNotifications()->where('status', 'verified')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Ödemesi doğrulanan siparişi iptal etmek için önce iade işlemi ve kaydı gereklidir.',
                    ]);
                }
                if ($locked->paymentNotifications()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'İptalden önce bekleyen ödeme bildirimini kontrol edip sonuçlandırın.',
                    ]);
                }
                // Restore reserved stock exactly once; cancelled orders are terminal.
                foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                    if ($item->product_id) {
                        Product::query()->whereKey($item->product_id)
                            ->where('tracks_stock', true)
                            ->increment('stock_quantity', $item->quantity);
                    }
                }
            }
            $previous = $locked->status;
            $locked->update(['status' => $next]);
            OrderStatusChange::create([
                'order_id' => $locked->id, 'user_id' => $request->user()->id,
                'from_status' => $previous, 'to_status' => $next,
                'source' => 'admin_forward', 'created_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Sipariş durumu güncellendi.');
    }
    public function correct(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::CORRECTABLE_STATES)],
            'reason' => ['required', 'string', 'min:15', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['status'=>'Açık iptal/iade talebi var. Önce talebi sonuçlandırın.']);
            }
            if (!in_array($locked->status, self::CORRECTABLE_STATES, true)
                || $locked->shippingShipment()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Entegratöre bağlı, iptal edilmiş veya ödeme bekleyen sipariş manuel geri alınamaz.',
                ]);
            }
            $verified = $locked->payment_method === 'bank_transfer'
                ? $locked->paymentNotifications()->where('status', 'verified')->exists()
                : $locked->paytrAttempts()->where('status', 'success')->exists();
            if (!$verified) {
                throw ValidationException::withMessages([
                    'status' => 'Durum düzeltmek için doğrulanmış ödeme kaydı gereklidir.',
                ]);
            }
            if ($locked->status === $data['status']) {
                throw ValidationException::withMessages(['status' => 'Zaten aynı durum seçili.']);
            }

            $previous = $locked->status;
            $locked->update(['status' => $data['status']]);
            OrderStatusChange::create([
                'order_id' => $locked->id, 'user_id' => $request->user()->id,
                'from_status' => $previous, 'to_status' => $data['status'],
                'source' => 'admin_correction', 'reason' => trim($data['reason']),
                'created_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Sipariş hazırlık/teslimat aşaması gerekçeyle düzeltildi. Ödeme kaydı değişmedi.');
    }

    public function revokeBankVerification(Request $request, Order $order): RedirectResponse
    {
        // Never rewrite a PAYTR callback or assume a genuine transfer was refunded.
        $data = $request->validate([
            'reason' => ['required','string','min:15','max:500'],
            'bank_not_received' => ['required','accepted'],
        ]);
        DB::transaction(function () use ($request, $order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['payment'=>'Açık iptal/iade talebi varken ödeme kaydı değiştirilemez.']);
            }
            if ($locked->payment_method !== 'bank_transfer'
                || $locked->status !== 'preparing'
                || $locked->shippingShipment()->exists()) {
                throw ValidationException::withMessages([
                    'payment' => 'Yalnızca henüz kargo kaydı bulunmayan, hazırlanıyor durumundaki havale siparişinin hatalı onayı geri alınabilir.',
                ]);
            }
            $notice = PaymentNotification::where('order_id', $locked->id)
                ->where('status', 'verified')->lockForUpdate()->first();
            if (!$notice || $locked->paymentNotifications()->where('status', 'verified')->count() !== 1) {
                throw ValidationException::withMessages([
                    'payment' => 'Tek ve geçerli bir banka onay kaydı bulunamadı.',
                ]);
            }

            $notice->update([
                'status' => 'rejected',
                'admin_note' => 'Önceki ödeme doğrulaması geri çekildi: '.trim($data['reason']),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $locked->update(['status' => 'pending_payment']);
            OrderStatusChange::create([
                'order_id' => $locked->id, 'user_id' => $request->user()->id,
                'from_status' => 'preparing', 'to_status' => 'pending_payment',
                'source' => 'bank_verification_correction',
                'reason' => trim($data['reason']), 'created_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Hatalı banka ödeme onayı geri alındı; sipariş ödeme bekliyor. Gerçek tahsilat veya iade işlemi yapılmadı.');
    }
}
