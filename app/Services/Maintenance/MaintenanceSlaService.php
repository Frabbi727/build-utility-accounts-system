<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use Illuminate\Support\Carbon;

class MaintenanceSlaService
{
    public function calculateDueBy(Carbon $createdAt, MaintenancePriority $priority): Carbon
    {
        return $createdAt->copy()->addHours($priority->slaHours());
    }

    public function applySla(MaintenanceRequest $request): MaintenanceRequest
    {
        $createdAt = $request->created_at ?? now();
        $request->due_by = $this->calculateDueBy($createdAt, $request->priority);

        return $request;
    }

    public function isOverdue(MaintenanceRequest $request): bool
    {
        return $request->isOverdue();
    }

    public function slaStatus(MaintenanceRequest $request): string
    {
        return $request->slaStatus();
    }
}
