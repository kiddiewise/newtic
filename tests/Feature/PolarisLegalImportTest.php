<?php

namespace Tests\Feature;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Support\LegalReadiness;
use App\Support\PolarisLegalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PolarisLegalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('newtic.checkout_test_mode', true);
    }

    public function test_cannot_import_on_an_unrelated_client_domain(): void
    {
        config()->set('app.url', 'https://sinaydesign.com');
        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertExitCode(1);
        $this->assertDatabaseCount('merchant_legal_pages', 0);
        $this->assertDatabaseCount('merchant_legal_profiles', 0);

        config()->set('app.url', 'https://polariskursun.com');
        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertExitCode(1);
        $this->assertDatabaseCount('merchant_legal_pages', 0);
    }

    public function test_a_non_test_payment_environment_never_issues_import(): void
    {
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        config()->set('newtic.checkout_test_mode', false);
        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertExitCode(1);
        $this->assertDatabaseCount('merchant_legal_pages', 0);
    }

    public function test_preview_does_not_modify_any_existing_data(): void
    {
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        $this->artisan('newtic:polaris-legal')->assertSuccessful();
        $this->assertDatabaseCount('merchant_legal_pages', 0);
        $this->assertDatabaseCount('merchant_legal_profiles', 0);
    }

    public function test_source_based_polaris_pages_are_published_and_appear_in_footer(): void
    {
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertSuccessful();

        $this->assertDatabaseCount('merchant_legal_profiles', 1);
        $this->assertDatabaseCount('merchant_legal_pages', count(PolarisLegalContent::pages()));
        $profile = MerchantLegalProfile::current();
        $this->assertSame(PolarisLegalContent::SELLER, $profile->legal_name);
        $this->assertSame(PolarisLegalContent::ADDRESS, $profile->registered_address);
        $this->assertSame(PolarisLegalContent::EMAIL, $profile->contact_email);
        $this->assertSame(PolarisLegalContent::PHONE, $profile->contact_phone);
        $this->assertNull($profile->tax_number);
        $this->assertNull($profile->mersis_number);
        $this->assertFalse($profile->show_paytr_disclosure);

        foreach (PolarisLegalContent::pages() as $slug => $page) {
            $record = MerchantLegalPage::published($slug);
            $this->assertNotNull($record, $slug);
            $this->assertSame(1, $record->version);
            $this->assertSame($page['title'], $record->title);
            $this->assertSame($page['body'], $record->body);
            $this->get(route('legal.show', $slug))->assertOk()->assertSee($page['title']);
        }

        $this->assertTrue(LegalReadiness::checkoutReady());
        $this->assertSame([], LegalReadiness::requiredBeforeLive());
        $this->get(route('home'))->assertOk()
            ->assertSee('Müşteri Hizmetleri')
            ->assertSee('Gizlilik Politikası')
            ->assertSee('Teslimat ve İade Şartları')
            ->assertSee('Çerez tercihleri');
    }

    public function test_existing_admin_content_and_any_tax_or_payment_disclosure_are_never_overwritten(): void
    {
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        $existing = MerchantLegalPage::query()->create([
            'slug' => 'hakkimizda',
            'title' => 'Yöneticinin Düzenlediği Metin',
            'body' => str_repeat('Manuel içerik. ', 40),
            'version' => 5,
            'is_published' => false,
        ]);
        MerchantLegalProfile::query()->create([
            'id' => 1,
            'legal_name' => 'Manuel Satıcı Unvanı',
            'tax_number' => '1234567890',
            'show_paytr_disclosure' => true,
        ]);
        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertSuccessful();
        $this->assertSame('Yöneticinin Düzenlediği Metin', $existing->fresh()->title);
        $this->assertSame(5, $existing->fresh()->version);
        $this->assertFalse($existing->fresh()->is_published);
        $profile = MerchantLegalProfile::current();
        $this->assertSame('Manuel Satıcı Unvanı', $profile->legal_name);
        $this->assertSame('1234567890', $profile->tax_number);
        $this->assertTrue($profile->show_paytr_disclosure);
        $this->assertSame(PolarisLegalContent::EMAIL, $profile->contact_email);

        $this->artisan('newtic:polaris-legal', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('merchant_legal_pages', count(PolarisLegalContent::pages()));
    }

    public function test_legal_texts_are_source_specific_and_no_fake_trust_or_contact_claims_exist(): void
    {
        $this->assertCount(10, PolarisLegalContent::pages());
        $profile = PolarisLegalContent::profile();
        $this->assertNull($profile['mersis_number']);
        $this->assertNull($profile['kep_address']);
        $this->assertNull($profile['tax_number']);
        foreach (PolarisLegalContent::pages() as $entry) {
            $this->assertGreaterThan(200, mb_strlen($entry['body']));
            $this->assertStringNotContainsString('sinaydesign.com', $entry['body']);
            $this->assertStringNotContainsString('powderblue-dinosaur', $entry['body']);
            $this->assertStringNotContainsString('ETBİS onaylı', $entry['body']);
        }
    }
}
