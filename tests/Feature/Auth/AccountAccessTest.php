<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_authenticated_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->put('/password', [])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_users_can_view_the_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Ada User']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ada User')
            ->assertSee('User');
    }

    public function test_authenticated_users_are_redirected_away_from_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/register')->assertRedirect('/dashboard');
    }

    public function test_unknown_email_cannot_log_in(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors([
            'email' => 'This account has been deactivated. Please contact your IT administrator.',
        ]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create();

        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_registration_always_creates_a_regular_active_user(): void
    {
        $this->post('/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'is_active' => '0',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'mallory@example.com')->firstOrFail();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_reset_link_request_gives_the_same_response_for_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $message = 'If an account exists for that email address, we have emailed a password reset link.';

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $message);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $message);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_cannot_be_reset_with_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');

        $this->post('/login', ['email' => $user->email, 'password' => 'new-password']);
        $this->assertGuest();
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }

    public function test_create_admin_command_creates_an_admin_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'it-admin@example.com', '--name' => 'IT Admin'])
            ->expectsQuestion('Password', 'a-strong-password')
            ->expectsQuestion('Confirm password', 'a-strong-password')
            ->expectsOutput('Admin account created for it-admin@example.com.')
            ->assertExitCode(0);

        $admin = User::where('email', 'it-admin@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('IT Admin', $admin->name);

        $this->post('/login', ['email' => 'it-admin@example.com', 'password' => 'a-strong-password']);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_create_admin_command_rejects_invalid_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:create-admin', ['email' => 'taken@example.com'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
    }
}
