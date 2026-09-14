<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceTimelineService;
use Illuminate\Console\Command;

class CheckMaintenanceSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:check-sla
                            {--building= : Filter by specific building ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan open maintenance tickets for SLA breaches and log overdue alerts';

    /**
     * Execute the console command.
     */
    public function handle(MaintenanceTimelineService $timelineService): int
    {
        $buildingId = $this->option('building');

        $query = MaintenanceRequest::query()
            ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
            ->whereNotNull('due_by')
            ->where('due_by', '<', now())
            ->when($buildingId, fn ($q, $id) => $q->where('building_id', $id))
            ->with(['flat', 'building', 'assignedStaff', 'assignedVendor']);

        $overdueCount = $query->count();

        if ($overdueCount === 0) {
            $this->info('No open maintenance tickets have breached SLA.');

            return self::SUCCESS;
        }

        $this->warn("Found {$overdueCount} maintenance ticket(s) breaching SLA:");

        /** @var MaintenanceRequest $request */
        foreach ($query->get() as $request) {
            $hours = abs($request->hoursRemaining());
            $this->line(" - [#{$request->id}] \"{$request->title}\" (Flat {$request->flat->number}) is overdue by {$hours} hour(s). Priority: {$request->priority->label()}");

            // Record timeline activity once if not already logged as overdue
            $alreadyLogged = $request->activities()->where('type', 'sla_breached')->exists();
            if (! $alreadyLogged) {
                $timelineService->record(
                    $request,
                    'sla_breached',
                    "SLA target breached by {$hours} hour(s).",
                    ['overdue_hours' => $hours, 'priority' => $request->priority->value]
                );
            }
        }

        return self::SUCCESS;
    }
}
