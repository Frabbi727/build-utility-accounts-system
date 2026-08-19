<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidTariffException extends RuntimeException
{
    public static function notStartingAtZero(string $first): self
    {
        return new self("A tariff's first slab must start at 0, not {$first}.");
    }

    public static function gap(string $expected, string $found): self
    {
        return new self("Tariff slabs must be contiguous: expected a slab starting at {$expected}, found one starting at {$found}.");
    }

    public static function overlap(string $expected, string $found): self
    {
        return new self("Tariff slabs must not overlap: a slab starting at {$found} runs back into the band ending at {$expected}.");
    }

    public static function notOpenEnded(string $last): self
    {
        return new self("A tariff's last slab must be open-ended so it absorbs any consumption; this one stops at {$last}.");
    }

    public static function empty(): self
    {
        return new self('A tariff needs at least one slab.');
    }

    public static function unboundedBeforeTheEnd(string $from): self
    {
        return new self("Only the last slab may be open-ended; the one starting at {$from} is not the last.");
    }
}
