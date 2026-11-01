<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_role_areas(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/technician')->assertRedirect('/login');
    }

    public function test_regular_users_can_use_their_pages_but_not_admin_or_technician_areas(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();

        $this->actingAs($user)->get('/technician')
            ->assertForbidden()
            ->assertSee('You do not have permission to access this page.');
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($user)->get("/admin/users/{$other->id}/edit")->assertForbidden();
    }

    public function test_regular_users_cannot_create_or_change_accounts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);

        $this->actingAs($user)->patch("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'is_active' => '1',
        ])->assertForbidden();
        $this->assertSame(UserRole::User, $user->fresh()->role);
    }

    public function test_technicians_can_open_the_technician_area_but_not_admin_screens(): void
    {
        $technician = User::factory()->technician()->create();

        $this->actingAs($technician)->get('/technician')->assertOk()->assertSee('Technician workspace');
        $this->actingAs($technician)->get('/dashboard')->assertOk();
        $this->actingAs($technician)->get('/admin/users')->assertForbidden();
        $this->actingAs($technician)->post('/admin/users', [])->assertForbidden();
    }

    public function test_admins_can_open_admin_screens_but_not_the_technician_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();
        $this->actingAs($admin)->get('/technician')->assertForbidden();
    }

    public function test_navigation_only_shows_links_for_the_users_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertDontSee(route('admin.users.index'))
            ->assertDontSee(route('technician.dashboard'));

        $this->actingAs(User::factory()->technician()->create())->get('/dashboard')
            ->assertSee(route('technician.dashboard'))
            ->assertDontSee(route('admin.users.index'));

        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
            ->assertSee(route('admin.users.index'))
            ->assertDontSee(route('technician.dashboard'));
    }

    public function test_users_cannot_change_their_own_role_through_the_profile_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'New Name',
            'email' => $user->email,
            'role' => 'admin',
            'is_active' => '0',
        ])->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_role_and_active_flag_are_not_mass_assignable(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => false]);

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_a_user_deactivated_while_signed_in_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Please contact your IT administrator.']);
        $this->assertGuest();
    }
}
