<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleMerchantSetting;
use App\Models\GoogleMerchantSyncItem;
use App\Models\Product;
use App\Support\GoogleMerchantApi;
use App\Support\GoogleMerchantSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

final class GoogleMerchantController extends Controller
{
    public function edit(Request $request): Response
    {
        $setting = GoogleMerchantSetting::current();
        $accountChoices = session('google_merchant_accounts', []);
        $syncIssues = GoogleMerchantSyncItem::query()
            ->when($setting->merchant_id, fn ($q) => $q->where('merchant_id', $setting->merchant_id),
                fn ($q) => $q->whereRaw('1 = 0'))
            ->where('status', 'error')->latest('updated_at')->limit(15)->get();

        return response()->view('admin.google-merchant', [
            'setting' => $setting,
            'hasSecret' => filled($setting->getRawOriginal('client_secret')),
            'hasToken' => filled($setting->getRawOriginal('refresh_token')),
            'accounts' => $accountChoices,
            'syncIssues' => $syncIssues,
            'productCount' => Product::query()->where('is_active', true)->where('is_demo', false)->count(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function save(Request $request): RedirectResponse
    {
        $setting = GoogleMerchantSetting::current();
        $validator = Validator::make($request->only(['client_id', 'client_secret']), [
            'client_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\.apps\.googleusercontent\.com$/D'],
            'client_secret' => [$setting->isConfigured() ? 'nullable' : 'required', 'string', 'max:2048'],
        ]);
        $validator->after(function ($validator) use ($request, $setting): void {
            if ($setting->client_id !== null && $setting->client_id !== $request->input('client_id')
                && !filled($request->input('client_secret'))) {
                $validator->errors()->add('client_secret', 'Yeni Google istemci kimliği için yeni gizli anahtar gereklidir.');
            }
        });
        if ($validator->fails()) {
            return redirect()->route('admin.google-merchant.edit')
                ->withErrors($validator)->withInput($request->only('client_id'));
        }

        $data = $validator->validated();
        $changed = $setting->client_id !== $data['client_id'] || filled($data['client_secret'] ?? null);
        $setting->client_id = $data['client_id'];
        if (filled($data['client_secret'] ?? null)) $setting->client_secret = $data['client_secret'];
        if ($changed) {
            $setting->refresh_token = null;
            $setting->merchant_id = null;
            $setting->data_source_name = null;
            $setting->auto_sync = false;
            $setting->sync_status = 'idle';
        }
        $setting->save();
        return redirect()->route('admin.google-merchant.edit')
            ->with('success', 'Google OAuth bilgileri şifrelenerek kaydedildi. Google hesabını bağlayabilirsiniz.');
    }

    public function connect(Request $request): RedirectResponse
    {
        $setting = GoogleMerchantSetting::current();
        if (!$setting->isConfigured()) {
            return back()->withErrors(['google' => 'Önce Google OAuth Client ID ve Client Secret bilgilerini kaydedin.']);
        }
        $state = Str::random(60);
        $request->session()->put('google_merchant_oauth', ['state' => $state, 'at' => now()->timestamp]);
        return redirect()->away(GoogleMerchantApi::authorizeUrl($setting, $state));
    }

    public function callback(Request $request, GoogleMerchantApi $api): RedirectResponse
    {
        $pending = $request->session()->pull('google_merchant_oauth');
        if (!is_array($pending) || !filled($pending['state'] ?? null)
            || !hash_equals($pending['state'], (string) $request->query('state', ''))
            || now()->timestamp - (int) ($pending['at'] ?? 0) > 600
            || ! $request->filled('code') || $request->filled('error')) {
            return redirect()->route('admin.google-merchant.edit')
                ->withErrors(['google' => 'Google yetkilendirmesi iptal edildi, geçersiz veya süresi doldu. Yeniden deneyin.']);
        }

        try {
            $setting = GoogleMerchantSetting::current();
            $setting->refresh_token = $api->exchangeCode($setting, (string) $request->query('code'));
            $setting->merchant_id = null;
            $setting->data_source_name = null;
            $setting->auto_sync = false;
            $setting->sync_status = 'idle';
            $setting->save();
        } catch (\Throwable $e) {
            return redirect()->route('admin.google-merchant.edit')->withErrors(['google' => $e->getMessage()]);
        }
        return redirect()->route('admin.google-merchant.edit')
            ->with('success', 'Google hesabı bağlandı. Şimdi Merchant Center hesabını listeleyip seçin.');
    }

    public function accounts(Request $request, GoogleMerchantApi $api): RedirectResponse
    {
        try {
            $setting = GoogleMerchantSetting::current();
            $accounts = $api->accounts($api->accessToken($setting));
            if (!$accounts) {
                throw new RuntimeException('Bu Google hesabına erişilebilir Merchant Center hesabı bulunamadı. Hesaba kullanıcı olarak eklenmeniz gerekebilir.');
            }
            $request->session()->flash('google_merchant_accounts', $accounts);
            return redirect()->route('admin.google-merchant.edit');
        } catch (\Throwable $e) {
            return redirect()->route('admin.google-merchant.edit')->withErrors(['google' => $e->getMessage()]);
        }
    }

    public function selectAccount(Request $request, GoogleMerchantApi $api): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'regex:/^accounts\/\d{4,20}$/D']]);
        try {
            $setting = GoogleMerchantSetting::current();
            $accounts = $api->accounts($api->accessToken($setting));
            if (!in_array($data['account'], array_column($accounts, 'name'), true)) {
                throw new RuntimeException('Seçilen Merchant Center hesabına erişim yetkiniz bulunmuyor.');
            }
            $id = substr($data['account'], 9);
            if ($setting->merchant_id !== $id) {
                $setting->data_source_name = null;
                $setting->auto_sync = false;
                $setting->sync_status = 'idle';
            }
            $setting->merchant_id = $id;
            $setting->save();
            return redirect()->route('admin.google-merchant.edit')
                ->with('success', 'Merchant Center hesabı seçildi. API veri kaynağını oluşturun.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.google-merchant.edit')->withErrors(['google' => $e->getMessage()]);
        }
    }

    public function prepare(GoogleMerchantApi $api): RedirectResponse
    {
        try {
            $setting = GoogleMerchantSetting::current();
            if (!$setting->isConnected() || !$setting->merchant_id) {
                throw new RuntimeException('Önce Google hesabını ve Merchant Center hesabını seçin.');
            }
            if ($setting->data_source_name) {
                throw new RuntimeException('Bu hesapta zaten bir NEWTIC veri kaynağı oluşturuldu.');
            }
            $setting->data_source_name = $api->createDataSource(
                $api->accessToken($setting), $setting->merchant_id
            );
            $setting->save();
            return redirect()->route('admin.google-merchant.edit')
                ->with('success', 'Google Merchant API veri kaynağı oluşturuldu. Ürünleri gönderebilirsiniz.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.google-merchant.edit')->withErrors(['google' => $e->getMessage()]);
        }
    }

    public function toggleAutoSync(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $setting = GoogleMerchantSetting::current();
        if (!$setting->isReady()) {
            return back()->withErrors(['google' => 'Otomatik senkronizasyon için Merchant API bağlantısı tamamlanmalı.']);
        }
        $setting->update(['auto_sync' => (bool) $data['enabled']]);
        return back()->with('success', $data['enabled']
            ? 'Otomatik senkronizasyon etkin. Sunucuda Laravel zamanlayıcısının/cron’un çalışması gerekir.'
            : 'Otomatik senkronizasyon kapatıldı.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $setting = GoogleMerchantSetting::current();
        $setting->update([
            'refresh_token' => null, 'merchant_id' => null, 'data_source_name' => null,
            'auto_sync' => false, 'sync_status' => 'idle',
        ]);
        $request->session()->forget('google_merchant_oauth');
        return redirect()->route('admin.google-merchant.edit')
            ->with('success', 'NEWTIC ile Google arasındaki bağlantı kesildi. Google Merchant Center içindeki eski ürünler otomatik silinmez.');
    }

    public function start(GoogleMerchantSync $sync): JsonResponse
    {
        try {
            return response()->json($sync->begin(GoogleMerchantSetting::current()))
                ->header('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422)->header('Cache-Control', 'no-store');
        }
    }

    public function step(GoogleMerchantSync $sync): JsonResponse
    {
        try {
            return response()->json($sync->step(GoogleMerchantSetting::current()))
                ->header('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422)->header('Cache-Control', 'no-store');
        }
    }
}