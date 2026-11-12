<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Models\ShippingWarehouse;
use App\Services\KargoIntegratorClient;
use App\Services\ShippingShipmentManager;
use App\Support\ShippingTestAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShippingShipmentController extends Controller
{
    /**
     * First release: ONLY a verified Dummy Kargo account with a TEST order.
     * No DHL or other real-courier API writes are possible.
     */
    public function create(Request $request, Order $order, KargoIntegratorClient $client): RedirectResponse
    {
        abort_unless(ShippingTestAccess::allowed(), 403);
        $data = $request->validate([
            'carrier_id' => ['required','integer','min:1'],
            'warehouse_id' => ['required','integer','min:1'],
            'desi' => ['required','numeric','gt:0','max:99999','decimal:0,2'],
            'kg' => ['nullable','numeric','gt:0','max:99999','decimal:0,2'],
            'payment_type' => ['required','in:cash_money,credit_card'],
        ]);

        $provider = ShippingProvider::where('provider', 'kargoentegrator')->first();
        if (!$provider?->is_enabled || !$provider->hasKey()) {
            throw ValidationException::withMessages(['shipping' => 'Kargo Entegratör test modunu önce etkinleştirin.']);
        }
        $carrier = ShippingCarrier::where('shipping_provider_id', $provider->id)
            ->whereKey($data['carrier_id'])->where('is_dummy', true)->first();
        $warehouse = ShippingWarehouse::where('shipping_provider_id', $provider->id)
            ->whereKey($data['warehouse_id'])->first();
        if (!$carrier || !$warehouse) {
            throw ValidationException::withMessages(['shipping' => 'Yalnızca doğrulanmış Dummy Kargo hesabı ve kayıtlı depo kullanılabilir.']);
        }

        // Avoid unnecessary API GETs for unpaid orders or duplicate attempts.
        // Repeat under a row lock before the actual POST to avoid races.
        if (!$order->is_test || $order->status !== 'preparing') {
            throw ValidationException::withMessages(['shipping' => 'Ödemesi doğrulanmamış test siparişi kargoya verilemez.']);
        }
        if ($order->shippingShipment()->exists()) {
            throw ValidationException::withMessages(['shipping' => 'Siparişin kargo işlemi zaten bulunuyor. Tekrarlama engellendi.']);
        }

        // Re-validate the remote carrier kind before any API POST.
        // A name typed by an administrator is not sufficient to authorize
        // a potentially chargeable real DHL/Aras/Yurtiçi shipment.
        try {
            $verified = collect($client->carriers($provider))->contains(
                fn ($item) => KargoIntegratorClient::accountId($item) === (int)$carrier->remote_id
                    && KargoIntegratorClient::carrierCode($item) === 'dummy'
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages(['shipping' => 'Dummy Kargo hesabı uzaktan doğrulanamadı. İşlem başlatılmadı.']);
        }
        if (!$verified) {
            throw ValidationException::withMessages(['shipping' => 'Seçilen hesap sağlayıcıda Dummy Kargo olarak doğrulanmadı.']);
        }

        $reserved = DB::transaction(function () use ($order, $provider, $carrier, $warehouse, $data): ShippingShipment {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$locked->is_test || $locked->status !== 'preparing') {
                throw ValidationException::withMessages(['shipping' => 'Sadece ödemesi doğrulanmış, hazırlanıyor durumundaki test siparişleri gönderilebilir.']);
            }
            if ($locked->shippingShipment()->exists()) {
                throw ValidationException::withMessages(['shipping' => 'Bu sipariş için zaten bir kargo işlemi bulunuyor. Tekrar barkod oluşturulamaz.']);
            }
            if (!$locked->items()->exists()) {
                throw ValidationException::withMessages(['shipping' => 'Siparişte ürün bulunmuyor.']);
            }
            return ShippingShipment::create([
                'order_id' => $locked->id,
                'shipping_provider_id' => $provider->id,
                'shipping_carrier_id' => $carrier->id,
                'shipping_warehouse_id' => $warehouse->id,
                'state' => 'creating',
                'desi' => $data['desi'],
                'kg' => $data['kg'] ?? null,
            ]);
        }, 3);

        try {
            $order->load('items');
            $words = preg_split('/\s+/u', trim($order->customer_name)) ?: [];
            if (count($words) < 2) {
                throw new \RuntimeException('Alıcı adı ve soyadı ayrı belirtilmelidir.');
            }
            $surname = array_pop($words);
            $digits = preg_replace('/\D+/', '', $order->phone);
            if (!preg_match('/^0?5\d{9}$/D', $digits)) {
                throw new \RuntimeException('Gönderi için geçerli bir cep telefonu numarası gerekli.');
            }
            $address = trim($order->street_address);
            if (mb_strlen($address) > 255) {
                throw new \RuntimeException('Alıcı açık adresi en fazla 255 karakter olmalıdır.');
            }
            $customer = [
                'name' => implode(' ', $words),
                'surname' => $surname,
                'phone' => preg_replace('/^0/', '', $digits),
                'email' => $order->customer_email,
                'country' => 'TÜRKİYE',
                'country_code' => 'TR',
                'city' => $order->city,
                'district' => $order->district,
                'address' => $address,
            ];
            $lines = $order->items->map(static fn($item) => [
                'title' => mb_substr($item->product_name, 0, 255),
                'quantity' => (int)$item->quantity,
                'sku' => mb_substr($item->sku, 0, 255),
                'platform_id' => (string)$item->id,
            ])->all();
            $payload = [
                'cargo_integration_id' => (int)$carrier->remote_id,
                'warehouse_id' => (int)$warehouse->remote_id,
                'customer' => $customer,
                'payment_type' => $data['payment_type'],
                'package_type' => 'box',
                'payor_type' => 'sender',
                'is_pay_at_door' => false,
                'desi' => (float)$data['desi'],
                'platform' => 'NEWTIC',
                'platform_id' => (string)$order->id,
                'platform_d_id' => $order->order_number,
                'notification_url' => route('shipping.callback'),
                'lines' => $lines,
            ];
            if (!empty($data['kg'])) $payload['kg'] = (float)$data['kg'];

            $remote = $client->create($provider, $payload);
            DB::transaction(function () use ($reserved, $remote) {
                $shipment = ShippingShipment::whereKey($reserved->id)->lockForUpdate()->firstOrFail();
                $shipment->update([
                    'remote_id' => (int)$remote['id'],
                    'state' => 'submitted',
                    'remote_status' => (string)($remote['status'] ?? 'non_processed'),
                    'barcode' => isset($remote['barcode']) ? mb_substr((string)$remote['barcode'], 0, 255) : null,
                ]);
            }, 3);
            $reserved->events()->create([
                'status' => $reserved->fresh()->remote_status ?: 'non_processed',
                'source' => 'created', 'created_at' => now(),
            ]);
            return redirect()->route('admin.orders.show', $order)
                ->with('success','Dummy Kargo test barkodu oluşturuldu. Paket kargo firmasına teslim edilmiş sayılmaz.');
        } catch (\Throwable) {
            // Do not retry an ambiguous HTTP POST automatically. The provider may
            // have created the consignment even if our connection timed out.
            $reserved->update(['state' => 'uncertain']);
            return redirect()->route('admin.orders.show', $order)
                ->withErrors(['shipping' => 'Kargo sonucu doğrulanamadı. Sağlayıcı panelinden sipariş numarasıyla kontrol edin; ikinci kez oluşturmayın.']);
        }
    }

    public function refresh(Order $order, KargoIntegratorClient $client, ShippingShipmentManager $manager): RedirectResponse
    {
        abort_unless(ShippingTestAccess::allowed(), 403);
        $shipment = $order->shippingShipment;
        if (!$shipment || $shipment->state !== 'submitted' || !$shipment->remote_id) {
            throw ValidationException::withMessages(['shipping' => 'Sorgulanabilecek kayıtlı bir gönderi bulunamadı.']);
        }
        try {
            $remote = $client->shipment($shipment->provider, $shipment->remote_id);
            $manager->syncDetails($shipment, $remote);
        } catch (\Throwable) {
            return back()->withErrors(['shipping' => 'Kargo durumu alınamadı. Daha sonra tekrar deneyin.']);
        }
        return back()->with('success', 'Dummy Kargo gönderi durumu güncellendi.');
    }

    public function label(Order $order, KargoIntegratorClient $client): Response
    {
        abort_unless(ShippingTestAccess::allowed(), 403);
        $shipment = $order->shippingShipment;
        abort_unless($shipment && $shipment->state === 'submitted'
            && $shipment->remote_id && $shipment->carrier->is_dummy, 404);
        try {
            $pdf = $client->label($shipment->provider, $shipment->remote_id);
        } catch (\Throwable) {
            abort(502, 'Kargo etiketi alınamadı.');
        }
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="newtic-kargo-'.(int)$order->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
