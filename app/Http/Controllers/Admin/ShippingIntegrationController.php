<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCarrier;
use App\Models\ShippingProvider;
use App\Models\ShippingWarehouse;
use App\Services\KargoIntegratorClient;
use App\Support\ShippingTestAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ShippingIntegrationController extends Controller
{
    private const PROVIDER = 'kargoentegrator';

    public function index(): Response
    {
        $provider = ShippingProvider::where('provider', self::PROVIDER)->first();
        return response()->view('admin.shipping-integrations.index', [
            'configured' => $provider?->hasKey() ?? false,
            'enabled' => $provider?->is_enabled ?? false,
            'lastCheckedAt' => $provider?->last_checked_at,
            'carriers' => $provider?->carriers()->orderBy('name')->get() ?? collect(),
            'warehouses' => $provider?->warehouses()->orderBy('name')->get() ?? collect(),
            'testAllowed' => ShippingTestAccess::allowed(),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    public function save(Request $request): RedirectResponse
    {
        $current = ShippingProvider::where('provider', self::PROVIDER)->first();
        $hasKey = $current?->hasKey() ?? false;
        // A normal Laravel validation redirect flashes input. Never flash API keys.
        $validator = Validator::make($request->only(['provider','api_key']), [
            'provider' => ['required','in:'.self::PROVIDER],
            'api_key' => [$hasKey ? 'nullable' : 'required', 'string', 'min:8','max:2048'],
        ], [
            'api_key.required' => 'Önce Kargo Entegratör API anahtarını girin.',
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.shipping-integrations.index')
                ->withErrors($validator);
        }
        $data = $validator->validated();

        DB::transaction(function () use ($data) {
            ShippingProvider::firstOrCreate(['provider' => self::PROVIDER]);
            $locked = ShippingProvider::where('provider', self::PROVIDER)
                ->lockForUpdate()->firstOrFail();
            if (filled($data['api_key'] ?? null)) {
                $locked->api_key = $data['api_key'];
                $locked->is_enabled = false;
                $locked->last_checked_at = null;
            }
            $locked->save();
        }, 3);

        return redirect()->route('admin.shipping-integrations.index')
            ->with('success','Kargo Entegratör API anahtarı şifreli kaydedildi. Gönderi oluşturma kapalı.');
    }

    public function sync(KargoIntegratorClient $client): RedirectResponse
    {
        $provider = ShippingProvider::where('provider', self::PROVIDER)->first();
        if (!$provider?->hasKey()) {
            throw ValidationException::withMessages(['shipping' => 'Önce API anahtarını kaydedin.']);
        }

        try {
            // Read-only GETs against provider. No carrier or warehouse created remotely.
            $carriers = $client->carriers($provider);
            $warehouses = $client->warehouses($provider);
        } catch (\Throwable) {
            return back()->withErrors(['shipping' => 'Bağlantı veya listeleme başarısız. API anahtarını ve sağlayıcı izinlerini kontrol edin.']);
        }

        DB::transaction(function () use ($provider, $carriers, $warehouses) {
            foreach (array_slice($carriers, 0, 200) as $item) {
                $id = KargoIntegratorClient::accountId($item);
                if (!$id) continue;
                $code = KargoIntegratorClient::carrierCode($item);
                ShippingCarrier::updateOrCreate([
                    'shipping_provider_id' => $provider->id, 'remote_id' => $id,
                ], [
                    'name' => KargoIntegratorClient::accountTitle($item, 'Kargo hesabı #'.$id),
                    'carrier_code' => $code ?: null,
                    'is_dummy' => $code === 'dummy',
                ]);
            }
            foreach (array_slice($warehouses, 0, 200) as $item) {
                $id = KargoIntegratorClient::accountId($item);
                if (!$id) continue;
                ShippingWarehouse::updateOrCreate([
                    'shipping_provider_id' => $provider->id, 'remote_id' => $id,
                ], ['name' => KargoIntegratorClient::accountTitle($item, 'Depo #'.$id)]);
            }
            $provider->update(['last_checked_at' => now()]);
        }, 3);

        return back()->with('success',
            'Bağlantı başarılı. Kargo hesapları ve depolar senkronize edildi. Yalnızca Dummy Kargo testine izin verilir.');
    }

    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required','boolean']]);
        if (!ShippingTestAccess::allowed()) {
            abort(403, 'Kargo test gönderileri yalnızca NEWTIC test alan adında açılabilir.');
        }
        DB::transaction(function () use ($data) {
            $provider = ShippingProvider::where('provider', self::PROVIDER)
                ->lockForUpdate()->first();
            if (!$provider?->hasKey() || !$provider->last_checked_at) {
                throw ValidationException::withMessages([
                    'shipping' => 'Önce API anahtarını kaydedip Kargo/Depo listelerini senkronize edin.',
                ]);
            }
            if ((bool)$data['enabled']
                && (!$provider->carriers()->where('is_dummy', true)->exists()
                    || !$provider->warehouses()->exists())) {
                throw ValidationException::withMessages([
                    'shipping' => 'Test için Dummy Kargo ve en az bir depo hesabı tanımlayıp senkronize edin.',
                ]);
            }
            $provider->update(['is_enabled' => (bool)$data['enabled']]);
        }, 3);

        return back()->with('success', $data['enabled']
            ? 'Dummy Kargo test gönderileri etkinleştirildi. DHL ve diğer gerçek taşıyıcılar kapalı.'
            : 'Kargo test gönderileri kapatıldı.');
    }
}
