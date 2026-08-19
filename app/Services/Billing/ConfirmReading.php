<?php

namespace App\Services\Billing;

use App\Enums\ReadingStatus;
use App\Exceptions\ReadingNotConfirmableException;
use App\Models\MeterReading;
use Illuminate\Support\Facades\DB;

/**
 * Settles a draft reading so it can be billed.
 *
 * The one thing this does that nothing else may: **stamp the tariff**. Resolving the rate
 * later, at bill time, would price a reading confirmed in July at whatever rate happened
 * to be current in September. Stamping here also fixes the point at which a tariff becomes
 * history — `UtilityTariff::isInUse()` is true from this moment, and the tariff screen
 * refuses to edit it.
 *
 * Confirming is idempotent: a reading already confirmed keeps the tariff it was confirmed
 * against, so re-confirming never silently reprices it.
 */
class ConfirmReading
{
    /**
     * @throws ReadingNotConfirmableException
     */
    public function handle(MeterReading $reading): MeterReading
    {
        if ($reading->isApplied()) {
            throw ReadingNotConfirmableException::alreadyBilled($reading);
        }

        if ($reading->isConfirmed()) {
            return $reading;
        }

        $reading->loadMissing('meter.utility');

        $tariff = $reading->meter->utility->tariffOn($reading->reading_date);

        if ($tariff === null) {
            throw ReadingNotConfirmableException::noTariff($reading);
        }

        return DB::transaction(function () use ($reading, $tariff): MeterReading {
            $reading->utility_tariff_id = $tariff->id;
            $reading->status = ReadingStatus::Confirmed;
            $reading->save();

            return $reading;
        });
    }

    /**
     * Send a confirmed reading back to draft so a misread can be corrected.
     *
     * Refused once the reading has reached a bill: a posted bill is never edited, so a
     * mistake found after billing is corrected by a fresh reading whose difference rides
     * the next bill, exactly as `.ai/rules/accounting-ledger.md` requires of the ledger.
     *
     * @throws ReadingNotConfirmableException
     */
    public function revert(MeterReading $reading): MeterReading
    {
        if ($reading->isApplied()) {
            throw ReadingNotConfirmableException::alreadyBilled($reading);
        }

        $reading->utility_tariff_id = null;
        $reading->status = ReadingStatus::Draft;
        $reading->save();

        return $reading;
    }
}
