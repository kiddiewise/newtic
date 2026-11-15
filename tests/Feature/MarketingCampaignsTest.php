<?php

namespace Tests\Feature;

use App\Models\EmailSetting;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Models\MarketingContact;
use App\Models\MarketingSetting;
use App\Models\User;
use App\Services\EmailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketingCampaignsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['is_admin'=>true])->save();
        return $user;
    }

    private function ready(): void
    {
        EmailSetting::current()->update([
            'enabled'=>true,
            'smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_scheme'=>'smtp',
            'smtp_username'=>'n@example.com','smtp_password'=>'test-secret',
            'from_address'=>'n@example.com','from_name'=>'Test Magaza',
            'test_recipient'=>'isolated@example.com','verified_at'=>now(),
            'events'=>EmailSetting::defaultEvents(),
        ]);
        MarketingSetting::current()->update([
            'business_name'=>'Örnek Şirket',
            'business_identifier'=>'MERSIS-TEST-010101',
            'contact_email'=>'destek@example.com',
            'business_address'=>'Örnek Mahallesi 10, 35000 İzmir',
            'iys_service_provider_id'=>'IYS-123456',
            'iys_checked_at'=>now(),
            'enabled'=>true,
        ]);
    }

    private function campaign(): MarketingCampaign
    {
        return MarketingCampaign::query()->create([
            'title'=>'Kasım sezonu fırsatları başladı',
            'body'=>'Müşterilerimiz için seçtiğimiz ürünler ve yeni sezon detayları.',
            'button_text'=>'Kampanyayı aç',
            'button_url'=>'https://example.com/firsatlar',
            'status'=>'draft',
        ]);
    }

    private function contact(string $email, bool $approved = false): MarketingContact
    {
        return MarketingContact::query()->create([
            'email'=>$email, 'name'=>'Alıcı',
            'status'=>$approved?'approved':'pending',
            'consent_source'=>'iys','consent_proof'=>'Reference dated consent record 123456',
            'consent_at'=>now()->subDay(),
            'iys_reference'=>$approved?'IYS-REGISTER-123':null,
            'iys_confirmed_at'=>$approved?now()->subMinute():null,
        ]);
    }

    public function test_marketing_pages_are_admin_only_and_sending_is_disabled_by_default(): void
    {
        $this->get(route('admin.marketing.index'))->assertRedirect(route('admin.login'));
        $user=User::factory()->create();
        $this->actingAs($user)->get(route('admin.marketing.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.marketing.index'))->assertOk();
        $campaign=$this->campaign();
        $this->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasErrors();
        $this->assertSame('draft',$campaign->fresh()->status);
    }

    public function test_admin_can_create_escaped_bannerless_campaign_and_preview_without_sending(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.marketing.store'),[
            'title'=>'Yeni sezon kampanyası',
            'body'=>'Kampanya açıklaması <script>alert("bad")</script> yazısı burada.',
            'button_text'=>'Ürünleri gör',
            'button_url'=>'https://example.com/urunler',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $campaign=MarketingCampaign::firstOrFail();
        $this->get(route('admin.marketing.preview',$campaign))->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("bad")</script>',false);
        $this->assertSame('draft',$campaign->status);
        $this->assertDatabaseCount('marketing_campaign_deliveries',0);
    }

    public function test_unapproved_contacts_are_excluded_even_when_registered_customers(): void
    {
        $this->ready();
        $this->contact('buyer@example.com',false);
        $this->actingAs($this->admin());
        $campaign=$this->campaign();
        $this->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasErrors('marketing');
        $this->assertDatabaseCount('marketing_campaign_deliveries',0);
        $this->assertSame('draft',$campaign->fresh()->status);
    }

    public function test_approved_contacts_send_once_only_to_preview_inbox_and_retries_do_not_duplicate(): void
    {
        $this->ready();
        $this->contact('buyer1@example.com',true);
        $this->contact('buyer2@example.com',true);
        $campaign=$this->campaign();
        $this->actingAs($this->admin())->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasNoErrors();
        $transport=$this->mock(EmailTransport::class);
        $transport->shouldReceive('send')->twice()->withArgs(
            fn($settings,$email,$payload)=>$settings instanceof EmailSetting
                && str_ends_with($email,'@example.com')
                && ($payload['template']??null)==='campaign'
                && !empty($payload['unsubscribe_url'])
        )->andReturnNull();

        $this->artisan('marketing:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame(2,MarketingCampaignDelivery::where('status','sent')->count());
        $this->assertSame('completed',$campaign->fresh()->status);
        $this->artisan('marketing:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame(2,MarketingCampaignDelivery::where('status','sent')->count());
        $this->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasErrors('marketing');
        $this->assertSame('isolated@example.com',EmailSetting::current()->safeRecipient('buyer1@example.com'));
    }

    public function test_unsubscribe_immediately_suppresses_queued_campaign_and_does_not_affect_orders(): void
    {
        $this->ready();
        $contact=$this->contact('buyer@example.com',true);
        $campaign=$this->campaign();
        $this->actingAs($this->admin())->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasNoErrors();
        $this->post(route('marketing.unsubscribe',$contact->unsubscribe_token))
            ->assertRedirect(route('marketing.unsubscribe.show',$contact->unsubscribe_token));
        $this->assertNotNull($contact->fresh()->unsubscribed_at);
        $this->artisan('marketing:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame(0,MarketingCampaignDelivery::where('status','sent')->count());
        $this->actingAs($this->admin())->post(route('admin.marketing.contact.store'),[
            'email'=>$contact->email,
            'consent_source'=>'iys',
            'consent_proof'=>'Old consent record should never revive opt out',
            'consent_at'=>now()->subDay()->format('Y-m-d\TH:i'),
            'iys_reference'=>'IYS-123','iys_verified'=>1,
        ])->assertSessionHasErrors('email');
    }

    public function test_paused_campaign_sends_nothing_until_resumed(): void
    {
        $this->ready();
        $this->contact('buyer@example.com',true);
        $campaign=$this->campaign();
        $this->actingAs($this->admin());
        $this->post(route('admin.marketing.launch',$campaign),[
            'confirm_compliance'=>1,'confirm_send'=>1,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.marketing.pause',$campaign))->assertSessionHasNoErrors();
        $this->artisan('marketing:process',['--limit'=>10])->assertExitCode(0);
        $this->assertDatabaseCount('marketing_campaign_deliveries',0);
        $this->post(route('admin.marketing.resume',$campaign))->assertSessionHasNoErrors();
        $transport=$this->mock(EmailTransport::class);
        $transport->shouldReceive('send')->once();
        $this->artisan('marketing:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame('completed',$campaign->fresh()->status);
    }

    public function test_guest_checkout_cannot_restore_an_address_that_unsubscribed(): void
    {
        $contact = $this->contact('withdrawn@example.com',true);
        $service = app(\App\Services\MarketingConsentService::class);
        $service->revoke($contact);
        $service->request($contact->email,'checkout_optional_consent',null);
        $this->assertNotNull($contact->fresh()->unsubscribed_at);
        $this->assertSame('unsubscribed',$contact->fresh()->status);
    }

    public function test_optional_account_opt_in_is_pending_until_iys_is_confirmed(): void
    {
        $this->post(route('account.register.submit'),[
            'name'=>'İzinli Müşteri','email'=>'buyer@example.com',
            'password'=>'SomePassword2026','password_confirmation'=>'SomePassword2026',
            'marketing_opt_in'=>1,
        ])->assertSessionHasNoErrors();
        $contact=MarketingContact::where('email','buyer@example.com')->firstOrFail();
        $this->assertSame('pending',$contact->status);
        $this->assertFalse($contact->eligibleToSend());
        $this->post(route('account.marketing.preference'),['subscribe'=>0])->assertSessionHasNoErrors();
        $this->assertNotNull($contact->fresh()->unsubscribed_at);
    }
}
