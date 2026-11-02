<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
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

    public function test_admin_dashboard_summarizes_global_services_and_technician_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $requester = User::factory()->create();
        $hardware = Category::factory()->create(['name' => 'Hardware']);
        $software = Category::factory()->create(['name' => 'Software']);
        $tickets = app(TicketService::class);

        Ticket::factory()->for($requester, 'requester')->for($hardware, 'category')->create(['title' => 'Open printer request']);
        Ticket::factory()->for($requester, 'requester')->for($software, 'category')->assignedTo($technician, $admin)->create(['title' => 'Assigned software request']);

        $started = Ticket::factory()->for($requester, 'requester')->for($software, 'category')->assignedTo($technician, $admin)->create();
        $tickets->changeStatus($started, TicketStatus::InProgress, $technician);

        $resolved = Ticket::factory()->for($requester, 'requester')->for($hardware, 'category')->assignedTo($technician, $admin)->create();
        $tickets->changeStatus($resolved, TicketStatus::InProgress, $technician);
        $tickets->changeStatus($resolved, TicketStatus::Resolved, $technician);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Service operations')
            ->assertSee('Daily service flow')
            ->assertSee('Technician progress')
            ->assertViewHas('statusCounts', fn ($counts) => $counts['open'] === 1
                && $counts['assigned'] === 1
                && $counts['in_progress'] === 1
                && $counts['resolved'] === 1
                && $counts->sum() === 4)
            ->assertViewHas('dailyActivity', fn ($days) => $days->last()['opened'] === 4
                && $days->last()['started'] === 2
                && $days->last()['resolved'] === 1)
            ->assertViewHas('serviceMix', fn ($mix) => $mix->pluck('name')->all() === ['Hardware', 'Software'])
            ->assertViewHas('technicians', fn ($technicians) => $technicians->firstWhere('id', $technician->id)->started_count === 2
                && $technicians->firstWhere('id', $technician->id)->resolved_count === 1);
    }

    public function test_requester_and_technician_dashboards_only_include_their_ticket_scopes(): void
    {
        $requester = User::factory()->create();
        $otherRequester = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();
        $privateTicket = Ticket::factory()->for($requester, 'requester')->create(['title' => 'Requester private ticket']);
        Ticket::factory()->for($otherRequester, 'requester')->create(['title' => 'Another requester ticket']);
        $assignedTicket = Ticket::factory()->for($otherRequester, 'requester')->assignedTo($technician)->create(['title' => 'Technician assignment']);
        Ticket::factory()->for($otherRequester, 'requester')->assignedTo($otherTechnician)->create(['title' => 'Other technician assignment']);

        $this->actingAs($requester)->get('/dashboard')
            ->assertOk()
            ->assertSee('Requester private ticket')
            ->assertDontSee('Another requester ticket')
            ->assertViewHas('statusCounts', fn ($counts) => $counts->sum() === 1 && $counts['open'] === 1);

        $this->actingAs($technician)->get('/dashboard')
            ->assertOk()
            ->assertSee('Technician assignment')
            ->assertDontSee('Other technician assignment')
            ->assertViewHas('statusCounts', fn ($counts) => $counts->sum() === 1 && $counts['assigned'] === 1);

        $this->assertSame($requester->id, $privateTicket->fresh()->user_id);
        $this->assertSame($technician->id, $assignedTicket->fresh()->assigned_to);
    }

    public function test_admin_can_filter_dashboard_to_a_specific_active_technician(): void
    {
        $admin = User::factory()->admin()->create();
        $tina = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $tom = User::factory()->technician()->create(['name' => 'Tom Tech']);
        $requester = User::factory()->create();
        $hardware = Category::factory()->create(['name' => 'Hardware']);
        $software = Category::factory()->create(['name' => 'Software']);
        $tickets = app(TicketService::class);

        $tinaTicket = Ticket::factory()->for($requester, 'requester')->for($hardware, 'category')->assignedTo($tina, $admin)->create();
        $tickets->changeStatus($tinaTicket, TicketStatus::InProgress, $admin);
        $tickets->changeStatus($tinaTicket, TicketStatus::Resolved, $tina);
        Ticket::factory()->for($requester, 'requester')->for($software, 'category')->assignedTo($tom, $admin)->create();

        $this->actingAs($admin)->get('/dashboard?technician_id='.$tina->id)
            ->assertOk()
            ->assertSee('Service activity for Tina Tech.')
            ->assertViewHas('statusCounts', fn ($counts) => $counts->sum() === 1 && $counts['resolved'] === 1)
            ->assertViewHas('dailyActivity', fn ($days) => $days->sum('opened') === 1
                && $days->sum('started') === 0
                && $days->sum('resolved') === 1)
            ->assertViewHas('serviceMix', fn ($mix) => $mix->pluck('name')->all() === ['Hardware'])
            ->assertViewHas('technicians', fn ($technicians) => $technicians->pluck('id')->all() === [$tina->id])
            ->assertViewHas('technicians', fn ($technicians) => $technicians->first()->started_count === 0
                && $technicians->first()->resolved_count === 1)
            ->assertViewHas('technicianOptions', fn ($technicians) => $technicians->pluck('id')->sort()->values()->all() === [$tina->id, $tom->id])
            ->assertViewHas('recentActivity', fn ($activities) => $activities->every(fn ($activity) => $activity->ticket->assigned_to === $tina->id));

        $this->actingAs($admin)->get('/dashboard?technician_id='.$tom->id)
            ->assertOk()->assertSee('Service activity for Tom Tech.');

        $this->actingAs($admin)->get('/dashboard?technician_id='.$requester->id)->assertSessionHasErrors('technician_id');
        $this->actingAs(User::factory()->create())->get('/dashboard?technician_id='.$tina->id)->assertSessionHasErrors('technician_id');
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

    public function test_theme_toggle_is_available_on_guest_and_authenticated_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Rivers State Internal Revenue Service')
            ->assertSee('rirs_logo_enhanced.png')
            ->assertSee('rirs_logo_transparent.png')
            ->assertSee('<title>IT Helpdesk</title>', false)
            ->assertDontSee('viewBox="0 0 316 316"', false)
            ->assertDontSee('Laravel Logo');

        $this->get('/login')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('rirs_logo_enhanced.png')
            ->assertSee('rirs_logo_transparent.png')
            ->assertDontSee('viewBox="0 0 316 316"', false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('rirs_logo_enhanced.png')
            ->assertSee('rirs_logo_transparent.png')
            ->assertDontSee('viewBox="0 0 316 316"', false);
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
