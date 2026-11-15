<?php

namespace Tests\Feature\Comments;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommentPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    private User $requester;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $this->technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $this->requester = User::factory()->create(['name' => 'Uma User']);
        $this->ticket = Ticket::factory()->assignedTo($this->technician, $this->admin)
            ->create(['user_id' => $this->requester->id, 'status' => TicketStatus::InProgress]);
    }

    private function comment(User $author, string $body = 'Any update?', ?Ticket $ticket = null)
    {
        return $this->actingAs($author)->post(route('tickets.comments.store', $ticket ?? $this->ticket), ['body' => $body]);
    }

    public function test_guests_cannot_comment(): void
    {
        $this->post(route('tickets.comments.store', $this->ticket), ['body' => 'Hi'])->assertRedirect('/login');
        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_requester_comments_on_own_ticket_and_returns_to_their_page(): void
    {
        $this->comment($this->requester, 'Still broken')
            ->assertRedirect(route('tickets.show', $this->ticket).'#comments')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->requester->id,
            'body' => 'Still broken',
        ]);
    }

    public function test_assigned_technician_and_admin_return_to_their_workspace(): void
    {
        $this->comment($this->technician)->assertRedirect(route('technician.tickets.show', $this->ticket).'#comments');
        $this->comment($this->admin)->assertRedirect(route('admin.tickets.show', $this->ticket).'#comments');

        $this->assertSame(2, $this->ticket->comments()->count());
    }

    public function test_other_users_and_unassigned_technicians_cannot_comment(): void
    {
        $this->comment(User::factory()->create())->assertForbidden();
        $this->comment(User::factory()->technician()->create())->assertForbidden();

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_previous_technician_loses_access_after_reassignment(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->technician, $this->admin)->create(['user_id' => $this->requester->id]);
        $ticket->forceFill(['assigned_to' => User::factory()->technician()->create()->id])->save();

        $this->comment($this->technician, 'Hi', $ticket)->assertForbidden();
    }

    public function test_body_is_required_and_limited(): void
    {
        $this->comment($this->requester, '')->assertSessionHasErrors('body', null, 'comment');
        $this->comment($this->requester, str_repeat('a', 2001))->assertSessionHasErrors('body', null, 'comment');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_closed_tickets_reject_comments(): void
    {
        $this->ticket->forceFill(['status' => TicketStatus::Closed])->save();

        $this->comment($this->requester)->assertSessionHasErrors('body', null, 'comment');
        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_ticket_and_author_cannot_be_overridden_from_the_form(): void
    {
        $other = Ticket::factory()->create();

        $this->actingAs($this->requester)->post(route('tickets.comments.store', $this->ticket), [
            'body' => 'Hello',
            'ticket_id' => $other->id,
            'user_id' => $this->admin->id,
        ]);

        $comment = TicketComment::sole();
        $this->assertSame($this->ticket->id, $comment->ticket_id);
        $this->assertSame($this->requester->id, $comment->user_id);
    }

    public function test_thread_is_shown_escaped_on_every_role_page(): void
    {
        TicketComment::factory()->for($this->ticket)->for($this->technician)->create(['body' => '<script>alert(1)</script>']);

        foreach ([
            [$this->requester, route('tickets.show', $this->ticket)],
            [$this->technician, route('technician.tickets.show', $this->ticket)],
            [$this->admin, route('admin.tickets.show', $this->ticket)],
        ] as [$user, $url]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('Comments')
                ->assertSee('Tina Tech')
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_requester_page_does_not_show_other_tickets_comments(): void
    {
        TicketComment::factory()->create(['body' => 'Secret elsewhere']);

        $this->actingAs($this->requester)->get(route('tickets.show', $this->ticket))->assertDontSee('Secret elsewhere');
    }

    public function test_everyone_on_the_ticket_except_the_author_is_notified(): void
    {
        Notification::fake();
        $secondAdmin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $bystander = User::factory()->create();
        $otherTechnician = User::factory()->technician()->create();

        $this->comment($this->requester, 'Any update?');

        Notification::assertSentTo([$this->technician, $this->admin, $secondAdmin], TicketCommentPosted::class);
        Notification::assertNotSentTo([$this->requester, $inactiveAdmin, $bystander, $otherTechnician], TicketCommentPosted::class);
        Notification::assertCount(3);
    }

    public function test_admin_comment_notifies_requester_and_technician_but_not_the_admin(): void
    {
        Notification::fake();

        $this->comment($this->admin);

        Notification::assertSentTo([$this->requester, $this->technician], TicketCommentPosted::class);
        Notification::assertNotSentTo($this->admin, TicketCommentPosted::class);
    }

    public function test_unassigned_ticket_notifies_requester_and_admins_only(): void
    {
        Notification::fake();
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $this->comment($this->admin, 'We will assign this shortly.', $ticket);

        Notification::assertSentTo($this->requester, TicketCommentPosted::class);
        Notification::assertNotSentTo($this->technician, TicketCommentPosted::class);
    }

    public function test_comment_is_rolled_back_when_notifying_fails(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Notification store unavailable'));

        $this->withoutExceptionHandling();

        try {
            $this->comment($this->requester);
            $this->fail('Expected the post to fail.');
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_stored_notification_links_each_recipient_to_their_own_page(): void
    {
        $this->comment($this->requester, 'The screen flickers when I open Outlook');

        $data = $this->technician->notifications()->sole()->data;
        $this->assertSame($this->ticket->id, $data['ticket_id']);
        $this->assertSame('New comment on '.$this->ticket->reference, $data['title']);
        $this->assertSame('Uma User: The screen flickers when I open Outlook', $data['body']);
        $this->assertSame(route('technician.tickets.show', $this->ticket).'#comments', $data['url']);

        $this->assertSame(route('admin.tickets.show', $this->ticket).'#comments', $this->admin->notifications()->sole()->data['url']);
    }
}
