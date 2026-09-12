<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Notice;
use App\Models\User;
use App\Services\Audit\AuditService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_audit_service_records_field_level_changes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $this->actingAs($admin);

        $building = Building::factory()->create();

        /** @var Notice $notice */
        $notice = Notice::create([
            'building_id' => $building->id,
            'title' => 'Initial Notice',
            'content' => 'Initial Content',
            'is_pinned' => false,
            'publish_date' => now()->toDateString(),
        ]);

        $createLog = AuditLog::where('entity_type', Notice::class)
            ->where('entity_id', $notice->id)
            ->where('action', 'CREATE')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertSame('notices', $createLog->module);
        $this->assertArrayHasKey('title', $createLog->new_values ?? []);
        $this->assertSame('Initial Notice', $createLog->new_values['title']);
        $this->assertNull($createLog->old_values);

        // Now update notice
        $notice->update([
            'title' => 'Updated Notice Title',
            'is_pinned' => true,
        ]);

        $updateLog = AuditLog::where('entity_type', Notice::class)
            ->where('entity_id', $notice->id)
            ->where('action', 'UPDATE')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame('Initial Notice', $updateLog->old_values['title']);
        $this->assertSame('Updated Notice Title', $updateLog->new_values['title']);
        $this->assertContains('title', $updateLog->changed_fields ?? []);
        $this->assertContains('is_pinned', $updateLog->changed_fields ?? []);
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create([
            'action' => 'TEST',
            'module' => 'general',
            'description' => 'Test log',
        ]);

        $this->expectException(\RuntimeException::class);
        $log->update(['description' => 'Modified description']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'action' => 'TEST',
            'module' => 'general',
            'description' => 'Test log',
        ]);

        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    public function test_sensitive_attributes_are_masked(): void
    {
        $auditService = app(AuditService::class);
        $log = $auditService->record(
            action: 'TEST',
            module: 'auth',
            description: 'User created',
            oldValues: null,
            newValues: [
                'name' => 'John',
                'password' => 'secret123',
                'remember_token' => 'tokenABC',
            ],
            changedFields: ['name', 'password', 'remember_token'],
        );

        $this->assertSame('********', $log->new_values['password']);
        $this->assertSame('********', $log->new_values['remember_token']);
        $this->assertSame('John', $log->new_values['name']);
    }

    public function test_audit_context_middleware_injects_request_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $customRequestId = 'custom-uuid-12345';
        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Request-Id' => $customRequestId,
                'X-Client-Platform' => 'flutter_android',
                'X-App-Version' => '1.0.0',
            ])
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertHeader('X-Request-Id', $customRequestId);
    }
}
