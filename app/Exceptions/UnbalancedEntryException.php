<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public static function make(string $debit, string $credit): self
    {
        return new self("Journal entry is unbalanced: debits {$debit} do not equal credits {$credit}.");
    }
}
