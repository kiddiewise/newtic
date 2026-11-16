<?php

namespace App\Support;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;

final class LegalReadiness
{
    public static function checkoutReady(): bool
    {
        if (!MerchantLegalProfile::current()->ready()) return false;

        $pages = MerchantLegalPage::checkoutDocuments();
        foreach (MerchantLegalPage::CHECKOUT_DOCUMENTS as $slug) {
            if (!isset($pages[$slug]) || blank($pages[$slug]->body)) return false;
        }

        return true;
    }

    public static function requiredBeforeLive(): array
    {
        $missing = [];
        if (!MerchantLegalProfile::current()->ready()) $missing[] = 'Satıcı ünvanı, adres, telefon ve e-posta';
        foreach (MerchantLegalPage::PAYTR_PAGES as $slug) {
            if (!MerchantLegalPage::published($slug)) {
                $missing[] = MerchantLegalPage::LABELS[$slug];
            }
        }
        if (!MerchantLegalPage::published('on-bilgilendirme-formu')) {
            $missing[] = MerchantLegalPage::LABELS['on-bilgilendirme-formu'];
        }
        return $missing;
    }

    public static function checkoutSignature(): string
    {
        if (!self::checkoutReady()) return '';

        $profile = MerchantLegalProfile::current();
        $pages = MerchantLegalPage::checkoutDocuments();

        return hash('sha256', implode('|', array_merge([
            (string) $profile->legal_name,
            (string) $profile->registered_address,
            (string) $profile->contact_email,
            (string) $profile->contact_phone,
        ], array_map(fn ($slug) => $pages[$slug]->revisionHash(), MerchantLegalPage::CHECKOUT_DOCUMENTS))));
    }

    public static function orderSnapshot(): array
    {
        $profile = MerchantLegalProfile::current();
        $pages = MerchantLegalPage::checkoutDocuments();

        return [
            'acknowledged_at' => now()->toIso8601String(),
            'seller' => [
                'legal_name' => $profile->legal_name,
                'registered_address' => $profile->registered_address,
                'contact_email' => $profile->contact_email,
                'contact_phone' => $profile->contact_phone,
            ],
            'documents' => collect(MerchantLegalPage::CHECKOUT_DOCUMENTS)
                ->mapWithKeys(fn ($slug) => [$slug => [
                    'title' => $pages[$slug]->title,
                    'version' => $pages[$slug]->version,
                    'sha256' => $pages[$slug]->revisionHash(),
                    'body' => $pages[$slug]->body,
                ]])->all(),
        ];
    }
}
