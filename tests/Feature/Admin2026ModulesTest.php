<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Admin2026ModulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    public function test_stock_page_shows_only_low_stock_active_products_by_default(): void
    {
        $category = Category::create(['name'=>'Kurşun', 'slug'=>'kursun', 'is_active'=>true]);
        foreach (['AZ' => 2, 'ÇOK' => 45] as $sku => $qty) {
            Product::create([
                'primary_category_id'=>$category->id,
                'sku'=>$sku, 'name'=>'Ürün '.$sku, 'slug'=>strtolower($sku),
                'price'=>'75.50', 'stock_quantity'=>$qty, 'is_active'=>true,
            ]);
        }

        $this->get(route('admin.stock'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin());
        $this->get(route('admin.stock'))->assertOk()
            ->assertSee('Stok Yönetimi')->assertSee('Ürün AZ')->assertDontSee('Ürün ÇOK');

        $this->get(route('admin.stock',['filter'=>'all']))->assertOk()
            ->assertSee('Ürün AZ')->assertSee('Ürün ÇOK');

        $this->get(route('admin.stock',['filter'=>'invalid']))->assertStatus(422);
    }

    public function test_customer_directory_only_shows_members_and_excludes_administrators(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name'=>'Müşteri Zeynep', 'email'=>'zeynep@example.com']);

        $this->get(route('admin.customers'))->assertRedirect(route('admin.login'));
        $this->actingAs($admin);
        $this->get(route('admin.customers'))->assertOk()
            ->assertSee('Müşteri Zeynep')
            ->assertSee('zeynep@example.com')
            ->assertDontSee($admin->email);

        $this->get(route('admin.customers',['q'=>'zeynep']))->assertOk()->assertSee('Müşteri Zeynep');
    }

    public function test_sales_report_only_counts_verified_real_orders_not_test_orders(): void
    {
        $real = $this->order(false, 'PK-PAID-001', 11000);
        $test = $this->order(true, 'PK-DEMO-001', 999000);

        foreach ([$real, $test] as $order) {
            PaymentNotification::create([
                'order_id' => $order->id,
                'bank_name_snapshot' => 'Test Bankası',
                'iban_snapshot' => 'TR330006100519786457841326',
                'status' => 'verified',
            ]);
        }

        $real->items()->create([
            'sku'=>'SKU-001', 'product_name'=>'Polaris Kurşun 0.75 gr',
            'quantity'=>2, 'unit_price_cents'=>5500, 'line_total_cents'=>11000,
        ]);

        $this->actingAs($this->admin());
        $this->get(route('admin.reports', ['days'=>30]))->assertOk()
            ->assertSee('Satış raporları')
            ->assertSee('110,00 ₺')
            ->assertSee('Polaris Kurşun 0.75 gr')
            ->assertDontSee('9.990,00 ₺');

        $this->get(route('admin.reports', ['days'=>7]))->assertOk();
        $this->get(route('admin.reports', ['days'=>180]))->assertStatus(422);
    }

    public function test_reports_and_customers_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.reports'))->assertForbidden();
        $this->get(route('admin.customers'))->assertForbidden();
        $this->get(route('admin.stock'))->assertForbidden();
    }

    private function order(bool $test, string $number, int $amount): Order
    {
        return Order::create([
            'order_number'=>$number,
            'checkout_token'=>(string) Str::uuid(),
            'customer_name'=>'Deneme',
            'customer_email'=>'deneme@example.com',
            'phone'=>'05321234567',
            'city'=>'İzmir',
            'district'=>'Buca',
            'street_address'=>'Atatürk Mahallesi 1 Sokak No 2',
            'invoice_type'=>'individual',
            'payment_method'=>'bank_transfer',
            'status'=>'preparing',
            'subtotal_cents'=>$amount,
            'shipping_cents'=>0,
            'total_cents'=>$amount,
            'currency'=>'TRY',
            'is_test'=>$test,
            'placed_at'=>now(),
        ]);
    }
}
