<?php

namespace App\Http\Controllers;

use App\Models\MarketingContact;
use App\Services\MarketingConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MarketingUnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $contact = MarketingContact::query()->where('unsubscribe_token',$token)->firstOrFail();
        return view('store.marketing-unsubscribe',compact('contact'));
    }

    public function unsubscribe(Request $request, string $token, MarketingConsentService $service): RedirectResponse
    {
        $contact = MarketingContact::query()->where('unsubscribe_token',$token)->firstOrFail();
        if (!$contact->unsubscribed_at) $service->revoke($contact);
        return redirect()->route('marketing.unsubscribe.show',$token);
    }
}
