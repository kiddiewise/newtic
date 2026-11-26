<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdmSetting;
use App\Services\Invoices\EdmTestClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

final class EdmSettingsController extends Controller
{
    public function edit(): Response
    {
        return $this->renderSettings();
    }

    private function renderSettings(?array $lookup = null, ?string $kind = null): Response
    {
        $settings = EdmSetting::query()->find(1);
        return response()->view('admin.edm-settings', [
            'lookup'=>$lookup,'lookupKind'=>$kind,
            'configured'=>$settings?->configured() ?? false,
            'status'=>$settings?->probe_status,
            'counter'=>$settings?->counter_left,
            'checkedAt'=>$settings?->checked_at,
            'endpoint'=>EdmTestClient::ENDPOINT,
        ])->header('Cache-Control','private, no-store, max-age=0');
    }

    public function lookup(Request $request, EdmTestClient $client): Response|RedirectResponse
    {
        if ($request->input('kind') === 'routing_preview') return $this->routingPreview($request);
        if ($request->input('kind') === 'invoice_evidence') return $this->invoiceEvidence($request,$client);
        if ($request->input('kind') === 'invoice_status') return $this->invoiceStatus($request,$client);
        $validator = Validator::make($request->only('edm_identifier','kind'), [
            'edm_identifier'=>['required','string','regex:/^[0-9]{10,11}$/D','not_in:11111111111'],
            'kind'=>['required','in:company,taxpayer'],
        ]);
        if ($validator->fails()) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Geçerli bir 10 veya 11 haneli vergi/kimlik numarası girin.']);
        $data = $validator->validated();
        $settings = EdmSetting::query()->find(1);
        if (!$settings?->configured() || !$settings->revision || $settings->probe_status !== 'ok') return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Önce test bağlantısını doğrulayın.']);
        try {
            $result = $data['kind'] === 'company'
                ? $client->company($settings->username,$settings->password,$data['edm_identifier'])
                : $client->taxpayer($settings->username,$settings->password,$data['edm_identifier']);
        } catch (Throwable) {
            $result = ['status'=>'failed','data'=>null];
        }
        if (!EdmSetting::query()->whereKey(1)->where('revision',$settings->revision)->exists()) {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Hesap değişti. Sorgulamayı yeniden yapın.']);
        }
        // Return a private, non-cacheable result directly. No session flash,
        // database persistence or query-string recipient identifier.
        return $this->renderSettings($result,$data['kind']);
    }

    private function invoiceStatus(Request $request, EdmTestClient $client): Response|RedirectResponse
    {
        $validator=Validator::make($request->only('edm_uuid','edm_profile'),[
            'edm_uuid'=>['required','string','regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD'],
            'edm_profile'=>['required','in:TEMELFATURA,TICARIFATURA,EARSIVFATURA'],
        ]);
        if ($validator->fails()) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Geçerli ETTN ve belge senaryosu girin.']);
        $settings=EdmSetting::query()->find(1);
        if (!$settings?->configured() || !$settings->revision || $settings->probe_status!=='ok') {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Önce test bağlantısını doğrulayın.']);
        }
        $data=$validator->validated();
        try {
            $result=$client->invoiceStatus($settings->username,$settings->password,strtolower($data['edm_uuid']),$data['edm_profile']);
        } catch (Throwable) {
            $result=['status'=>'failed','data'=>null];
        }
        if (!EdmSetting::query()->whereKey(1)->where('revision',$settings->revision)->exists()) {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Hesap değişti. Sorgulamayı yeniden yapın.']);
        }
        return $this->renderSettings($result,'invoice_status');
    }

    private function routingPreview(Request $request): Response|RedirectResponse
    {
        $validator=Validator::make($request->only('seller_id','buyer_id','sender_alias','receiver_alias','series','year'),[
            'seller_id'=>['required','string','regex:/^[0-9]{10,11}$/D','not_in:11111111111'],
            'buyer_id'=>['required','string','regex:/^[0-9]{10,11}$/D','not_in:11111111111'],
            'sender_alias'=>['required','string','regex:/^urn:mail:[^\s<>]{1,250}$/Di'],
            'receiver_alias'=>['required','string','regex:/^urn:mail:[^\s<>]{1,250}$/Di'],
            'series'=>['required','string','regex:/^[A-Z0-9]{3}$/D','not_in:ABC,GIB'],
            'year'=>['required','integer','between:2000,2099'],
        ]);
        if ($validator->fails()) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Geçerli taraf kimlikleri, etiketler ve üç karakterli e-Fatura serisi girin.']);
        $settings=EdmSetting::query()->find(1);
        if (!$settings?->configured() || !$settings->revision || $settings->probe_status!=='ok') {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Önce test bağlantısını doğrulayın.']);
        }
        $d=$validator->validated();
        try {
            $data=app(\App\Services\Invoices\EdmRoutingPreview::class)->inspect($settings,$d['seller_id'],$d['buyer_id'],
                $d['sender_alias'],$d['receiver_alias'],$d['series'],(int)$d['year']);
            $result=['status'=>'ok','data'=>$data];
        } catch (Throwable) {
            $result=['status'=>'failed','data'=>null];
        }
        if (!EdmSetting::query()->whereKey(1)->where('revision',$settings->revision)->where('probe_status','ok')->exists()) {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Hesap değişti. Sorgulamayı yeniden yapın.']);
        }
        return $this->renderSettings($result,'routing_preview');
    }

