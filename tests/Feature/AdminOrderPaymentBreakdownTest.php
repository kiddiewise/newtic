<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Models\User;
use App\Support\OrderPaymentBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminOrderPaymentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $method = 'paytr'): Order
    {
        return Order::create([
            'order_number' => 'PK-PAY-'.Str::upper(Str::random(8)),
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Test Müşteri',
            'customer_email' => 'test@example.com',
            'phone' => '05321234567',
            'address_label' => 'Ev',
            'city' => 'İzmir',
            'district' => 'Karşıyaka',
            'street_address' => 'Örnek Mahallesi 10 Sokak No 3',
            'invoice_type' => 'individual',
            'payment_method' => $method,
            'status' => 'pending_payment',
            'subtotal_cents' => 109940,
            'shipping_cents' => 0,
            'total_cents' => 109940,
            'currency' => 'TRY',
            'is_test' => true,
            'placed_at' => now(),
        ]);
    }

    private function admin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
    }

    private function attempt(Order $order, string $status, ?int $paid = null): PaytrPaymentAttempt
    {
        return PaytrPaymentAttempt::create([
            'order_id' => $order->id,
            'merchant_oid' => 'NT'.Str::upper(Str::random(30)),
            'amount_cents' => $order->total_cents,
            'currency' => 'TRY',
            'status' => $status,
            'is_test' => true,
            'paid_total_cents' => $paid,
            'processed_at' => $status === 'success' ? now() : null,
        ]);
    }

    public function test_paytr_confirmed_additional_collection_is_displayed_without_overwriting_order_total(): void
    {
        $order = $this->order();
        $this->attempt($order, 'success', 120206);
        $order->forceFill(['status' => 'preparing'])->save();

        $breakdown = OrderPaymentBreakdown::fromOrder($order);
        $this->assertSame(109940, $breakdown['products_cents']);
        $this->assertSame(0, $breakdown['shipping_cents']);
        $this->assertSame(109940, $breakdown['order_total_cents']);
        $this->assertSame(10266, $breakdown['provider_difference_cents']);
        $this->assertSame(120206, $breakdown['paid_cents']);

        $this->admin();
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['newtic-detail-overview','id="order-manage"','id="order-items"','id="order-payment"'],false)
            ->assertSee('Ödeme toplamı')
            ->assertSee('Ürünlerin toplamı')
            ->assertSee('Vade farkı / PAYTR ödeme farkı')
            ->assertSee('102,66')
            ->assertSee('Sipariş tutarı')
            ->assertSee('1.099,40')
            ->assertSee('Ödendi (PAYTR / banka doğrulandı)')
            ->assertSee('1.202,06')
            ->assertSee('PayTR doğrulanmış tahsilat');

        $this->assertSame(109940, $order->fresh()->total_cents);
    }

    public function test_pending_payment_cannot_look_paid_or_have_fake_installment_difference(): void
    {
        $order = $this->order();
        $this->attempt($order, 'pending');
        $this->admin();

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Ödeme toplamı')
            ->assertSee('Ödeme bekleniyor')
            ->assertDontSee('Vade farkı / PAYTR ödeme farkı')
            ->assertDontSee('Ödendi (PAYTR / banka doğrulandı)');
    }

    public function test_confirmed_paytr_without_difference_does_not_invent_vade_farki(): void
    {
        $order = $this->order();
        $this->attempt($order, 'success', 109940);
        $order->forceFill(['status' => 'preparing'])->save();
        $this->admin();

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Ödendi (PAYTR / banka doğrulandı)')
            ->assertDontSee('Vade farkı / PAYTR ödeme farkı');
    }
}
