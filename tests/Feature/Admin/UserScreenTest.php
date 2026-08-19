<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Livewire\Admin\UserList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_an_admin_creates_a_user_with_a_role(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(UserList::class)
            ->call('create')
            ->set('name', 'New Accountant')
            ->set('email', 'accounts@example.com')
            ->set('password', 'secret-password')
            ->set('role', Role::Accountant->value)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::firstWhere('email', 'accounts@example.com');
        $this->assertTrue($user->hasRole(Role::Accountant->value));
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_a_new_user_needs_a_password_of_at_least_eight_characters(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(UserList::class)
            ->call('create')
            ->set('name', 'Too Short')
            ->set('email', 'short@example.com')
            ->set('password', 'abc')
            ->set('role', Role::Committee->value)
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_email_addresses_are_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(UserList::class)
            ->call('create')
            ->set('name', 'Duplicate')
            ->set('email', 'taken@example.com')
            ->set('password', 'secret-password')
            ->set('role', Role::Committee->value)
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_editing_without_a_password_keeps_the_existing_one(): void
    {
        $subject = $this->userWithRole(Role::Committee);
        $original = $subject->password;

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(UserList::class)
            ->call('edit', $subject->id)
            ->assertSet('password', '')
            ->assertSet('role', Role::Committee->value)
            ->set('name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $subject->refresh();
        $this->assertSame('Renamed', $subject->name);
        $this->assertSame($original, $subject->password);
    }

    public function test_changing_a_role_replaces_the_old_one(): void
    {
        $subject = $this->userWithRole(Role::Committee);

        Livewire::actingAs($this->userWithRole(Role::Admin))
            ->test(UserList::class)
            ->call('edit', $subject->id)
            ->set('role', Role::Accountant->value)
            ->call('save')
            ->assertHasNoErrors();

        $subject->refresh();
        $this->assertTrue($subject->hasRole(Role::Accountant->value));
        $this->assertFalse($subject->hasRole(Role::Committee->value));
    }

    public function test_an_admin_cannot_delete_their_own_login(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('delete', $admin->id)
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_an_accountant_cannot_reach_user_administration(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Accountant))
            ->test(UserList::class)
            ->assertForbidden();
    }
}
