<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function bank(string $iban = 'TR330006100519786457841326', bool $active = true): BankAccount
    {
        return BankAccount::create([
            'bank_name' => 'Deneme Bankası',
            'account_holder' => 'Polaris Kurşun',
            'iban' => $iban,
            'sort_order' => 0,
            'is_active' => $active,
        ]);
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function order(): Order
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Surf kurşun', 'slug' => 'surf-kursun', 'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'BANK-TEST-75',
            'name' => 'Surf Kurşun 75 gr',
            'slug' => 'surf-kursun-75-bank',
            'price' => '100.00',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.place'), [
            'checkout_token' => session('checkout_token'),
            'payment_method' => 'bank_transfer',
            'email' => 'musteri@example.com',
            'address_label' => 'Ev',
            'full_name' => 'Deneme Müşteri',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi 1234 Sokak Bina 5',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Order::query()->firstOrFail();
    }

    public function test_ibans_are_validated_by_country_length_and_checksum(): void
    {
        $this->assertTrue(BankAccount::validTurkishIban('tr33 0006 1005 1978 6457 8413 26'));
        $this->assertTrue(BankAccount::validTurkishIban('TR060006100519786457841327'));
        $this->assertFalse(BankAccount::validTurkishIban('TR340006100519786457841326'));
        $this->assertFalse(BankAccount::validTurkishIban('DE330006100519786457841326'));
    }

    public function test_only_admin_can_add_multiple_banks_and_invalid_iban_is_rejected(): void
    {
        $this->get(route('admin.bank-accounts.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.bank-accounts.store'), [
            'bank_name' => 'Banka', 'account_holder' => 'Şirket',
            'iban' => 'TR330006100519786457841326', 'sort_order' => 0,
            'is_active' => 1,
        ])->assertForbidden();

        $this->actingAs($this->administrator());
        $this->post(route('admin.bank-accounts.store'), [
            'bank_name' => 'Deneme Bankası', 'account_holder' => 'Polaris Kurşun',
            'iban' => 'TR340006100519786457841326', 'sort_order' => 0,
            'is_active' => 1,
        ])->assertSessionHasErrors('iban');
        $this->assertDatabaseCount('bank_accounts', 0);

        foreach (['tr33 0006 1005 1978 6457 8413 26', 'TR060006100519786457841327'] as $index => $iban) {
            $this->post(route('admin.bank-accounts.store'), [
                'bank_name' => 'Banka '.$index, 'account_holder' => 'Polaris Kurşun',
                'iban' => $iban, 'sort_order' => $index,
                'is_active' => 1,
            ])->assertRedirect(route('admin.bank-accounts.index'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('bank_accounts', 2);
        $this->assertDatabaseHas('bank_accounts', ['iban' => 'TR330006100519786457841326']);
        $this->get(route('admin.bank-accounts.index'))->assertOk()->assertSee('Banka 0');
    }

    public function test_bank_logo_can_be_uploaded_and_deactivated(): void
    {
        Storage::fake('public');
        $this->actingAs($this->administrator());
        $this->post(route('admin.bank-accounts.store'), [
            'bank_name' => 'Banka', 'account_holder' => 'Polaris Kurşun',
            'iban' => 'TR330006100519786457841326', 'sort_order' => 0,
            'is_active' => 1, 'logo' => UploadedFile::fake()->image('bank.png', 180, 60),
        ])->assertRedirect(route('admin.bank-accounts.index'))->assertSessionHasNoErrors();

        $account = BankAccount::firstOrFail();
        $this->assertStringStartsWith('storage/bank-logos/', $account->logo_path);
        Storage::disk('public')->assertExists(substr($account->logo_path, 8));

        $this->patch(route('admin.bank-accounts.update', $account), [
            'bank_name' => 'Banka', 'account_holder' => 'Polaris Kurşun',
            'iban' => $account->iban, 'sort_order' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($account->fresh()->is_active);
    }

    public function test_guest_can_copy_bank_details_and_send_a_single_pending_payment_claim(): void
    {
        $bank = $this->bank();
        $order = $this->order();

        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Banka hesabını seç')
            ->assertSee('data-copy-value="'.$bank->iban.'"', false)
            ->assertSee('data-copy-value="'.$order->order_number.'"', false)
            ->assertSee('Ödeme Yaptım (Test Bildirimi)');

        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
            'customer_note' => 'Başka bir isimden havale yapıldı.',
        ])->assertRedirect(route('checkout.success', $order))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payment_notifications', 1);
        $this->assertDatabaseHas('payment_notifications', [
            'order_id' => $order->id,
            'bank_account_id' => $bank->id,
            'status' => 'pending',
        ]);
        $this->assertSame('pending_payment', $order->fresh()->status);

        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect(route('checkout.success', $order));
        $this->assertDatabaseCount('payment_notifications', 1);
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Banka hareketleri kontrol ediliyor.');
    }

    public function test_claim_does_not_verify_itself_and_manual_order_status_bypass_fails(): void
    {
        $bank = $this->bank();
        $order = $this->order();
        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect();

        $notice = PaymentNotification::firstOrFail();
        $this->actingAs($this->administrator());
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'preparing',
        ])->assertSessionHasErrors('status');
        $this->post(route('admin.payment-notifications.verify', $notice), [])
            ->assertSessionHasErrors('bank_verified');

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->post(route('admin.payment-notifications.verify', $notice), [
            'bank_verified' => 1,
        ])->assertRedirect(route('admin.orders.show', $order))->assertSessionHasNoErrors();

        $this->assertSame('verified', $notice->fresh()->status);
        $this->assertSame('preparing', $order->fresh()->status);
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status'); // No silent cancellation without a refund workflow.
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertNotNull($notice->fresh()->reviewed_at);
        $this->assertNotNull($notice->fresh()->reviewed_by);
        $this->post(route('admin.payment-notifications.verify', $notice), [
            'bank_verified' => 1,
        ])->assertSessionHasErrors('payment');

        $this->get(route('admin.payment-notifications.index'))->assertOk()
            ->assertDontSee('Ödeme bildirimi kontrol bekliyor');
    }

    public function test_rejected_notification_preserves_audit_and_allows_one_new_claim(): void
    {
        $bank = $this->bank();
        $order = $this->order();
        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect();
        $notice = PaymentNotification::firstOrFail();

        $admin = $this->administrator();
        $this->actingAs($admin);
        $this->post(route('admin.payment-notifications.reject', $notice), [
            'admin_note' => 'Banka hesabına tutar ulaşmamış.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('rejected', $notice->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame($admin->id, $notice->fresh()->reviewed_by);

        // Simulate the same guest checkout session after an admin review.
        // Logging out through the UI invalidates the session by design.
        \Illuminate\Support\Facades\Auth::logout();
        $this->assertGuest();
        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect(route('checkout.success', $order));

        $this->assertDatabaseCount('payment_notifications', 2);
        $this->assertSame(1, PaymentNotification::query()->where('status', 'rejected')->count());
        $this->assertSame(1, PaymentNotification::query()->where('status', 'pending')->count());
    }

    public function test_other_session_or_inactive_bank_cannot_submit_payment_claim(): void
    {
        $bank = $this->bank();
        $order = $this->order();
        $this->withSession(['confirmed_order_ids' => [], 'checkout_last_order' => null]);
        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertNotFound();
        $this->assertDatabaseCount('payment_notifications', 0);

        $this->withSession(['confirmed_order_ids' => [$order->id]]);
        $bank->update(['is_active' => false]);
        $this->post(route('checkout.payment-notify', $order), [
            'bank_account_id' => $bank->id,
        ])->assertSessionHasErrors('bank_account_id');
        $this->assertDatabaseCount('payment_notifications', 0);
    }

    public function test_bank_accounts_are_hidden_without_activation_and_no_claim_form_is_shown(): void
    {
        $bank = $this->bank('TR330006100519786457841326', false);
        $order = $this->order();
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Banka hesapları henüz tanımlanmadı')
            ->assertDontSee($bank->iban);
    }
}
