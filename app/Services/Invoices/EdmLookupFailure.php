<?php

namespace App\Services\Invoices;

use RuntimeException;

final class EdmLookupFailure extends RuntimeException
{
    public function __construct(public readonly string $safeCode, public readonly array $hints=[])
    {
        parent::__construct('EDM lookup failed');
    }
}
