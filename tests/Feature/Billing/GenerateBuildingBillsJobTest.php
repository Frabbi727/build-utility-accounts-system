<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateBuildingBillsJob;
use App\Jobs\SendResidentPushNotificationJob;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\ServiceChargeBill;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateBuildingBillsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_it_generates_bills_asynchronously_and_queues_push_notifications(): void
    {
        Queue::fake([SendResidentPushNotificationJob::class]);

        $user = User::factory()->create();
        $building = Building::factory()->flatRate('3000.00')->create();
        $owner = Owner::factory()->create(['user_id' => $user->id]);
        $flat = Flat::factory()->for($building)->create(['owner_id' => $owner->id]);

        $job = new GenerateBuildingBillsJob($building, Carbon::parse('2026-08-01'), $user->id);
        $bills = app()->call([$job, 'handle']);

        $this->assertCount(1, $bills);
        $this->assertSame(1, ServiceChargeBill::count());
        $this->assertSame('3000.00', (string) $bills->first()->total_amount);

        Queue::assertPushed(SendResidentPushNotificationJob::class, function ($pushJob) use ($user): bool {
            return in_array($user->id, $pushJob->userIds, true)
                && $pushJob->title === 'New Monthly Bill Issued';
        });
    }
}
