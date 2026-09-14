<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestActivity;
use App\Models\User;

class MaintenanceTimelineService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        MaintenanceRequest $request,
        string $type,
        string $description,
        ?array $metadata = null,
        ?User $user = null,
    ): MaintenanceRequestActivity {
        $userId = $user !== null ? $user->id : auth()->id();

        return MaintenanceRequestActivity::create([
            'maintenance_request_id' => $request->id,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
