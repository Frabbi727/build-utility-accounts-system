<?php

namespace App\Services\Billing;

use App\Enums\DistributionStatus;
use App\Enums\ReadingStatus;
use App\Models\Building;
use App\Models\CostDistribution;
use App\Models\Flat;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceChargeBill;
use App\Services\Billing\LineSources\AdHocChargeLines;
use App\Services\Billing\LineSources\BillLineSource;
use App\Services\Billing\LineSources\ChargeHeadLines;
use App\Services\Billing\LineSources\CostDistributionLines;
use App\Services\Billing\LineSources\MeterReadingLines;
use App\Support\BillLineData;
use App\Support\BillSimulationResult;
use App\Support\FlatBillSimulation;
use Illuminate\Support\Carbon;

/**
 * In-memory dry-run simulator for monthly billing runs.
 *
 * Computes exact prospective lines, totals, advance drawdowns, and flags anomalies
 * without writing any database records or posting to the ledger.
 */
class SimulateMonthlyBills
{
    /**
     * @param  list<BillLineSource>  $sources
     */
    public function __construct(
        private readonly array $sources,
    ) {}

    public function handle(Building $building, Carbon $month): BillSimulationResult
    {
        $billingMonth = $month->copy()->startOfMonth();
        $dueDate = $billingMonth->copy()->addDays($building->due_day_of_month - 1);

        $alreadyBilledFlatIds = ServiceChargeBill::whereIn('flat_id', $building->flats()->select('id'))
            ->whereDate('billing_month', $billingMonth)
            ->pluck('flat_id')
            ->all();

        $pendingReadingsCount = MeterReading::query()
            ->whereIn('meter_id', Meter::where('building_id', $building->id)->select('id'))
            ->whereDate('billing_month', '<=', $billingMonth)
            ->where('status', ReadingStatus::Draft)
            ->count();

        $pendingDistributionsCount = CostDistribution::query()
            ->where('building_id', $building->id)
            ->whereDate('billing_month', '<=', $billingMonth)
            ->where('status', DistributionStatus::Draft)
            ->count();

        $buildingWarnings = [];
        if ($pendingReadingsCount > 0) {
            $buildingWarnings[] = "There are {$pendingReadingsCount} unconfirmed meter reading(s) that will be excluded from this bill.";
        }
        if ($pendingDistributionsCount > 0) {
            $buildingWarnings[] = "There are {$pendingDistributionsCount} unapproved cost distribution(s) that will be excluded from this bill.";
        }

        $flats = $building->flats()
            ->with(['chargeOverrides', 'owner', 'tenants'])
            ->orderBy('number')
            ->get();

        $flatSimulations = [];
        $totalFlatsToBill = 0;
        $estTotalAmount = '0.00';
        $estChargeHeadsAmount = '0.00';
        $estUtilitiesAmount = '0.00';
        $estCostDistributionsAmount = '0.00';
        $estAdHocAmount = '0.00';
        $estAdvancesAmount = '0.00';
        $estNetReceivable = '0.00';

        foreach ($flats as $flat) {
            $flat->setRelation('building', $building);

            $isAlreadyBilled = in_array($flat->id, $alreadyBilledFlatIds, true);
            $warnings = [];

            if (! $flat->is_active) {
                $warnings[] = 'Flat is marked inactive.';
            }

            if ($isAlreadyBilled) {
                $warnings[] = 'Already billed for this month.';
            }

            // Check if flat has unconfirmed draft meter readings
            $flatDraftReadings = MeterReading::query()
                ->whereIn('meter_id', Meter::where('flat_id', $flat->id)->select('id'))
                ->whereDate('billing_month', '<=', $billingMonth)
                ->where('status', ReadingStatus::Draft)
                ->count();

            if ($flatDraftReadings > 0) {
                $warnings[] = "Has {$flatDraftReadings} unconfirmed draft meter reading(s).";
            }

            $chargeHeadsTotal = '0.00';
            $utilitiesTotal = '0.00';
            $costDistributionsTotal = '0.00';
            $adHocTotal = '0.00';
            $flatLines = [];

            foreach ($this->sources as $source) {
                $lines = array_values(array_filter(
                    $source->linesFor($flat, $billingMonth),
                    fn (BillLineData $line): bool => ! $line->isZero(),
                ));

                foreach ($lines as $line) {
                    $flatLines[] = $line;
                    $amount = $line->amount;

                    if ($source instanceof ChargeHeadLines) {
                        $chargeHeadsTotal = bcadd($chargeHeadsTotal, $amount, 2);
                    } elseif ($source instanceof MeterReadingLines) {
                        $utilitiesTotal = bcadd($utilitiesTotal, $amount, 2);
                    } elseif ($source instanceof CostDistributionLines) {
                        $costDistributionsTotal = bcadd($costDistributionsTotal, $amount, 2);
                    } elseif ($source instanceof AdHocChargeLines) {
                        $adHocTotal = bcadd($adHocTotal, $amount, 2);
                    }
                }
            }

            $flatTotal = '0.00';
            $flatTotal = bcadd($flatTotal, $chargeHeadsTotal, 2);
            $flatTotal = bcadd($flatTotal, $utilitiesTotal, 2);
            $flatTotal = bcadd($flatTotal, $costDistributionsTotal, 2);
            $flatTotal = bcadd($flatTotal, $adHocTotal, 2);

            if ($flat->is_active && ! $isAlreadyBilled && bccomp($flatTotal, '0', 2) <= 0) {
                $warnings[] = 'Total billable amount is zero (will be skipped).';
            }

            // Estimate advance drawdowns
            $availableAdvance = '0.00';
            $payments = Payment::where('flat_id', $flat->id)->get();
            foreach ($payments as $payment) {
                $availableAdvance = bcadd($availableAdvance, $payment->unallocatedAmount(), 2);
            }

            $estimatedAdvanceDrawdown = bccomp($availableAdvance, '0', 2) > 0
                ? (bccomp($flatTotal, $availableAdvance, 2) < 0 ? $flatTotal : $availableAdvance)
                : '0.00';

            $netReceivable = bcsub($flatTotal, $estimatedAdvanceDrawdown, 2);

            $simulation = new FlatBillSimulation(
                flat: $flat,
                lines: $flatLines,
                chargeHeadsAmount: $chargeHeadsTotal,
                utilitiesAmount: $utilitiesTotal,
                costDistributionsAmount: $costDistributionsTotal,
                adHocAmount: $adHocTotal,
                totalAmount: $flatTotal,
                availableAdvance: $availableAdvance,
                estimatedAdvanceDrawdown: $estimatedAdvanceDrawdown,
                netReceivable: $netReceivable,
                isAlreadyBilled: $isAlreadyBilled,
                warnings: $warnings,
            );

            $flatSimulations[] = $simulation;

            if ($simulation->willBeBilled()) {
                $totalFlatsToBill++;
                $estTotalAmount = bcadd($estTotalAmount, $flatTotal, 2);
                $estChargeHeadsAmount = bcadd($estChargeHeadsAmount, $chargeHeadsTotal, 2);
                $estUtilitiesAmount = bcadd($estUtilitiesAmount, $utilitiesTotal, 2);
                $estCostDistributionsAmount = bcadd($estCostDistributionsAmount, $costDistributionsTotal, 2);
                $estAdHocAmount = bcadd($estAdHocAmount, $adHocTotal, 2);
                $estAdvancesAmount = bcadd($estAdvancesAmount, $estimatedAdvanceDrawdown, 2);
                $estNetReceivable = bcadd($estNetReceivable, $netReceivable, 2);
            }
        }

        return new BillSimulationResult(
            building: $building,
            billingMonth: $billingMonth,
            dueDate: $dueDate,
            flats: $flatSimulations,
            totalActiveFlats: $building->activeFlats()->count(),
            totalFlatsToBill: $totalFlatsToBill,
            alreadyBilledCount: count($alreadyBilledFlatIds),
            estimatedTotalAmount: $estTotalAmount,
            estimatedChargeHeadsAmount: $estChargeHeadsAmount,
            estimatedUtilitiesAmount: $estUtilitiesAmount,
            estimatedCostDistributionsAmount: $estCostDistributionsAmount,
            estimatedAdHocAmount: $estAdHocAmount,
            estimatedAdvancesAmount: $estAdvancesAmount,
            estimatedNetReceivable: $estNetReceivable,
            pendingDraftReadingsCount: $pendingReadingsCount,
            pendingDraftDistributionsCount: $pendingDistributionsCount,
            buildingWarnings: $buildingWarnings,
        );
    }
}
