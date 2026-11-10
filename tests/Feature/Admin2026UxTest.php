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

class Admin2026UxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'newtic-admin@example.com',
            'password' => 'SecurePassword2026!',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function product(string $sku = 'SURF-75', int $stock = 3): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Renkli Surf Kurşun',
            'slug' => 'renkli-surf-kursun',
            'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Renkli Surf Kurşun '.$sku,
            'sku' => $sku,
            'slug' => strtolower($sku),
            'price' => '149.90',
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    private function order(bool $isTest, int $amount, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'musteri@example.com',
            'phone' => '05321234567',
            'address_label' => 'Ev',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi 1000 Sokak No 3',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'status' => 'preparing',
            'subtotal_cents' => $amount,
            'shipping_cents' => 0,
            'total_cents' => $amount,
            'currency' => 'TRY',
            'is_test' => $isTest,
            'placed_at' => now(),
        ]);
    }

    public function test_new_dashboard_is_accessible_only_to_administrators(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.search', ['q' => 'surf']))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.search', ['q' => 'surf']))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Genel Bakış')
            ->assertSee('Satış performansı')
            ->assertSee('Hızlı işlemler')
            ->assertSee('Son siparişler')
            ->assertSee('Havale / EFT')
            ->assertSee('Stok uyarıları');
    }

    public function test_dashboard_shows_verified_real_revenue_and_ignores_test_orders(): void
    {
        $real = $this->order(false, 12345, 'PK-REAL-01');
        $test = $this->order(true, 990000, 'PK-TEST-01');
        foreach ([$real, $test] as $order) {
            PaymentNotification::create([
                'order_id' => $order->id,
                'bank_name_snapshot' => 'Deneme Bankası',
                'iban_snapshot' => 'TR330006100519786457841326',
                'status' => 'verified',
            ]);
        }

        $this->actingAs($this->admin());
        $page = $this->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Doğrulanmış ciro')->assertSee('123,45 ₺')
            ->assertSee('PK-REAL-01')->assertSee('PK-TEST-01');
        $this->assertSame(2, Order::count());
    }

    public function test_navigation_retains_existing_management_routes(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.index'))
            ->assertSee(route('admin.orders.index'))
            ->assertSee(route('admin.products.index'))
            ->assertSee(route('admin.categories.index'))
            ->assertSee(route('admin.payment-notifications.index'))
            ->assertSee(route('admin.bank-accounts.index'))
            ->assertSee('data-admin-menu-toggle')
            ->assertSee('admin-global-search');
        // The original storefront sections editor must remain fully functional.
        $this->get(route('admin.index'))->assertOk()->assertSee('Ana sayfa blokları');
    }

    public function test_customer_and_sku_search_returns_real_records(): void
    {
        $product = $this->product('SURF-125');
        $order = $this->order(true, 2990, 'PK-UX-01');
        $this->actingAs($this->admin());

        $this->get(route('admin.search', ['q' => 'SURF-125']))->assertOk()
            ->assertSee($product->name)->assertSee(route('admin.products.edit', $product));

        $this->get(route('admin.search', ['q' => 'Ayşe']))->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(route('admin.orders.show', $order));

        $this->get(route('admin.search', ['q' => str_repeat('x', 101)]))->assertStatus(422);
    }

    public function test_product_filters_show_low_stock_and_can_hide_inactive_products(): void
    {
        $low = $this->product('LOW-125', 2);
        $normal = $this->product('OK-125', 50);
        $this->actingAs($this->admin());

        $this->get(route('admin.products.index', ['status' => 'low_stock']))
            ->assertOk()->assertSee($low->name)->assertDontSee($normal->name);

        $this->get(route('admin.products.index', ['status' => 'active']))
            ->assertOk()->assertSee($low->name)->assertSee($normal->name);

        $low->update(['is_active' => false]);
        $this->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertOk()->assertSee($low->name)->assertDontSee($normal->name);
    }

    public function test_order_filters_and_customer_search_preserve_order_records(): void
    {
        $real = $this->order(false, 12345, 'PK-UX-REAL');
        $test = $this->order(true, 55000, 'PK-UX-TEST');
        $test->update(['status' => 'pending_payment']);
        $this->actingAs($this->admin());

        $this->get(route('admin.orders.index', ['q' => 'PK-UX-REAL']))
            ->assertOk()->assertSee($real->order_number)->assertDontSee($test->order_number);

        $this->get(route('admin.orders.index', ['status' => 'pending_payment']))
            ->assertOk()->assertSee($test->order_number)->assertDontSee($real->order_number);

        $this->get(route('admin.orders.show', $real))->assertOk()
            ->assertSee('Sipariş süreci')
            ->assertSee('Müşteri ve teslimat')
            ->assertSee('Durum yönetimi');
    }

    public function test_product_editor_has_working_field_names_and_image_upload(): void
    {
        $this->product();
        $this->actingAs($this->admin());
        $this->get(route('admin.products.create'))->assertOk()
            ->assertSee('data-admin-image-input')
            ->assertSee('data-admin-product-hero')
            ->assertSee('name="regular_price"', false)
            ->assertSee('name="sale_price"', false)
            ->assertSee('name="stock_quantity"', false)
            ->assertSee('name="primary_category_id"', false)
            ->assertSee('name="images[]"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('Ürünü oluştur');
    }

    public function test_authorized_admin_login_lands_on_new_dashboard(): void
    {
        $admin = $this->admin();
        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'SecurePassword2026!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
