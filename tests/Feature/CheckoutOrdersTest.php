<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\BankAccount;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These cases exercise successful checkout, so supply a real active
        // payment fixture. Separate tests verify checkout without any IBAN.
        BankAccount::create([
            'bank_name' => 'Test Bankası', 'account_holder' => 'Polaris Kurşun',
            'iban' => 'TR330006100519786457841326',
            'sort_order' => 0, 'is_active' => true,
        ]);
    }

    private function product(string $sku = 'SURF-75', string $price = '500.00', int $stock = 10): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Renkli Surf Kurşun', 'slug' => 'renkli-surf-kursun', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => $sku, 'name' => 'Polaris '.$sku, 'slug' => strtolower($sku),
            'price' => $price, 'stock_quantity' => $stock, 'is_active' => true,
        ]);
    }

    private function address(array $extra = []): array
    {
        return array_merge([
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Gaziemir',
            'street_address' => 'Atıfbey Mahallesi, Deniz Caddesi No: 12 Daire: 4',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'order_note' => 'Lütfen teslimattan önce arayın.',
        ], $extra);
    }

    private function ready(Product $product, int $quantity = 1): string
    {
        $this->post(route('cart.add'), ['items' => [$product->id => $quantity]])
            ->assertRedirect(route('cart.index'));
        $this->get(route('checkout.index'))->assertOk()->assertSee('Siparişi Tamamla');

        return (string) session('checkout_token');
    }

    private function purchase(Product $product, array $overrides = [], int $qty = 1)
    {
        $token = $this->ready($product, $qty);

        return $this->post(route('checkout.place'), $this->address(
            array_merge(['checkout_token' => $token], $overrides)
        ));
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_guest_can_place_unpaid_bank_transfer_order_without_becoming_member(): void
    {
        $product = $this->product();
        $this->purchase($product)->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame(50000, $order->subtotal_cents);
        $this->assertSame(7990, $order->shipping_cents);
        $this->assertSame(57990, $order->total_cents);
        $this->assertSame('Gaziemir', $order->district);
        $this->assertSame('Lütfen teslimattan önce arayın.', $order->order_note);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(50000, $order->items()->first()->unit_price_cents);
        $this->assertSame([], session('shopping_cart', []));
        $this->assertDatabaseCount('users', 0);

        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Ödeme bekleniyor')
            ->assertSee('aria-label="Sipariş kutlaması">🎉</div>', false)
            ->assertSee('data-order-celebration="'.$order->id.'"', false)
            ->assertDontSee('checkout-success-mark">✓</div>', false);
    }

    public function test_optional_invoice_identity_is_encrypted_and_vat_is_snapshotted(): void
    {
        $product = $this->product();
        $token = $this->ready($product);
        $this->get(route('checkout.index'))->assertOk()->assertSee('name="billing_tckn"',false);
        $this->post(route('checkout.place'), $this->address(['checkout_token'=>$token,'billing_tckn'=>'11111111111']))
            ->assertSessionHasErrors('billing_tckn');
        $this->assertNull(session()->getOldInput('billing_tckn'));
        $this->assertDatabaseCount('orders',0);
        $this->post(route('checkout.place'), $this->address(['checkout_token'=>$token,'billing_tckn'=>'12345678950']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('12345678950',$order->billing_tckn);
        $this->assertNotSame('12345678950',$order->getRawOriginal('billing_tckn'));
        $this->assertArrayNotHasKey('billing_tckn',$order->toArray());
        $this->assertSame(20,$order->items()->firstOrFail()->vat_rate);
        $this->assertSame(57990,$order->total_cents);
        $product->update(['vat_rate'=>10]);
        $this->assertSame(20,$order->items()->firstOrFail()->vat_rate);
        $this->assertSame('12345678950',\App\Support\InvoiceBuyerIdentifier::resolve($order));
    }

    public function test_anonymous_invoice_placeholder_requires_explicit_archive_eligibility(): void
    {
        $this->purchase($this->product())->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertNull($order->billing_tckn);
        $this->assertSame('11111111111',\App\Support\InvoiceBuyerIdentifier::resolve($order,true));
        $this->expectException(\DomainException::class);
        \App\Support\InvoiceBuyerIdentifier::resolve($order);
    }

    public function test_invoice_preview_is_private_read_only_and_shipping_rate_is_only_an_assumption(): void
    {
        $this->purchase($this->product())->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $url=route('admin.orders.invoice-preview',$order);
        \Illuminate\Support\Facades\Http::fake();
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($this->admin());
        $this->get($url)->assertOk()->assertSee('Belge düzenlenmedi')->assertSee('Vergi hesabı eksik')->assertSee('579,90 ₺');
        $reply=$this->get($url.'?shipping_rate=20')->assertOk()
            ->assertSee('Seçilen kargo KDV oranı yalnızca varsayımdır.')
            ->assertSee('483,25 ₺')->assertSee('96,65 ₺')->assertDontSee('Vergi hesabı eksik');
        $this->assertStringContainsString('no-store',$reply->headers->get('Cache-Control'));
        $this->get($url.'?shipping_rate[]=20')->assertStatus(422);
        $this->assertSame(57990,$order->fresh()->total_cents);
        $this->assertSame('pending_payment',$order->fresh()->status);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_invoice_preparation_preserves_one_uuid_and_exports_only_local_xml(): void
    {
        $this->purchase($this->product())->assertRedirect()->assertSessionHasNoErrors();
        $order=Order::firstOrFail();
        $store=route('admin.orders.invoice-preparation.store',$order);
        $download=route('admin.orders.invoice-preparation.xml',$order);
        \Illuminate\Support\Facades\Http::fake();
        $this->post($store,['shipping_rate'=>20])->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->post($store,['shipping_rate'=>20])->assertForbidden();
        $this->get($download)->assertForbidden();
        $this->actingAs($this->admin());
        $this->post($store)->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('invoice_preparations',0);
        $this->post($store,['shipping_rate'=>20])->assertRedirect()->assertSessionHasNoErrors();
        $draft=\App\Models\InvoicePreparation::firstOrFail();
        $uuid=$draft->uuid;
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertSame(57990,$draft->gross_cents);
        $this->assertStringNotContainsString('gross_cents',$draft->getRawOriginal('amounts'));
        $this->post($store,['shipping_rate'=>20])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoice_preparations',1);
        $this->assertSame($uuid,$draft->fresh()->uuid);
        $this->post($store,['shipping_rate'=>10])->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('invoice_preparations',1);
        $response=$this->get($download)->assertOk()->assertSee('LOCAL_ONLY_NOT_FOR_SUBMISSION',false)->assertSee($uuid)->assertSee('579.90')->assertDontSee('ayse@example.com');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $doc=new \DOMDocument();
        $this->assertTrue($doc->loadXML($response->getContent()));
        $this->assertSame('InvoicePreparation',$doc->documentElement->localName);
        $this->assertSame('false',$doc->documentElement->getAttribute('fiscalDocument'));
        $this->get(route('admin.orders.invoice-preview',$order))->assertOk()->assertSee($uuid)->assertSee('Yerel taslak');
        $this->assertSame('pending_payment',$order->fresh()->status);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_untracked_stock_can_be_ordered_without_decrement_or_cancel_restore(): void
    {
        $product = $this->product();
        $product->update(['tracks_stock' => false, 'stock_quantity' => 999]);

        $this->purchase($product, [], 3)->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame(999, $product->fresh()->stock_quantity);

        $this->actingAs($this->admin());
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(999, $product->fresh()->stock_quantity);
    }

    public function test_empty_checkout_redirects_to_cart_and_cannot_place_order(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        $this->post(route('checkout.place'), $this->address(['checkout_token' => (string) Str::uuid()]))
            ->assertSessionHasErrors('checkout_token');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invoice_corporate_requires_all_three_fields_and_preserves_them(): void
    {
        $product = $this->product();
        $token = $this->ready($product);

        $this->post(route('checkout.place'), $this->address([
            'checkout_token' => $token, 'invoice_type' => 'corporate',
        ]))->assertSessionHasErrors(['company_title', 'tax_office', 'tax_number']);

        $this->post(route('checkout.place'), $this->address([
            'checkout_token' => $token, 'invoice_type' => 'corporate',
            'company_title' => 'Polaris Test Limited Şirketi',
            'tax_office' => 'Gaziemir',
            'tax_number' => '1234567890',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertSame('corporate', $order->invoice_type);
        $this->assertSame('Polaris Test Limited Şirketi', $order->company_title);
        $this->assertSame('1234567890', $order->tax_number);
    }

    public function test_individual_invoice_discards_forged_company_details(): void
    {
        $product = $this->product();
        $this->purchase($product, [
            'invoice_type' => 'individual',
            'company_title' => 'Sahte Firma',
            'tax_office' => 'Sahte',
            'tax_number' => '1234567890',
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertNull($order->company_title);
        $this->assertNull($order->tax_office);
        $this->assertNull($order->tax_number);
    }

    public function test_free_shipping_and_pricing_are_calculated_on_server_even_when_client_forges_totals(): void
    {
        $product = $this->product('HIGH', '500.00');
        $token = $this->ready($product, 2);
        $this->post(route('checkout.place'), $this->address([
            'checkout_token' => $token,
            'subtotal_cents' => 1, 'total_cents' => 1, 'shipping_cents' => 0,
        ]))->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(100000, $order->subtotal_cents);
        $this->assertSame(0, $order->shipping_cents);
        $this->assertSame(100000, $order->total_cents);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_950_lira_products_plus_79_lira_shipping_do_not_unlock_free_shipping(): void
    {
        \App\Models\StoreSetting::current()->update([
            'free_shipping_threshold' => '1000.00',
            'shipping_fee' => '79.00',
        ]);

        $product = $this->product('NEAR-THRESHOLD', '950.00');
        $token = $this->ready($product);

        $this->post(route('checkout.place'), $this->address([
            'checkout_token' => $token,
            'shipping_cents' => 0, // Buyer cannot zero out their shipping contribution.
            'total_cents' => 95000,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(95000, $order->subtotal_cents);
        $this->assertSame(7900, $order->shipping_cents);
        $this->assertSame(102900, $order->total_cents);
    }

    public function test_changed_stock_rejects_order_without_creating_record_or_decrementing_twice(): void
    {
        $product = $this->product('LIMITED', '500.00', 2);
        $token = $this->ready($product, 2);
        $product->update(['stock_quantity' => 1]);
        $this->post(route('checkout.place'), $this->address(['checkout_token' => $token]))
            ->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(2, session('shopping_cart')[$product->id]);
    }

    public function test_second_submit_with_same_checkout_token_does_not_create_another_order(): void
    {
        $product = $this->product();
        $token = $this->ready($product);
        $data = $this->address(['checkout_token' => $token]);
        $this->post(route('checkout.place'), $data)->assertRedirect();
        $this->post(route('checkout.place'), $data)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_guest_order_confirmation_is_not_accessible_from_another_session(): void
    {
        $product = $this->product();
        $this->purchase($product)->assertRedirect();
        $order = Order::firstOrFail();

        $this->withSession(['confirmed_order_ids' => [], 'checkout_last_order' => null])
            ->get(route('checkout.success', $order))->assertNotFound();
    }

    public function test_guest_can_opt_in_to_create_account_and_first_saved_address(): void
    {
        $product = $this->product();
        $this->purchase($product, [
            'create_account' => 1, 'account_password' => 'VerySecure987',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'ayse@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $user->addresses()->count());
        $this->assertSame($user->id, Order::firstOrFail()->user_id);
        $this->assertTrue($user->addresses()->first()->is_default);
        $this->get(route('account.index'))->assertOk()->assertSee('Siparişlerim')->assertSee('Adres defterim');
    }

    public function test_existing_member_can_save_new_address_and_place_an_order(): void
    {
        $member = User::factory()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com']);
        $this->actingAs($member);
        $product = $this->product();
        $this->purchase($product, ['save_address' => 1])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($member->id, Order::firstOrFail()->user_id);
        $this->assertSame('Ev', $member->addresses()->firstOrFail()->label);
        $this->get(route('account.addresses.index'))->assertOk()->assertSee('Gaziemir');
    }

    public function test_member_can_create_multiple_saved_addresses_and_other_user_cannot_edit_them(): void
    {
        $member = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($member);

        foreach ([
            ['address_label' => 'Ev', 'city' => 'İzmir'],
            ['address_label' => 'İş', 'city' => 'Ankara', 'district' => 'Çankaya'],
        ] as $record) {
            $payload = $this->address($record);
            unset($payload['email'], $payload['payment_method'], $payload['order_note']);
            $this->post(route('account.addresses.store'), $payload)
                ->assertRedirect(route('account.addresses.index'))->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $member->addresses()->count());
        $this->assertSame(1, $member->addresses()->where('is_default', true)->count());

        $address = $member->addresses()->firstOrFail();
        $this->actingAs($second);
        $this->get(route('account.addresses.edit', $address))->assertNotFound();
        $this->patch(route('account.addresses.update', $address), $this->address())->assertNotFound();
        $this->delete(route('account.addresses.destroy', $address))->assertNotFound();
        $this->assertSame(2, $member->addresses()->count());
    }

    public function test_admin_sees_orders_but_non_admin_cannot(): void
    {
        $product = $this->product();
        $this->purchase($product)->assertRedirect();
        $order = Order::firstOrFail();

        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertForbidden();
        $this->actingAs($this->admin());
        $this->get(route('admin.orders.index'))->assertOk()->assertSee($order->order_number);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Sipariş notu');
    }

    public function test_cancelling_order_restores_reserved_stock_exactly_once_and_is_terminal(): void
    {
        $product = $this->product();
        $this->purchase($product, [], 2)->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $this->actingAs($this->admin());
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'preparing'])
            ->assertSessionHasErrors('status');
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_shipped_order_cannot_be_cancelled_automatically(): void
    {
        $product = $this->product();
        $this->purchase($product)->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($this->admin());
        $this->patch(route('admin.orders.update', $order), ['status' => 'preparing'])
            ->assertSessionHasErrors('status');
        $notice = PaymentNotification::create([
            'order_id' => $order->id,
            'bank_name_snapshot' => 'Test Bankası',
            'iban_snapshot' => 'TR330006100519786457841326',
            'status' => 'pending',
        ]);
        $this->post(route('admin.payment-notifications.verify', $notice), ['bank_verified' => 1])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHasNoErrors();
        $this->assertSame('preparing', $order->fresh()->status);
        $this->patch(route('admin.orders.update', $order), ['status' => 'shipped'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_account_registration_login_and_logout_work_without_admin_role(): void
    {
        $this->post(route('account.register.submit'), [
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'password' => 'VerySecure987',
            'password_confirmation' => 'VerySecure987',
        ])->assertRedirect(route('account.index'))->assertSessionHasNoErrors();

        $user = User::firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertAuthenticatedAs($user);
        $this->post(route('account.logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('account.login.submit'), [
            'email' => 'ayse@example.com', 'password' => 'VerySecure987',
        ])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
    }
}
