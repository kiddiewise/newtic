<?php

namespace App\Http\Controllers;

use App\Models\MarketingContact;
use App\Services\MarketingConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MarketingPreferenceController extends Controller
{
    public function update(Request $request, MarketingConsentService $service): RedirectResponse
    {
        $data=$request->validate(['subscribe'=>['required','boolean']]);
        $user=$request->user();
        abort_unless($user && !$user->is_admin,403);
        $contact=MarketingContact::query()->where('email',mb_strtolower($user->email))->first();
        if ((bool)$data['subscribe']) {
            // Preserve a valid existing grant; never silently reset IYS evidence.
            if (!$contact || !$contact->eligibleToSend()) {
                $service->request($user->email,'customer_account_preference',$user);
            }
            return back()->with('success','Kampanya tercihiniz kaydedildi. Gönderim için İYS kaydı ayrıca doğrulanacaktır.');
        }
        if ($contact && !$contact->unsubscribed_at) $service->revoke($contact);
        return back()->with('success','Kampanya e-postaları durduruldu. Sipariş ve güvenlik bildirimleri etkilenmez.');
    }
}
