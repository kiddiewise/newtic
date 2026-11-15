<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Models\MarketingContact;
use App\Models\MarketingSetting;
use App\Models\User;
use App\Services\EmailTransport;
use App\Services\MarketingCampaignMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MarketingCampaignController extends Controller
{
    public function index(): View
    {
        return view('admin.marketing.index', [
            'campaigns'=>MarketingCampaign::query()->withCount([
                'deliveries as recipients_count',
                'deliveries as sent_count'=>fn($q)=>$q->where('status','sent'),
                'deliveries as failed_count'=>fn($q)=>$q->where('status','failed'),
                'deliveries as skipped_count'=>fn($q)=>$q->where('status','skipped'),
            ])->latest('id')->paginate(15),
            'eligibleCount'=>MarketingContact::query()->eligible()->count(),
            'settings'=>MarketingSetting::current(),
        ]);
    }

    public function create(): View
    {
        return view('admin.marketing.edit', ['campaign'=>new MarketingCampaign([
            'status'=>'draft',
        ]),'recipients'=>collect(),'report'=>[]]);
    }

    public function edit(MarketingCampaign $campaign): View
    {
        return view('admin.marketing.edit', [
            'campaign'=>$campaign,
            'recipients'=>$campaign->deliveries()->with('contact:id,email')->latest('id')->paginate(20),
            'report'=>$campaign->deliveries()->selectRaw('status,COUNT(*) as total')
                ->groupBy('status')->pluck('total','status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validateCampaign($request);
        $newBanner = $request->file('banner')?->store('campaigns', 'public');
        try {
            $campaign = MarketingCampaign::create([
                'created_by'=>$request->user()->id,
                'title'=>trim($data['title']),
                'body'=>trim($data['body']),
                'button_text'=>trim((string)($data['button_text'] ?? '')) ?: null,
                'button_url'=>trim((string)($data['button_url'] ?? '')) ?: null,
                'banner_path'=>$newBanner ? 'storage/'.$newBanner : null,
                'status'=>'draft',
            ]);
        } catch (\Throwable $e) {
            if ($newBanner) Storage::disk('public')->delete($newBanner);
            throw $e;
        }
        return redirect()->route('admin.marketing.edit',$campaign)->with('success','Kampanya taslağı kaydedildi. Henüz e-posta gönderilmedi.');
    }

    public function update(Request $request, MarketingCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status==='draft',409);
        $data=$this->validateCampaign($request);
        $newBanner=$request->file('banner')?->store('campaigns','public');
        $old=$campaign->banner_path;
        try {
            $campaign->update([
                'title'=>trim($data['title']),
                'body'=>trim($data['body']),
                'button_text'=>trim((string)($data['button_text'] ?? '')) ?: null,
                'button_url'=>trim((string)($data['button_url'] ?? '')) ?: null,
                'banner_path'=>$newBanner ? 'storage/'.$newBanner
                    : ($request->boolean('remove_banner') ? null : $old),
            ]);
        } catch (\Throwable $e) {
            if ($newBanner) Storage::disk('public')->delete($newBanner);
            throw $e;
        }
        if (($newBanner || $request->boolean('remove_banner')) && str_starts_with((string)$old,'storage/campaigns/')) {
            Storage::disk('public')->delete(substr($old,8));
        }
        return back()->with('success','Taslak güncellendi.');
    }

    public function preview(MarketingCampaign $campaign, MarketingCampaignMailer $composer): View
    {
        return view('emails.marketing-campaign', ['mailData'=>$composer->payload($campaign)]);
    }

    public function test(Request $request, MarketingCampaign $campaign, MarketingCampaignMailer $composer, EmailTransport $transport): RedirectResponse
    {
        $settings=EmailSetting::current();
        if (!$settings->active()) {
            return back()->withErrors(['email'=>'Önce E-posta ve Bildirimler bölümünde SMTP testini tamamlayıp göndermeyi açın.']);
        }
        // No campaign contact is addressed, never a customer.
        try {
            $target=EmailSetting::previewEnvironment() ? (string)$settings->test_recipient
                : (string)$request->user()->email;
            $payload=$composer->payload($campaign);
            $payload['subject']='[NEWTIC TEST] '.$payload['subject'];
            $transport->send($settings,$target,$payload);
        } catch (\Throwable) {
            return back()->withErrors(['email'=>'Kampanya test iletisi gönderilemedi. SMTP ayarlarını kontrol edin.']);
        }
        return back()->with('success','Kampanya testi yalnızca güvenli test adresine gönderildi. Gerçek alıcılara bildirim yapılmadı.');
    }

    public function launch(Request $request, MarketingCampaign $campaign): RedirectResponse
    {
        $request->validate([
            'confirm_compliance'=>['required','accepted'],
            'confirm_send'=>['required','accepted'],
        ]);
        if (!EmailSetting::current()->active()) {
            return back()->withErrors(['email'=>'SMTP bildirim merkezi etkin ve doğrulanmış olmalıdır.']);
        }
        if (!MarketingSetting::current()->ready()) {
            return back()->withErrors(['marketing'=>'İYS kayıt beyanı ve kurumsal kimlik bilgileri tamamlanmadan kampanya gönderilemez.']);
        }
        if (!EmailSetting::previewEnvironment() && !config('newtic.marketing_live_enabled',false)) {
            return back()->withErrors(['marketing'=>'Canlı kampanya izni sunucu yapılandırmasında kapalı. İYS doğrulaması ve gönderici limitleri kontrol edilmelidir.']);
        }
        if (!MarketingContact::query()->eligible()->exists()) {
            return back()->withErrors(['marketing'=>'İYS doğrulanmış, izinli bir alıcı bulunamadı. Üyeleri otomatik aboneliğe eklemiyoruz.']);
        }
        DB::transaction(function () use($campaign) {
            $locked=MarketingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='draft') throw ValidationException::withMessages([
                'marketing'=>'Bu kampanya zaten başlatılmış; tekrar gönderim oluşturulmadı.',
            ]);
            $locked->update(['status'=>'queuing','launched_at'=>now(),'last_contact_id'=>0]);
        },3);
        return back()->with('success','Kampanya izinli alıcılar için gönderim kuyruğuna alındı. Yönetim panelinden durdurabilirsiniz.');
    }

    public function pause(MarketingCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use($campaign) {
            $locked=MarketingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status,['queuing','sending'],true)) return;
            $locked->update(['resume_status'=>$locked->status,'status'=>'paused']);
        });
        return back()->with('success','Kampanya duraklatıldı; sıradaki mesajlar işlenmeyecek.');
    }

    public function resume(MarketingCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use($campaign) {
            $locked=MarketingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->status!=='paused') return;
            $locked->update(['status'=>in_array($locked->resume_status,['queuing','sending'],true)
                ? $locked->resume_status : 'sending','resume_status'=>null]);
        });
        return back()->with('success','Kampanya yeniden başlatıldı.');
    }

    public function cancel(MarketingCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use($campaign) {
            $locked=MarketingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status,['queuing','sending','paused'],true)) return;
            $locked->update(['status'=>'cancelled','finished_at'=>now(),'resume_status'=>null]);
            $locked->deliveries()->whereIn('status',['pending','retry'])->update([
                'status'=>'skipped','last_error'=>'CampaignCancelled',
            ]);
        });
        return back()->with('success','Kampanya iptal edildi. Gönderilmiş e-postalar geri alınamaz.');
    }

    public function contacts(): View
    {
        return view('admin.marketing.contacts',[
            'contacts'=>MarketingContact::query()->latest('id')->paginate(20),
            'eligibleCount'=>MarketingContact::query()->eligible()->count(),
            'settings'=>MarketingSetting::current(),
        ]);
    }

    public function saveContact(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'email'=>['required','email','max:255'],
            'name'=>['nullable','string','max:160'],
            'consent_source'=>['required',Rule::in(['document','iys','external_record'])],
            'consent_proof'=>['required','string','min:12','max:2000'],
            'consent_at'=>['required','date','before_or_equal:now'],
            'iys_reference'=>['required','string','min:3','max:190'],
            'iys_verified'=>['required','accepted'],
        ]);
        $email=mb_strtolower(trim($data['email']));
        $existing=MarketingContact::query()->where('email',$email)->first();
        if ($existing?->status==='unsubscribed') {
            throw ValidationException::withMessages(['email'=>'Bu adres abonelikten çıkmış. Yönetici tarafından zorla tekrar abone yapılamaz; yeni açık onay alınmalıdır.']);
        }
        $user=User::query()->whereRaw('LOWER(email) = ?',[$email])->first();
        MarketingContact::query()->updateOrCreate(['email'=>$email],[
            'user_id'=>$user?->id, 'name'=>$data['name']??$user?->name,
            'status'=>'approved', 'consent_source'=>$data['consent_source'],
            'consent_proof'=>trim($data['consent_proof']),
            'consent_at'=>$data['consent_at'],
            'iys_reference'=>trim($data['iys_reference']),
            'iys_confirmed_at'=>now(), 'unsubscribed_at'=>null,
        ]);
        return back()->with('success','Alıcı, onay kanıtı ve manuel İYS doğrulama beyanıyla kaydedildi.');
    }

    public function suppress(MarketingContact $contact): RedirectResponse
    {
        $contact->update(['status'=>'suppressed','iys_confirmed_at'=>null]);
        return back()->with('success','Alıcı sonraki kampanyalardan çıkarıldı. Mevcut kuyruğa da gönderilmeyecek.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'business_name'=>['required','string','max:180'],
            'business_identifier'=>['required','string','max:100'],
            'contact_email'=>['required','email','max:255'],
            'business_address'=>['required','string','min:12','max:2000'],
            'iys_service_provider_id'=>['required','string','min:3','max:120'],
            'iys_checked'=>['required','accepted'],
            'enabled'=>['required','boolean'],
        ]);
        MarketingSetting::current()->update([
            'business_name'=>$data['business_name'],
            'business_identifier'=>$data['business_identifier'],
            'contact_email'=>$data['contact_email'],
            'business_address'=>$data['business_address'],
            'iys_service_provider_id'=>$data['iys_service_provider_id'],
            'iys_checked_at'=>now(),
            'enabled'=>(bool)$data['enabled'],
        ]);
        return back()->with('success','Kurumsal ve İYS beyanları kaydedildi. Bu form gerçek İYS API kontrolü yapmaz.');
    }

    private function validateCampaign(Request $request): array
    {
        $data=$request->validate([
            'title'=>['required','string','min:5','max:180'],
            'body'=>['required','string','min:15','max:15000'],
            'button_text'=>['nullable','string','max:70','required_with:button_url'],
            'button_url'=>['nullable','url','starts_with:https://','max:2000','required_with:button_text'],
            'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096','dimensions:max_width=4000,max_height=4000'],
            'remove_banner'=>['nullable','boolean'],
        ]);
        if (isset($data['button_url']) && !filter_var($data['button_url'],FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['button_url'=>'HTTPS bağlantısı geçersiz.']);
        }
        return $data;
    }
}
