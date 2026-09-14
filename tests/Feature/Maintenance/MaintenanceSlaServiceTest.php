<?php

namespace Tests\Feature\Maintenance;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Flat;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Maintenance\MaintenanceSlaService;
use App\Services\Maintenance\MaintenanceTimelineService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceSlaServiceTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    private Flat $flat;

    private User $user;

    private MaintenanceSlaService $slaService;

    private MaintenanceTimelineService $timelineService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->building = Building::factory()->create();
        $this->flat = Flat::factory()->for($this->building)->create();
        $this->user = User::factory()->create();

        $this->slaService = app(MaintenanceSlaService::class);
        $this->timelineService = app(MaintenanceTimelineService::class);
    }

    public function test_it_calculates_due_by_based_on_priority_sla(): void
    {
        $created = Carbon::parse('2026-09-01 10:00:00');

        $emergencyDue = $this->slaService->calculateDueBy($created, MaintenancePriority::Emergency);
        $this->assertSame('2026-09-01 14:00:00', $emergencyDue->toDateTimeString());

        $highDue = $this->slaService->calculateDueBy($created, MaintenancePriority::High);
        $this->assertSame('2026-09-02 10:00:00', $highDue->toDateTimeString());

        $mediumDue = $this->slaService->calculateDueBy($created, MaintenancePriority::Medium);
        $this->assertSame('2026-09-03 10:00:00', $mediumDue->toDateTimeString());

        $lowDue = $this->slaService->calculateDueBy($created, MaintenancePriority::Low);
        $this->assertSame('2026-09-05 10:00:00', $lowDue->toDateTimeString());
    }

    public function test_it_applies_sla_to_maintenance_request(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->user->id,
            'priority' => MaintenancePriority::High,
            'created_at' => Carbon::parse('2026-09-01 12:00:00'),
        ]);

        $this->slaService->applySla($request);
        $request->save();

        $this->assertSame('2026-09-02 12:00:00', $request->due_by?->toDateTimeString());
    }

    public function test_timeline_service_records_activities(): void
    {
        $request = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->user->id,
        ]);

        $activity = $this->timelineService->record(
            $request,
            'status_changed',
            'Status changed to In Progress',
            ['old' => 'open', 'new' => 'in_progress'],
            $this->user
        );

        $this->assertDatabaseHas('maintenance_request_activities', [
            'id' => $activity->id,
            'maintenance_request_id' => $request->id,
            'type' => 'status_changed',
            'description' => 'Status changed to In Progress',
        ]);
    }

    public function test_check_sla_command_identifies_breached_tickets(): void
    {
        $overdueTicket = MaintenanceRequest::factory()->create([
            'building_id' => $this->building->id,
            'flat_id' => $this->flat->id,
            'user_id' => $this->user->id,
            'title' => 'Emergency water leak',
            'status' => MaintenanceStatus::Open,
            'priority' => MaintenancePriority::Emergency,
            'due_by' => now()->subHours(2),
        ]);

        $this->artisan('maintenance:check-sla', ['--building' => $this->building->id])
            ->expectsOutputToContain('Emergency water leak')
            ->assertExitCode(0);

        $this->assertDatabaseHas('maintenance_request_activities', [
            'maintenance_request_id' => $overdueTicket->id,
            'type' => 'sla_breached',
        ]);
    }
}
