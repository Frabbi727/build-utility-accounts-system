<?php

namespace App\Services\Billing;

use App\Enums\DistributionStatus;
use App\Exceptions\InvalidDistributionException;
use App\Models\CostDistribution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Freezes a distribution's per-unit lines so they can be billed.
 *
 * **This posts nothing to the ledger, and must not.** The receivable is accrued when the
 * bill is generated. Accruing at approval as well would double-count the income, and
 * because each entry would balance on its own, no balance check would ever notice.
 *
 * Freezing is the point: after approval a flat can be added, deactivated or resized
 * without moving money somebody has already agreed to pay.
 */
class ApproveDistribution
{
    public function __construct(private readonly CostDistributionCalculator $calculator) {}

    /**
     * Recompute a draft's lines from its basis, replacing whatever was there.
     *
     * @throws InvalidDistributionException
     */
    public function recalculate(CostDistribution $distribution): CostDistribution
    {
        if ($distribution->isApproved()) {
            throw InvalidDistributionException::alreadyApproved();
        }

        $lines = $this->calculator->linesFor($distribution);

        return DB::transaction(function () use ($distribution, $lines): CostDistribution {
            $distribution->lines()->delete();
            $distribution->lines()->createMany($lines);

            return $distribution->load('lines');
        });
    }

    /**
     * Settle the lines as they currently stand.
     *
     * The lines are taken as they are rather than recomputed, because the operator may
     * have edited them — that is what replaces any need for saved weighting rules. The
     * one thing enforced is that they still add up to the total, or the building would
     * recover more or less than it spent.
     *
     * @throws InvalidDistributionException
     */
    public function handle(CostDistribution $distribution): CostDistribution
    {
        if ($distribution->isApproved()) {
            return $distribution;
        }

        if ($distribution->lines()->doesntExist()) {
            $this->recalculate($distribution);
        }

        $allocated = $distribution->allocatedAmount();

        if (bccomp($allocated, (string) $distribution->total_amount, 2) !== 0) {
            throw new InvalidDistributionException(
                "The lines add up to {$allocated}, not {$distribution->total_amount}."
            );
        }

        return DB::transaction(function () use ($distribution): CostDistribution {
            $distribution->status = DistributionStatus::Approved;
            $distribution->approved_by = Auth::id();
            $distribution->approved_at = now();
            $distribution->save();

            return $distribution;
        });
    }

    /**
     * Send an approved distribution back to draft.
     *
     * Refused once any line has reached a bill: that money is on an owner's statement and
     * in the ledger, and a posted bill is never edited.
     *
     * @throws InvalidDistributionException
     */
    public function revert(CostDistribution $distribution): CostDistribution
    {
        if ($distribution->isBilled()) {
            throw InvalidDistributionException::alreadyBilled();
        }

        $distribution->status = DistributionStatus::Draft;
        $distribution->approved_by = null;
        $distribution->approved_at = null;
        $distribution->save();

        return $distribution;
    }
}
