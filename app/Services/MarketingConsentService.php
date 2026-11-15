<?php

namespace App\Services;

use App\Models\MarketingContact;
use App\Models\User;

final class MarketingConsentService
{
    public function request(string $email, string $source, ?User $user = null): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        // Explicit unchecked-by-default consent is only provisional. In particular,
        // it never grants send permission without separate IYS verification.
        $contact = MarketingContact::query()->firstOrCreate(['email'=>$email]);
        if ($contact->eligibleToSend()) return; // Keep an already verified, unrevoked grant.
        // Never let a guest checkout with an arbitrary email address reverse
        // that address owner's previous unsubscribe decision.
        if ($contact->unsubscribed_at && (!$user || !$user->email_verified_at)) return;
        $contact->fill([
            'user_id'=>$user?->id ?? $contact->user_id,
            'name'=>$user?->name ?? $contact->name,
            'status'=>'pending',
            'consent_source'=>$source,
            'consent_proof'=>'User checked optional newsletter permission on '.$source.' at '.now()->toIso8601String(),
            'consent_at'=>now(),
            'iys_reference'=>null,
            'iys_confirmed_at'=>null,
            'unsubscribed_at'=>null,
        ])->save();
    }

    public function revoke(MarketingContact $contact): void
    {
        $contact->forceFill([
            'status'=>'unsubscribed',
            'unsubscribed_at'=>now(),
            'iys_confirmed_at'=>null,
        ])->save();
    }
}
