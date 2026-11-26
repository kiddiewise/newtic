<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;

/**
 * Required external approval boundary for the EXACT bytes, account, parties
 * and operator. No implementation or container binding until official GIB
 * validation, EDM signing/serial policy and test routing are verified.
 */
interface EdmSandboxLoadPolicy
{
    public function authorize(InvoicePreparation $draft, EdmSetting $account,
        #[\SensitiveParameter] string $xml, string $profile,
        #[\SensitiveParameter] string $senderAlias, #[\SensitiveParameter] string $receiverAlias): void;
}
