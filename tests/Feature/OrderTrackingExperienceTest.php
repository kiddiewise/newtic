<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function customerOrder(string $status = 'pending_payment', ?User $owner = null): Order
    {
        return Order::create([
            'order_number' => 'PK-TEST-'.strtoupper(Str::random(8)),
            'checkout_token' => (string) Str::uuid(),
            'user_id' => $owner?->id,
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'buyer@example.com',
            'phone' => '05321234567',
            'address_label' => 'Ev',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi 23 Sokak No 4',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'status' => $status,
            'subtotal_cents' => 10000,
            'shipping_cents' => 7990,
            'total_cents' => 17990,
            'currency' => 'TRY',
            'is_test' => true,
            'placed_at' => now(),
        ]);
    }

    private function notification(Order $order, string $status): PaymentNotification
    {
        return PaymentNotification::create([
            'order_id' => $order->id,
            'bank_name_snapshot' => 'Test Bankası',
            'iban_snapshot' => 'TR330006100519786457841326',
            'status' => $status,
        ]);
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_fresh_order_keeps_original_party_celebration_and_requires_payment(): void
    {
        $owner = User::factory()->create();
        $order = $this->customerOrder('pending_payment', $owner);
        $this->actingAs($owner)
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Siparişiniz oluşturuldu!')
            ->assertSee('aria-label="Sipariş kutlaması">🎉</div>', false)
            ->assertSee('data-order-celebration="'.$order->id.'"', false)
            ->assertSee('Ödeme bekleniyor')
            ->assertSee('Siparişinizin yolculuğu')
            ->assertSee('Ödeme bilgilerine git')
            ->assertSee('Gerçek havale/EFT göndermeyin');
    }

    public function test_pending_bank_notice_has_review_hero_and_never_claims_payment_is_approved(): void
    {
        $owner = User::factory()->create();
        $order = $this->customerOrder('pending_payment', $owner);
        $this->notification($order, 'pending');

        $this->actingAs($owner)
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Ödeme bildiriminiz alındı')
            ->assertSee('Ödeme kontrol ediliyor')
            ->assertSee('Banka hareketleri kontrol ediliyor')
            ->assertDontSee('data-order-celebration', false)
            ->assertDontSee('Kargoyu takip et');
    }

    public function test_rejected_bank_notification_has_distinct_error_state_and_can_be_resent(): void
    {
        $owner = User::factory()->create();
        $order = $this->customerOrder('pending_payment', $owner);
        $this->notification($order, 'rejected');

        $this->actingAs($owner)
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Ödeme bildiriminiz doğrulanamadı')
            ->assertSee('Önceki bildiriminiz doğrulanamadı')
            ->assertSee('Ödeme bilgilerine git')
            ->assertDontSee('Siparişiniz hazırlanıyor!');
    }

    public function test_every_fulfillment_stage_has_a_unique_hero_and_no_new_order_celebration(): void
    {
        $owner = User::factory()->create();
        $states = [
            'preparing' => ['Siparişiniz hazırlanıyor!', 'aria-label="Paket hazırlanıyor"'],
            'shipped' => ['Siparişiniz yola çıktı!', 'aria-label="Kargo aracı"'],
            'out_for_delivery' => ['Siparişiniz dağıtıma çıktı!', 'aria-label="Kargo aracı"'],
            'completed' => ['Siparişiniz tamamlandı!', 'aria-label="Sipariş tamamlandı"'],
            'cancelled' => ['Siparişiniz iptal edildi', 'aria-label="İptal edildi"'],
        ];

        foreach ($states as $status => [$title, $iconLabel]) {
            $order = $this->customerOrder($status, $owner);
            $response = $this->actingAs($owner)
                ->get(route('checkout.success', $order))->assertOk()
                ->assertSee($title)
                ->assertSee($iconLabel, false)
                ->assertSee($order->order_number)
                ->assertDontSee('data-order-celebration', false)
                ->assertDontSee('Siparişiniz oluşturuldu!');
            if ($status === 'completed') {
                $response->assertSee('👍')->assertSee('Siparişlerime dön')
                    ->assertSee('Havale / EFT · Ödeme doğrulandı');
            }
            if ($status === 'cancelled') {
                $response->assertSee('Alışverişe devam et')
                    ->assertSee('takip adımları durduruldu');
            }
            if ($status === 'shipped' || $status === 'out_for_delivery') {
                $response->assertSee('Kargo bilgilerini görüntüle')
                    ->assertSee('Kargo takip numarası henüz paylaşılmadı');
            }
        }
    }

    public function test_shipping_link_only_appears_if_real_https_tracking_url_has_been_saved(): void
    {
        $owner = User::factory()->create();
        $order = $this->customerOrder('shipped', $owner);
        $order->update([
            'carrier_name' => 'Deneme Kargo',
            'tracking_number' => 'ABC123456',
            'tracking_url' => 'https://tracking.example.org/parcel/ABC123456',
        ]);
        $this->actingAs($owner)
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Deneme Kargo')
            ->assertSee('ABC123456')
            ->assertSee('Kargoyu takip et')
            ->assertSee('https://tracking.example.org/parcel/ABC123456')
            ->assertSee('Kargo firmasından takip et');
    }

    public function test_admin_shipping_details_can_only_be_changed_after_shipping(): void
    {
        $admin = $this->administrator();
        $order = $this->customerOrder();
        $route = route('admin.orders.shipping', $order);
        $this->actingAs($admin)
            ->patch($route, ['carrier_name' => 'Deneme Kargo'])
            ->assertSessionHasErrors('shipping');

        $order->update(['status' => 'shipped']);
        $this->patch($route, [
            'carrier_name' => 'Deneme Kargo',
            'tracking_number' => '123456',
            'tracking_url' => 'https://tracking.example.org/123456',
        ])->assertRedirect(route('admin.orders.show', $order))->assertSessionHasNoErrors();

        $this->assertSame('123456', $order->fresh()->tracking_number);
        $this->patch($route, ['tracking_url' => 'http://example.org/unsecured'])
            ->assertSessionHasErrors('tracking_url');
        $this->assertSame('https://tracking.example.org/123456', $order->fresh()->tracking_url);
    }

    public function test_only_administrators_may_update_shipping_or_distribution_status(): void
    {
        $order = $this->customerOrder('shipped');
        $this->patch(route('admin.orders.shipping', $order), [
            'tracking_number' => 'unauthorized',
        ])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->patch(route('admin.orders.shipping', $order), [
            'tracking_number' => 'unauthorized',
        ])->assertForbidden();
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'out_for_delivery',
        ])->assertForbidden();

        $this->actingAs($this->administrator());
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'out_for_delivery',
        ])->assertRedirect(route('admin.orders.show', $order))->assertSessionHasNoErrors();
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_guest_cannot_read_other_customers_order_without_signed_link(): void
    {
        $order = $this->customerOrder('completed');
        $this->get(route('checkout.success', $order))->assertNotFound();
    }
}
