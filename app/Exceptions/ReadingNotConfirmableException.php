<?php

namespace App\Exceptions;

use App\Models\MeterReading;
use RuntimeException;

class ReadingNotConfirmableException extends RuntimeException
{
    public static function noTariff(MeterReading $reading): self
    {
        return new self(
            "No tariff is in force for {$reading->meter->utility->name} on "
            .$reading->reading_date->toDateString().', so this reading cannot be priced.'
        );
    }

    public static function alreadyBilled(MeterReading $reading): self
    {
        return new self("Reading {$reading->id} has already been billed and cannot be changed.");
    }
}
