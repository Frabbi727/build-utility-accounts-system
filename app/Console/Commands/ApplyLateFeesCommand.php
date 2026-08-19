<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Services\Billing\ApplyLateFees;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Charges late fees on overdue bills. Meant to run daily.
 *
 * ApplyLateFees charges a bill at most once per calendar month, so running this
 * every day — or twice in one day — charges nobody twice.
 */
class ApplyLateFeesCommand extends Command
{
    protected $signature = 'billing:late-fees
                            {--on= : Run as if today were this date (YYYY-MM-DD)}
                            {--building= : Restrict to one building id (defaults to every building)}';

    protected $description = 'Charge late fees on bills still unpaid after their due date';

    public function handle(ApplyLateFees $lateFees): int
    {
        $on = $this->option('on');

        try {
            $runDate = $on === null ? Carbon::now() : Carbon::parse($on);
        } catch (\Throwable) {
            $this->error("Could not read --on=\"{$on}\". Expected YYYY-MM-DD, for example 2026-08-19.");

            return self::FAILURE;
        }

        $buildings = Building::query()
            ->when($this->option('building'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        if ($buildings->isEmpty()) {
            $this->error('No building matched. Nothing to charge.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($buildings as $building) {
            $charged = $lateFees->handle($building, $runDate);
            $total += $charged->count();

            $this->line("{$building->name}: {$charged->count()} bill(s) charged a late fee");
        }

        $this->info("Charged {$total} bill(s).");

        return self::SUCCESS;
    }
}
