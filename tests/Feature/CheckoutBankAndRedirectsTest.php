<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\UrlRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutBankAndRedirectsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function bank(bool $active = true): BankAccount
    {
        return BankAccount::create([
            'bank_name' => 'Test Bankası',
            'account_holder' => 'Polaris Kurşun',
            'iban' => 'TR330006100519786457841326',
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Deniz Kurşunu', 'slug' => 'deniz-kursunu', 'is_active' => true]);
        return Product::create([
            'primary_category_id' => $category->id,
            'name' => '75g Test Kurşunu',
            'slug' => 'test-75g',
            'sku' => 'TEST-75',
            'price' => '89.90',
            'stock_quantity' => 10,
            'tracks_stock' => true,
            'is_active' => true,
        ]);
    }

    private function checkout(Product $product): string
    {
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk();
        return (string) session('checkout_token');
    }

    private function payload(string $token): array
    {
        return [
            'checkout_token' => $token, 'payment_method' => 'bank_transfer',
            'email' => 'musteri@example.com', 'address_label' => 'Ev',
            'full_name' => 'Deneme Müşteri', 'phone' => '05321234567',
            'city' => 'İzmir', 'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi Çiçek Sokak No 23',
            'invoice_type' => 'individual',
        ];
    }

    public function test_bank_is_hidden_without_active_iban_and_forged_payment_is_rejected(): void
    {
        $product = $this->product();
        $token = $this->checkout($product);
        $html = $this->get(route('checkout.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('value="bank_transfer"', $html);
        $this->assertStringNotContainsString('<strong>Havale / EFT</strong>', $html);
        $this->assertStringContainsString('Şu anda aktif bir ödeme yöntemi bulunmuyor.', $html);
        $this->assertStringContainsString('data-payment-available="0"', $html);

        $this->post(route('checkout.place'), $this->payload($token))
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->bank(false);
        $this->get(route('checkout.index'))->assertOk()->assertDontSee('<strong>Havale / EFT</strong>', false);
        $this->post(route('checkout.place'), $this->payload($token))
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_bank_appears_when_activated_and_cannot_be_bypassed_after_deactivation(): void
    {
        $product = $this->product();
        $bank = $this->bank();
        $token = $this->checkout($product);
        $this->get(route('checkout.index'))->assertOk()
            ->assertSee('value="bank_transfer"', false)
            ->assertSee('data-payment-available="1"', false);

        $bank->update(['is_active' => false]);
        $this->post(route('checkout.place'), $this->payload($token))
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);

        $bank->update(['is_active' => true]);
        $this->post(route('checkout.place'), $this->payload($token))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    private function unpaidOrder(Product $product, string $number, string $status = 'pending_payment'): Order
    {
        $order = Order::create([
            'order_number' => $number, 'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Test Müşteri', 'customer_email' => 'test@example.com',
            'phone' => '05321234567', 'city' => 'İzmir', 'district' => 'Buca',
            'street_address' => 'Atatürk mahallesi no 34',
            'invoice_type' => 'individual', 'payment_method' => 'bank_transfer',
            'status' => $status, 'subtotal_cents' => 17980,
            'shipping_cents' => 0, 'total_cents' => 17980,
            'currency' => 'TRY', 'is_test' => true,
            'placed_at' => now()->subDays(3),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'sku' => $product->sku,
            'product_name' => $product->name, 'quantity' => 2,
            'unit_price_cents' => 8990, 'line_total_cents' => 17980,
        ]);
        $product->decrement('stock_quantity', 2);
        return $order;
    }

    public function test_expired_unpaid_orders_restore_stock_once_but_protect_bank_claims(): void
    {
        $product = $this->product();
        $bank = $this->bank();
        $old = $this->unpaidOrder($product, 'PK-AUTO-OLD');
        $protected = $this->unpaidOrder($product, 'PK-AUTO-CLAIM');
        $recent = $this->unpaidOrder($product, 'PK-AUTO-RECENT');
        $recent->update(['placed_at' => now()->subHours(3)]);

        PaymentNotification::create([
            'order_id' => $protected->id, 'bank_account_id' => $bank->id,
            'bank_name_snapshot' => $bank->bank_name,
            'iban_snapshot' => $bank->iban, 'status' => 'pending',
        ]);

        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('pending_payment', $protected->fresh()->status);
        $this->assertSame('pending_payment', $recent->fresh()->status);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id' => $old->id, 'source' => 'automatic_bank_expiry',
        ]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(1, $old->statusChanges()->count());
    }

    public function test_redirects_are_admin_only_permanent_portable_and_cycle_safe(): void
    {
        $this->get(route('admin.redirects.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.redirects.store'), [
            'old_path' => '/old', 'new_path' => '/new',
        ])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())->get(route('admin.redirects.index'))->assertForbidden();
        $this->actingAs($this->admin());

        $this->post(route('admin.redirects.store'), [
            'old_path' => 'https://old-other-platform.example/urun/eski-kursun',
            'new_path' => '/urun/yeni-kursun',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $url = 'https://brand-new-site.example/urun/eski-kursun';
        $this->get($url)->assertStatus(301)
            ->assertRedirect('https://brand-new-site.example/urun/yeni-kursun');
        $this->get($url.'?utm_source=google')->assertStatus(301)
            ->assertRedirect('https://brand-new-site.example/urun/yeni-kursun?utm_source=google');

        $entry = UrlRedirect::firstOrFail();
        $this->assertSame('/urun/eski-kursun', $entry->old_path);
        $this->assertSame(2, $entry->fresh()->hit_count);
        $this->get('https://brand-new-site.example/random-page')->assertNotFound();

        $this->post(route('admin.redirects.store'), [
            'old_path' => '/urun/yeni-kursun',
            'new_path' => '/urun/eski-kursun', 'is_active' => 1,
        ])->assertSessionHasErrors('old_path');
        $this->assertDatabaseCount('url_redirects', 1);
        $this->get('/sepet/ekle')->assertStatus(405);
    }

    public function test_redirect_disabling_and_bulk_csv_are_transactional(): void
    {
        $this->actingAs($this->admin());
        $temp = tempnam(sys_get_temp_dir(), 'links-');
        file_put_contents($temp, "\xEF\xBB\xBFold_path;new_path\n/eski-urun;/urun/yeni-urun\n/eski-kategori;/kategori/yeni-kategori\n");
        $upload = new UploadedFile($temp, 'redirects.csv', 'text/csv', null, true);
        $this->post(route('admin.redirects.import'), ['csv' => $upload])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('url_redirects', 2);

        $redirect = UrlRedirect::where('old_path', '/eski-urun')->firstOrFail();
        $this->patch(route('admin.redirects.update', $redirect), [
            'new_path' => '/urun/duzeltilmis',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->get('/eski-urun')->assertNotFound();

        $this->patch(route('admin.redirects.update', $redirect), [
            'new_path' => '/urun/duzeltilmis',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->get('/eski-urun')->assertStatus(301)->assertRedirect(url('/urun/duzeltilmis'));
        @unlink($temp);
    }
}
