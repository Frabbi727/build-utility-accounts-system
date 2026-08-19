<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Services\Billing\GenerateMonthlyBills;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs monthly bill generation without a browser, so it can be scheduled.
 *
 * All the work belongs to GenerateMonthlyBills, which is idempotent per
 * (flat, billing month) — re-running this command bills nobody twice.
 */
class GenerateBillsCommand extends Command
{
    protected $signature = 'billing:generate
                            {--month= : Billing month as YYYY-MM (defaults to the current month)}
                            {--building= : Restrict to one building id (defaults to every building)}';

    protected $description = 'Generate service charge bills and post the matching accruals';

    public function handle(GenerateMonthlyBills $generator): int
    {
        $month = $this->option('month');

        try {
            $billingMonth = $month === null
                ? Carbon::now()->startOfMonth()
                : Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $this->error("Could not read --month=\"{$month}\". Expected YYYY-MM, for example 2026-08.");

            return self::FAILURE;
        }

        $buildings = Building::query()
            ->when($this->option('building'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        if ($buildings->isEmpty()) {
            $this->error('No building matched. Nothing to generate.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($buildings as $building) {
            $bills = $generator->handle($building, $billingMonth);
            $total += $bills->count();

            $this->line("{$building->name}: {$bills->count()} bill(s) for {$billingMonth->format('F Y')}");
        }

        $this->info("Generated {$total} bill(s).");

        return self::SUCCESS;
    }
}
