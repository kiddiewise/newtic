<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\LegalReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MerchantLegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function publish(string $slug, ?string $body = null): MerchantLegalPage
    {
        return MerchantLegalPage::query()->create([
            'slug' => $slug,
            'title' => MerchantLegalPage::LABELS[$slug],
            'body' => $body ?? str_repeat("Bu mağazanın ürün ve tüketici bilgilendirmeleri günceldir. ", 8),
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function seller(): void
    {
        MerchantLegalProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Örnek Ticaret Limited Şirketi',
            'registered_address' => 'İzmir, Türkiye · Adres örneği',
            'contact_email' => 'iletisim@example.test',
            'contact_phone' => '+90 232 000 00 00',
        ]);
    }

    private function paymentFixture(): Product
    {
        BankAccount::create([
            'bank_name' => 'Örnek Banka',
            'account_holder' => 'Örnek Ticaret Limited Şirketi',
            'iban' => 'TR330006100519786457841326',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Vazo', 'slug' => 'vazo', 'is_active' => true]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Seramik Vazo',
            'sku' => 'VZ-001',
            'slug' => 'seramik-vazo',
            'price' => '140.00',
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
    }

    private function checkoutPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'email' => 'customer@example.test',
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'İzmir Atatürk Mahallesi, Sokak 12',
            'invoice_type' => 'individual',
        ], $overrides);
    }

    public function test_fresh_store_has_no_fake_legal_text_or_pretend_payment_badges(): void
    {
        $this->assertDatabaseCount('merchant_legal_pages', 0);
        $this->assertFalse(LegalReadiness::checkoutReady());
        $this->assertNotEmpty(LegalReadiness::requiredBeforeLive());
        $this->get(route('legal.show', 'mesafeli-satis-sozlesmesi'))->assertNotFound();
        $this->get(route('legal.show', 'kvkk-aydinlatma'))->assertNotFound();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Çerez tercihleri', $html);
        $this->assertStringContainsString('Yardım Merkezi', $html);
        $this->assertStringNotContainsString('POLARIS</strong><small>KURŞUN', $html);
        $this->assertStringNotContainsString('TR GO', $html);
        $this->assertStringNotContainsString('VISA</span>', $html);
    }

    public function test_non_admin_cannot_publish_policy_or_seller_info(): void
    {
        $this->patch(route('admin.legal.page', 'gizlilik-politikasi'), [
            'title' => 'Gizlilik', 'body' => str_repeat('A', 240), 'is_published' => 1,
        ])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.legal.index'))->assertForbidden();
        $this->patch(route('admin.legal.profile'), [
            'legal_name' => 'Yetkisiz', 'show_paytr_disclosure' => 0,
        ])->assertForbidden();
        $this->assertDatabaseCount('merchant_legal_pages', 0);
        $this->assertDatabaseCount('merchant_legal_profiles', 0);
    }

    public function test_admin_publishes_only_reviewed_long_text_and_versions_are_preserved(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.legal.index'))->assertOk()->assertSee('Yasal sayfalar');
        $this->patch(route('admin.legal.page', 'gizlilik-politikasi'), [
            'title' => 'Gizlilik Politikası', 'body' => 'Kısa.', 'is_published' => 1,
        ])->assertSessionHasErrors('body');

        $body = str_repeat('Bu mağazaya özel gizlilik ve veri güvenliği bilgisi. ', 8);
        $this->patch(route('admin.legal.page', 'gizlilik-politikasi'), [
            'title' => 'Gizlilik Politikası', 'body' => $body, 'is_published' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $page = MerchantLegalPage::published('gizlilik-politikasi');
        $this->assertSame(1, $page->version);
        $hash = $page->revisionHash();

        $this->patch(route('admin.legal.page', 'gizlilik-politikasi'), [
            'title' => 'Gizlilik Politikası', 'body' => $body.' Yenilendi.', 'is_published' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $page->fresh()->version);
        $this->assertNotSame($hash, $page->fresh()->revisionHash());
        $this->get(route('legal.show', 'gizlilik-politikasi'))->assertOk()->assertSee('Yenilendi.');
    }

    public function test_legal_document_is_html_escaped_not_executed(): void
    {
        $this->publish('hakkimizda', str_repeat('Firma hakkında açıklama. ', 12).'<script>alert(1)</script>');
        $this->get(route('legal.show', 'hakkimizda'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_lawful_checkout_is_blocked_on_live_store_until_identity_and_pages_exist(): void
    {
        config()->set('newtic.checkout_test_mode', false);
        $product = $this->paymentFixture();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.place'), $this->checkoutPayload((string) session('checkout_token')))
            ->assertSessionHasErrors('legal_acknowledged');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_published_contract_is_acknowledged_with_exact_snapshot_before_order_is_saved(): void
    {
        $this->seller();
        $contract = $this->publish('mesafeli-satis-sozlesmesi');
        $this->publish('on-bilgilendirme-formu');
        $product = $this->paymentFixture();

        $this->assertTrue(LegalReadiness::checkoutReady());
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();
        $html = $this->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertStringContainsString('legal_acknowledged', $html);
        $this->assertStringContainsString('Ön Bilgilendirme Formu', $html);
        $token = (string) session('checkout_token');
        $revision = LegalReadiness::checkoutSignature();

        $this->post(route('checkout.place'), $this->checkoutPayload($token))->assertSessionHasErrors('legal_acknowledged');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.place'), $this->checkoutPayload($token, [
            'legal_acknowledged' => 1, 'legal_revision' => str_repeat('0', 64),
        ]))->assertSessionHasErrors('legal_acknowledged');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.place'), $this->checkoutPayload($token, [
            'legal_acknowledged' => 1, 'legal_revision' => $revision,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $snapshot = $order->legal_acknowledgement;
        $this->assertSame(28000, $snapshot['financial_summary']['subtotal_cents']);
        $this->assertSame($order->total_cents, $snapshot['financial_summary']['total_cents']);
        $this->assertSame($contract->revisionHash(), $snapshot['documents']['mesafeli-satis-sozlesmesi']['sha256']);
        $this->assertSame('Örnek Ticaret Limited Şirketi', $snapshot['seller']['legal_name']);

        $contract->update(['body' => str_repeat('Yeni metin. ', 40), 'version' => 2]);
        $this->assertSame(1, $order->fresh()->legal_acknowledgement['documents']['mesafeli-satis-sozlesmesi']['version']);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_published_membership_terms_are_optional_for_guest_but_required_on_registration(): void
    {
        $terms = $this->publish('uyelik-sozlesmesi');
        $this->publish('kvkk-aydinlatma');
        $this->get(route('account.register'))->assertOk()->assertSee('KVKK Aydınlatma Metni')
            ->assertSee('membership_accepted');

        $payload = [
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.test',
            'password' => 'VerySecure987',
            'password_confirmation' => 'VerySecure987',
        ];

        $this->post(route('account.register.submit'), $payload)->assertSessionHasErrors('membership_accepted');
        $this->assertDatabaseCount('users', 0);

        $this->post(route('account.register.submit'), $payload + [
            'membership_accepted' => 1, 'membership_revision' => $terms->revisionHash(),
        ])->assertRedirect(route('account.index'))->assertSessionHasNoErrors();

        $user = User::firstOrFail();
        $this->assertSame($terms->revisionHash(), $user->legal_notice['sha256']);
        $this->assertDatabaseCount('marketing_contacts', 0);
    }
}
