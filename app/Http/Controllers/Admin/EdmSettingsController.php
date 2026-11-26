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
        $settings = EdmSetting::query()->find(1);
        return response()->view('admin.edm-settings', [
            'configured'=>$settings?->configured() ?? false,
            'status'=>$settings?->probe_status,
            'counter'=>$settings?->counter_left,
            'checkedAt'=>$settings?->checked_at,
            'endpoint'=>EdmTestClient::ENDPOINT,
        ])->header('Cache-Control','private, no-store, max-age=0');
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
