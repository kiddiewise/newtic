<?php

namespace App\Services\Invoices;

use RuntimeException;

final class EdmLookupFailure extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct('EDM lookup failed');
    }
}
