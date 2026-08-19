<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidDistributionException extends RuntimeException
{
    public static function noUnits(): self
    {
        return new self('There are no active units to distribute this cost across.');
    }

    public static function zeroWeight(): self
    {
        return new self('Every unit weighs nothing, so there is no basis on which to divide the cost.');
    }

    public static function readingsMissing(int $count): self
    {
        return new self("{$count} meter reading(s) for this month are not confirmed yet, so a consumption split would be computed against a partial denominator.");
    }

    public static function alreadyApproved(): self
    {
        return new self('This distribution is approved. Its lines are frozen and cannot be recomputed.');
    }

    public static function alreadyBilled(): self
    {
        return new self('This distribution has already reached a bill and can no longer be changed.');
    }
}
