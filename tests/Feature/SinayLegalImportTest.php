<?php

namespace Tests\Feature;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Support\LegalReadiness;
use App\Support\SinayLegalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SinayLegalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('newtic.checkout_test_mode', true);
    }

    public function test_importer_is_isolated_to_sinay_store_only(): void
    {
        config()->set('app.url', 'https://polariskursun.com');
        $this->artisan('newtic:sinay-legal', ['--publish' => true])->assertExitCode(1);
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        $this->artisan('newtic:sinay-legal', ['--publish' => true])->assertExitCode(1);
        $this->assertDatabaseCount('merchant_legal_pages', 0);
    }

    public function test_dry_run_does_not_change_database(): void
    {
        config()->set('app.url', 'https://sinaydesign.com');
        $this->artisan('newtic:sinay-legal')->assertSuccessful();
        $this->assertDatabaseCount('merchant_legal_pages', 0);
        $this->assertDatabaseCount('merchant_legal_profiles', 0);
    }

    public function test_publish_complete_pages_without_fabricating_seller_identifiers_or_checkout_readiness(): void
    {
        config()->set('app.url', 'https://sinaydesign.com');
        $this->artisan('newtic:sinay-legal', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('merchant_legal_pages', 10);

        $profile = MerchantLegalProfile::current();
        $this->assertSame('Sinem Durak', $profile->legal_name);
        $this->assertNull($profile->registered_address);
        $this->assertNull($profile->contact_email);
        $this->assertNull($profile->contact_phone);
        $this->assertNull($profile->tax_number);
        $this->assertFalse($profile->show_paytr_disclosure);
        $this->assertFalse(LegalReadiness::checkoutReady());
        $this->assertNotEmpty(LegalReadiness::requiredBeforeLive());

        foreach (SinayLegalContent::pages() as $slug => $data) {
            $record = MerchantLegalPage::published($slug);
            $this->assertNotNull($record, $slug);
            $this->assertSame($data['title'], $record->title);
            $this->assertSame($data['body'], $record->body);
            $this->get(route('legal.show', $slug))->assertOk()->assertSee($data['title']);
        }
        $this->get(route('home'))->assertOk()
            ->assertSee('Gizlilik Politikası')
            ->assertSee('Teslimat, Cayma ve İade Koşulları');
    }

    public function test_admin_content_and_verified_business_information_are_never_replaced(): void
    {
        config()->set('app.url', 'https://sinaydesign.com');
        MerchantLegalProfile::create([
            'id' => 1,
            'legal_name' => 'Onaylanmış Ticari Ünvan',
            'registered_address' => 'Onaylı adres örneği',
            'contact_email' => 'support@example.test',
            'contact_phone' => '+90 212 000 00 00',
            'tax_number' => '1234567890',
            'show_paytr_disclosure' => true,
        ]);
        MerchantLegalPage::create([
            'slug' => 'teslimat-ve-iade',
            'title' => 'Özel mağaza düzenlemesi',
            'body' => str_repeat('Mevcut idari metin. ', 20),
            'version' => 5,
            'is_published' => false,
        ]);
        $this->artisan('newtic:sinay-legal', ['--publish' => true])->assertSuccessful();
        $this->assertSame('Onaylanmış Ticari Ünvan', MerchantLegalProfile::current()->legal_name);
        $this->assertSame('1234567890', MerchantLegalProfile::current()->tax_number);
        $this->assertTrue(MerchantLegalProfile::current()->show_paytr_disclosure);
        $this->assertSame('Özel mağaza düzenlemesi', MerchantLegalPage::where('slug', 'teslimat-ve-iade')->first()->title);
        $this->assertSame(5, MerchantLegalPage::where('slug', 'teslimat-ve-iade')->first()->version);
        $this->artisan('newtic:sinay-legal', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('merchant_legal_pages', 10);
    }

    public function test_materials_are_not_earring_specific_and_respect_consumer_rights(): void
    {
        $this->assertCount(10, SinayLegalContent::pages());
        foreach (SinayLegalContent::pages() as $slug => $page) {
            $this->assertGreaterThan(200, mb_strlen($page['body']));
            $this->assertStringNotContainsString('küpe', mb_strtolower($page['body']), $slug);
            $this->assertStringNotContainsString('polariskursun', mb_strtolower($page['body']), $slug);
            $this->assertStringNotContainsString('iade kabul edilmez', mb_strtolower($page['body']), $slug);
        }
        $this->assertStringContainsString('14 gün', SinayLegalContent::pages()['teslimat-ve-iade']['body']);
        $this->assertStringContainsString('ayıplı', SinayLegalContent::pages()['mesafeli-satis-sozlesmesi']['body']);
        $this->assertStringContainsString('fotoğraf', SinayLegalContent::pages()['kvkk-aydinlatma']['body']);
    }
}
