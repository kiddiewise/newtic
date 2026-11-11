<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaytrSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class PaytrSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = PaytrSetting::query()->find(1);

        // Never pass a model containing decrypted secrets into Blade.
        $merchantId = $settings?->merchant_id ?? '';
        $hasKey = $settings && filled($settings->getRawOriginal('merchant_key'));
        $hasSalt = $settings && filled($settings->getRawOriginal('merchant_salt'));
        $configured = $settings?->hasCredentials() ?? false;

        return response()->view('admin.paytr-settings', compact(
            'merchantId', 'hasKey', 'hasSalt', 'configured'
        ))->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    public function update(Request $request): RedirectResponse
    {
        $existing = PaytrSetting::query()->find(1);
        $hasKey = $existing && filled($existing->getRawOriginal('merchant_key'));
        $hasSalt = $existing && filled($existing->getRawOriginal('merchant_salt'));

        // Manually redirect on validation errors. Laravel's normal redirect
        // flashes request input, which MUST NOT include API credentials.
        $validator = Validator::make($request->only([
            'merchant_id', 'merchant_key', 'merchant_salt',
        ]), [
            'merchant_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9]+$/D'],
            'merchant_key' => [$hasKey ? 'nullable' : 'required', 'string', 'max:2048'],
            'merchant_salt' => [$hasSalt ? 'nullable' : 'required', 'string', 'max:2048'],
        ], [
            'merchant_id.required' => 'Mağaza numarasını girin.',
            'merchant_id.regex' => 'Mağaza numarası yalnızca harf ve rakam içerebilir.',
            'merchant_key.required' => 'Mağaza parolasını girin.',
            'merchant_salt.required' => 'Mağaza gizli anahtarını girin.',
        ]);

        // A different merchant ID must never accidentally reuse the old store
        // credentials. Require both values again during account rotation.
        $validator->after(function ($validator) use ($request, $existing): void {
            if (! $existing || $validator->errors()->has('merchant_id')
                || $existing->merchant_id === $request->input('merchant_id')) {
                return;
            }
            if (! filled($request->input('merchant_key'))) {
                $validator->errors()->add('merchant_key', 'Yeni mağaza numarası için mağaza parolasını tekrar girin.');
            }
            if (! filled($request->input('merchant_salt'))) {
                $validator->errors()->add('merchant_salt', 'Yeni mağaza numarası için gizli anahtarı tekrar girin.');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('admin.paytr-settings.edit')
                ->withErrors($validator)
                ->withInput($request->only('merchant_id'));
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated): void {
            // Create once, then lock the singleton row for concurrent updates.
            PaytrSetting::query()->firstOrCreate(['id' => 1]);
            $settings = PaytrSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $settings->merchant_id = $validated['merchant_id'];
            if (filled($validated['merchant_key'] ?? null)) {
                $settings->merchant_key = $validated['merchant_key'];
            }
            if (filled($validated['merchant_salt'] ?? null)) {
                $settings->merchant_salt = $validated['merchant_salt'];
            }

            // Stage one only: NEVER auto-activate real card collection by
            // accepting keys. Webhooks/token validation are not wired yet.
            $settings->is_enabled = false;
            $settings->test_mode = true;
            $settings->save();
        }, 3);

        return redirect()->route('admin.paytr-settings.edit')
            ->with('success', 'PAYTR bilgileri güvenli şekilde kaydedildi. Ödeme alma henüz etkin değil.');
    }
}
