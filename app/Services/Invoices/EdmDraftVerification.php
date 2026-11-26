<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;

/**
 * Deployment-specific, trusted verification boundary, intentionally unbound.
 * A concrete implementation must verify current official GIB XSD AND
 * Schematron against the exact original XML bytes; EDM draft signing/serial
 * policy; current account/recipient routing and explicit operator approval.
 * Do not accept browser-supplied flags, digests or SVRL as proof.
 */
interface EdmDraftVerification
{
    public function verify(InvoicePreparation $draft, EdmSetting $account,
        #[\SensitiveParameter] string $xml, string $profile,
        #[\SensitiveParameter] string $senderAlias, #[\SensitiveParameter] string $receiverAlias): void;
}
