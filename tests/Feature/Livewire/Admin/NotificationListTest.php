<?php

namespace Tests\Feature\Livewire\Admin;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Livewire\Admin\NotificationList;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_non_staff_cannot_access_notifications_screen(): void
    {
        $resident = User::factory()->create();
        $resident->assignRole(Role::Owner->value);

        $this->actingAs($resident)
            ->get(route('admin.notifications'))
            ->assertForbidden();
    }

    public function test_staff_can_access_notifications_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        Notification::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Important Maintenance Notice',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('Important Maintenance Notice');
    }

    public function test_staff_can_filter_notifications(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $n1 = Notification::factory()->create([
            'user_id' => $admin->id,
            'type' => NotificationType::BillGenerated->value,
            'title' => 'Bill For Flat 101',
            'status' => 'sent',
            'is_read' => false,
        ]);

        $n2 = Notification::factory()->create([
            'user_id' => $admin->id,
            'type' => NotificationType::MaintenanceUpdated->value,
            'title' => 'Ticket Resolved 202',
            'status' => 'pending',
            'is_read' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(NotificationList::class)
            ->set('type', NotificationType::BillGenerated->value)
            ->assertSee('Bill For Flat 101')
            ->assertDontSee('Ticket Resolved 202')
            ->set('type', '')
            ->set('status', 'pending')
            ->assertSee('Ticket Resolved 202')
            ->assertDontSee('Bill For Flat 101')
            ->set('status', '')
            ->set('isRead', '1')
            ->assertSee('Ticket Resolved 202')
            ->assertDontSee('Bill For Flat 101');
    }

    public function test_staff_can_open_notification_details_modal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $device = UserDevice::factory()->create([
            'user_id' => $admin->id,
            'device_model' => 'Pixel 9 Pro',
        ]);

        $notification = Notification::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Payment Submission Received',
            'body' => 'Your slip of 5000 BDT is being reviewed.',
            'status' => 'sent',
        ]);

        NotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $admin->id,
            'user_device_id' => $device->id,
            'status' => 'success',
            'error_message' => null,
            'response_payload' => ['fcm_id' => 'msg_12345'],
        ]);

        $this->actingAs($admin);

        Livewire::test(NotificationList::class)
            ->call('openDetail', $notification->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('viewingId', $notification->id)
            ->assertSee('Payment Submission Received')
            ->assertSee('Pixel 9 Pro')
            ->call('closeDetail')
            ->assertSet('showDetailModal', false)
            ->assertSet('viewingId', null);
    }

    public function test_staff_can_send_manual_notification_to_all_residents(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $resident1 = User::factory()->create();
        $resident1->assignRole(Role::Owner->value);

        $resident2 = User::factory()->create();
        $resident2->assignRole(Role::Tenant->value);

        $this->actingAs($admin);

        Livewire::test(NotificationList::class)
            ->call('openSendModal')
            ->assertSet('showSendModal', true)
            ->set('sendTarget', 'all_residents')
            ->set('sendTitle', 'Building Water Tank Cleaning')
            ->set('sendBody', 'Water supply will be suspended tomorrow from 10 AM to 2 PM.')
            ->call('sendNotification')
            ->assertHasNoErrors()
            ->assertSet('showSendModal', false);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident1->id,
            'title' => 'Building Water Tank Cleaning',
            'type' => NotificationType::AdminNotification->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident2->id,
            'title' => 'Building Water Tank Cleaning',
            'type' => NotificationType::AdminNotification->value,
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_staff_can_send_manual_notification_to_single_user(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $user = User::factory()->create();
        $user->assignRole(Role::Owner->value);

        $this->actingAs($admin);

        Livewire::test(NotificationList::class)
            ->call('openSendModal')
            ->set('sendTarget', 'single')
            ->set('sendUserId', $user->id)
            ->set('sendTitle', 'Private Alert')
            ->set('sendBody', 'Please collect your parcel from the lobby.')
            ->call('sendNotification')
            ->assertHasNoErrors()
            ->assertSet('showSendModal', false);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Private Alert',
            'body' => 'Please collect your parcel from the lobby.',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_send_notification_validation_rules(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin);

        Livewire::test(NotificationList::class)
            ->call('openSendModal')
            ->set('sendTitle', '')
            ->set('sendBody', '')
            ->set('sendTarget', 'single')
            ->set('sendUserId', null)
            ->call('sendNotification')
            ->assertHasErrors(['sendTitle', 'sendBody', 'sendUserId']);
    }
}
