<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentNotification;
use App\Models\PaytrPaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Support\VerifiedOrderRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VerifiedOrderRevenueConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function order(string $method, int $cents, bool $test = false, string $status = 'preparing'): Order
    {
        return Order::create([
            'order_number' => 'AUDIT-'.Str::upper(Str::random(15)),
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Test kişi',
            'customer_email' => 'audit@example.test',
            'phone' => '05321234567',
            'city' => 'İzmir', 'district' => 'Gaziemir',
            'street_address' => 'Test adresi',
            'payment_method' => $method,
            'status' => $status,
            'subtotal_cents' => $cents, 'shipping_cents' => 0,
            'total_cents' => $cents, 'currency' => 'TRY',
            'is_test' => $test, 'placed_at' => now(),
        ]);
    }

    private function bankVerified(Order $order): void
    {
        PaymentNotification::create([
            'order_id' => $order->id,
            'bank_name_snapshot' => 'Deneme Bankası',
            'iban_snapshot' => 'TR000000000000000000000000',
            'status' => 'verified',
        ]);
    }

    private function cardSucceeded(Order $order, bool $attemptTest = false): void
    {
        PaytrPaymentAttempt::create([
            'order_id' => $order->id,
            'merchant_oid' => 'NT'.Str::upper(Str::random(25)),
            'amount_cents' => $order->total_cents,
            'currency' => 'TRY',
            'status' => 'success',
            'is_test' => $attemptTest,
            'paid_total_cents' => $order->total_cents,
        ]);
    }

    public function test_all_admin_screens_count_the_same_verified_non_test_gross_revenue(): void
    {
        $bank = $this->order('bank_transfer', 10000);
        $this->bankVerified($bank);

        $card = $this->order('paytr', 20000);
        $this->cardSucceeded($card);

        $pending = $this->order('paytr', 90000, false, 'pending_payment');
        $this->cardSucceeded($pending);
        $test = $this->order('paytr', 50000, true);
        $this->cardSucceeded($test, true);
        $cancelled = $this->order('bank_transfer', 80000, false, 'cancelled');
        $this->bankVerified($cancelled);
        $unverified = $this->order('bank_transfer', 60000);
        PaymentNotification::create([
            'order_id' => $unverified->id,
            'bank_name_snapshot' => 'Deneme Bankası',
            'iban_snapshot' => 'TR000000000000000000000000',
            'status' => 'pending',
        ]);
        $wrongMethod = $this->order('paytr', 70000);
        $this->bankVerified($wrongMethod);

        // Misfiled payment evidence must never validate a different method.
        $wrongCard = $this->order('bank_transfer', 40000);
        $this->cardSucceeded($wrongCard);

        $product = Product::create([
            'primary_category_id' => Category::create([
                'name' => 'Kontrol', 'slug' => 'kontrol', 'is_active' => true,
            ])->id,
            'name' => 'Kontrol Ürünü', 'sku' => 'AUDIT-SKU',
            'slug' => 'kontrol-urunu', 'price' => '100.00',
            'stock_quantity' => 4, 'tracks_stock' => true, 'is_active' => true,
        ]);

        foreach ([$bank, $card, $pending, $test, $cancelled, $wrongMethod] as $order) {
            $order->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku, 'product_name' => $product->name,
                'quantity' => 1, 'unit_price_cents' => $order->total_cents,
                'line_total_cents' => $order->total_cents,
            ]);
        }

        $this->assertSame(2, VerifiedOrderRevenue::query()->count());
        $this->assertSame(30000, (int) VerifiedOrderRevenue::query()->sum('total_cents'));

        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['verified_revenue_cents'] === 30000)
            ->assertViewHas('trend', fn ($trend) => (int) $trend->sum('revenue') === 30000);
        $this->get(route('admin.reports'))->assertOk()
            ->assertViewHas('paidCents', 30000)
            ->assertViewHas('paidCount', 2)
            ->assertViewHas('topProducts', fn ($top) => (int) $top->first()->sold === 2);
        $this->get(route('admin.analytics'))->assertOk()
            ->assertViewHas('revenueCents', 30000)
            ->assertViewHas('orderCount', 2)
            ->assertViewHas('products', fn ($products) => $products->first()['sold'] === 2);
    }
}
