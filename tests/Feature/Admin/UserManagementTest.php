<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Alice Admin']);
    }

    public function test_admin_sees_a_paginated_account_list(): void
    {
        User::factory()->count(20)->create();

        $response = $this->actingAs($this->admin)->get('/admin/users')->assertOk();

        $this->assertCount(15, $response->viewData('users'));
        $this->assertSame(21, $response->viewData('users')->total());
    }

    public function test_admin_can_search_and_filter_accounts_by_role(): void
    {
        User::factory()->technician()->create(['name' => 'Terry Tech', 'email' => 'terry@example.com']);
        User::factory()->create(['name' => 'Uma User', 'email' => 'uma@example.com']);

        $this->actingAs($this->admin)->get('/admin/users?search=terry')
            ->assertOk()->assertSee('Terry Tech')->assertDontSee('Uma User');

        $this->actingAs($this->admin)->get('/admin/users?role=technician')
            ->assertOk()->assertSee('Terry Tech')->assertDontSee('Uma User');

        $this->actingAs($this->admin)->get('/admin/users?role=superuser')
            ->assertSessionHasErrors('role');
    }

    public function test_admin_can_create_an_account_with_a_role(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Terry Tech',
            'email' => 'terry@example.com',
            'phone' => '+234 800 000 0000',
            'role' => 'technician',
            'password' => 'initial-pass',
            'password_confirmation' => 'initial-pass',
        ])->assertRedirect('/admin/users')->assertSessionHas('success', 'Account created for terry@example.com.');

        $user = User::where('email', 'terry@example.com')->firstOrFail();
        $this->assertSame(UserRole::Technician, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('+234 800 000 0000', $user->phone);
        $this->assertTrue(Hash::check('initial-pass', $user->password));
    }

    public function test_account_creation_is_validated(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->admin)->from('/admin/users/create')->post('/admin/users', [
            'name' => '',
            'email' => $existing->email,
            'role' => 'superuser',
            'password' => 'initial-pass',
            'password_confirmation' => 'different',
        ])->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['name', 'email', 'role', 'password']);

        $this->assertSame(2, User::count());
    }

    public function test_admin_can_change_another_accounts_role_and_deactivate_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}", [
            'name' => 'Promoted Person',
            'email' => $user->email,
            'role' => 'technician',
            'is_active' => '0',
        ])->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Promoted Person', $user->name);
        $this->assertSame(UserRole::Technician, $user->role);
        $this->assertFalse($user->is_active);
    }

    public function test_admin_can_reactivate_an_account(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'user',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->from("/admin/users/{$this->admin->id}/edit")->patch("/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'user',
            'is_active' => '0',
        ])->assertRedirect("/admin/users/{$this->admin->id}/edit")->assertSessionHasErrors([
            'role' => 'You cannot change your own role.',
            'is_active' => 'You cannot deactivate your own account.',
        ]);

        $this->admin->refresh();
        $this->assertSame(UserRole::Admin, $this->admin->role);
        $this->assertTrue($this->admin->is_active);
    }

    public function test_admin_can_update_their_own_details_without_role_fields(): void
    {
        $this->actingAs($this->admin)->get("/admin/users/{$this->admin->id}/edit")
            ->assertOk()
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="is_active"', false);

        $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}", [
            'name' => 'Alice A.',
            'email' => $this->admin->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Alice A.', $this->admin->fresh()->name);
    }

    public function test_email_must_stay_unique_when_editing(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $other->email,
            'role' => 'user',
            'is_active' => '1',
        ])->assertSessionHasErrors('email');
    }

    public function test_promote_admin_command_promotes_an_existing_account(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'boss@example.com']);

        $this->artisan('app:promote-admin', ['email' => 'boss@example.com'])
            ->expectsConfirmation("Make {$user->name} <boss@example.com> an active administrator?", 'yes')
            ->expectsOutput('boss@example.com is now an administrator.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_promote_admin_command_changes_nothing_when_declined_or_unknown(): void
    {
        $user = User::factory()->create(['email' => 'someone@example.com']);

        $this->artisan('app:promote-admin', ['email' => 'someone@example.com'])
            ->expectsConfirmation("Make {$user->name} <someone@example.com> an active administrator?", 'no')
            ->assertExitCode(1);
        $this->assertSame(UserRole::User, $user->fresh()->role);

        $this->artisan('app:promote-admin', ['email' => 'nobody@example.com'])
            ->expectsOutput('No account found for nobody@example.com.')
            ->assertExitCode(1);
    }
}