    private function invoiceEvidence(Request $request,EdmTestClient $client): Response|RedirectResponse
    {
        $validator=Validator::make($request->only('edm_uuid','edm_profile','seller_id','buyer_id'),[
            'edm_uuid'=>['required','string','regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD'],
            'edm_profile'=>['required','in:TEMELFATURA,TICARIFATURA,EARSIVFATURA'],
            'seller_id'=>['required','string','regex:/^[0-9]{10,11}$/D','not_in:11111111111'],
            'buyer_id'=>['required','string','regex:/^[0-9]{10,11}$/D'],
        ]);
        if ($validator->fails()) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Geçerli ETTN, taraf kimlikleri ve belge senaryosu girin.']);
        $settings=EdmSetting::query()->find(1);
        if (!$settings?->configured() || !$settings->revision || $settings->probe_status!=='ok') {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Önce test bağlantısını doğrulayın.']);
        }
        $d=$validator->validated();
        try {
            $result=$client->invoiceXmlEvidence($settings->username,$settings->password,strtolower($d['edm_uuid']),$d['edm_profile'],$d['seller_id'],$d['buyer_id']);
        } catch (Throwable) {
            $result=['status'=>'failed','data'=>null];
        }
        if (!EdmSetting::query()->whereKey(1)->where('revision',$settings->revision)->where('probe_status','ok')->exists()) {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Hesap değişti. Sorgulamayı yeniden yapın.']);
        }
        return $this->renderSettings($result,'invoice_evidence');
    }

    public function update(Request $request): RedirectResponse
    {
        // Always rotate both fields together; no credentials in old input.
        $validator = Validator::make($request->only('edm_username','edm_password'), [
            'edm_username'=>['required','string','max:255'],
            'edm_password'=>['required','string','max:1024'],
        ]);
        if ($validator->fails()) return redirect()->route('admin.edm.edit')->withErrors($validator);
        $data = $validator->validated();
        try {
            DB::transaction(function () use ($data): void {
                EdmSetting::query()->firstOrCreate(['id'=>1]);
                EdmSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail()->forceFill([
                    'username'=>$data['edm_username'],
                    'password'=>$data['edm_password'],
                    'revision'=>(string)Str::uuid(),
                    'probe_status'=>null,'counter_left'=>null,'checked_at'=>null,
                ])->save();
            }, 3);
        } catch (Throwable) {
            return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Bağlantı bilgileri güvenli şekilde kaydedilemedi.']);
        }
        return redirect()->route('admin.edm.edit')->with('success','Test hesabı kaydedildi. Bağlantıyı test edebilirsiniz.');
    }

    public function probe(EdmTestClient $client): RedirectResponse
    {
        $settings = EdmSetting::query()->find(1);
        if (!$settings?->configured() || !$settings->revision) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Önce test hesabınızı kaydedin.']);
        try {
            $result = $client->probe($settings->username,$settings->password);
        } catch (Throwable) {
            $result = ['status'=>'failed','counter'=>null];
        }
        // Do not publish a stale probe for credentials rotated during the call.
        $updated = EdmSetting::query()->whereKey(1)->where('revision',$settings->revision)->update([
            'probe_status'=>$result['status'],
            'counter_left'=>$result['counter'],
            'checked_at'=>now(),
        ]);
        if (!$updated) return redirect()->route('admin.edm.edit')->withErrors(['edm'=>'Hesap değişti. Bağlantıyı yeniden test edin.']);
        return redirect()->route('admin.edm.edit')->with('success','Bağlantı kontrolü tamamlandı. Sonucu aşağıda görebilirsiniz.');
    }
}
