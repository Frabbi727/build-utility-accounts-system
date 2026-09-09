<?php

namespace Tests\Feature\Masters;

use App\Enums\NoticeType;
use App\Enums\Role;
use App\Livewire\Masters\NoticeList;
use App\Models\Building;
use App\Models\Notice;
use App\Models\User;
use App\Support\CurrentBuilding;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoticeListTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->building = Building::factory()->create();
        app(CurrentBuilding::class)->set($this->building->id);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_staff_can_view_notice_list_and_create_notice(): void
    {
        $staff = $this->userWithRole(Role::Admin);

        Livewire::actingAs($staff)
            ->test(NoticeList::class)
            ->assertSee(__('masters.notices'))
            ->call('create')
            ->assertSet('showForm', true)
            ->set('title', 'Water Supply Maintenance')
            ->set('content', 'Water supply will be suspended on Saturday from 2 PM to 5 PM.')
            ->set('type', NoticeType::Maintenance->value)
            ->set('isPinned', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $notice = Notice::firstOrFail();
        $this->assertSame('Water Supply Maintenance', $notice->title);
        $this->assertSame(NoticeType::Maintenance, $notice->type);
        $this->assertTrue($notice->is_pinned);
        $this->assertSame($this->building->id, $notice->building_id);
        $this->assertSame($staff->id, $notice->created_by);
    }

    public function test_staff_can_edit_toggle_pinned_and_delete_notice(): void
    {
        $staff = $this->userWithRole(Role::Accountant);
        $notice = Notice::factory()->create([
            'building_id' => $this->building->id,
            'title' => 'Initial Title',
            'is_pinned' => false,
        ]);

        Livewire::actingAs($staff)
            ->test(NoticeList::class)
            ->call('edit', $notice->id)
            ->assertSet('title', 'Initial Title')
            ->set('title', 'Updated Circular Title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated Circular Title', $notice->fresh()->title);

        // Toggle pinned
        Livewire::actingAs($staff)
            ->test(NoticeList::class)
            ->call('togglePinned', $notice->id);

        $this->assertTrue($notice->fresh()->is_pinned);

        // Delete
        Livewire::actingAs($staff)
            ->test(NoticeList::class)
            ->call('delete', $notice->id);

        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }

    public function test_resident_cannot_access_notice_management(): void
    {
        $resident = $this->userWithRole(Role::Owner);

        $response = $this->actingAs($resident)->get(route('notices.index'));
        $response->assertForbidden();
    }

    public function test_active_and_pinned_scopes_work_correctly(): void
    {
        $activeNotice = Notice::factory()->create([
            'building_id' => $this->building->id,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(2),
            'is_pinned' => true,
        ]);

        $futureNotice = Notice::factory()->create([
            'building_id' => $this->building->id,
            'published_at' => now()->addDay(),
            'expires_at' => now()->addDays(5),
            'is_pinned' => false,
        ]);

        $expiredNotice = Notice::factory()->create([
            'building_id' => $this->building->id,
            'published_at' => now()->subDays(5),
            'expires_at' => now()->subDay(),
            'is_pinned' => false,
        ]);

        $activeNotices = Notice::active()->get();
        $this->assertTrue($activeNotices->contains($activeNotice));
        $this->assertFalse($activeNotices->contains($futureNotice));
        $this->assertFalse($activeNotices->contains($expiredNotice));

        $pinnedNotices = Notice::pinned()->get();
        $this->assertTrue($pinnedNotices->contains($activeNotice));
        $this->assertFalse($pinnedNotices->contains($futureNotice));
    }
}
