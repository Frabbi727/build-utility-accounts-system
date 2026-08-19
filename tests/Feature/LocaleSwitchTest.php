<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_default_locale_is_bangla(): void
    {
        $this->assertSame('bn', config('app.locale'));
    }

    public function test_a_visitor_can_switch_to_english_and_back(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', session('locale'));

        $this->post(route('locale.switch'), ['locale' => 'bn'])->assertRedirect();
        $this->assertSame('bn', session('locale'));
    }

    public function test_an_unsupported_locale_is_ignored(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'fr'])->assertRedirect();
        $this->assertNull(session('locale'));
    }

    public function test_the_chosen_locale_is_applied_to_a_rendered_page(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Admin->value]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('reports.trial-balance'))
            ->assertOk()
            ->assertSee('Trial Balance')
            ->assertDontSee('রেওয়ামিল');
    }

    public function test_bangla_renders_when_no_choice_is_stored(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::Admin->value]);

        $this->actingAs($user)
            ->get(route('reports.trial-balance'))
            ->assertOk()
            ->assertSee('রেওয়ামিল', false);
    }
}
