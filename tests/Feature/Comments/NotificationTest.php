<?php

namespace Tests\Feature\Comments;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    private User $requester;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->technician = User::factory()->technician()->create();
        $this->requester = User::factory()->create(['name' => 'Uma User']);
        $this->ticket = Ticket::factory()->assignedTo($this->technician, $this->admin)
            ->create(['user_id' => $this->requester->id, 'status' => TicketStatus::InProgress]);
    }

    private function postComment(User $author, string $body = 'Any update?', ?Ticket $ticket = null): void
    {
        app(TicketService::class)->addComment($ticket ?? $this->ticket, $author, $body);
    }

    public function test_guests_cannot_reach_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect('/login');
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
        $this->post(route('notifications.read'))->assertRedirect('/login');
    }

    public function test_feed_returns_unread_count_and_items(): void
    {
        $this->postComment($this->requester, 'First');
        $this->travel(1)->minute();
        $this->postComment($this->requester, 'Second');

        $response = $this->actingAs($this->technician)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.title', 'New comment on '.$this->ticket->reference)
            ->assertJsonPath('notifications.0.body', 'Uma User: Second');

        $id = $response->json('notifications.0.id');
        $response->assertJsonPath('notifications.0.url', route('notifications.open', $id));
    }

    public function test_feed_only_returns_the_signed_in_users_notifications(): void
    {
        $this->postComment($this->requester);

        $this->actingAs($this->requester)->getJson(route('notifications.feed'))
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    public function test_feed_is_capped(): void
    {
        foreach (range(1, 12) as $i) {
            $this->postComment($this->requester, "Message {$i}");
        }

        $this->actingAs($this->admin)->getJson(route('notifications.feed'))
            ->assertJsonPath('unread_count', 12)
            ->assertJsonCount(10, 'notifications');
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_ticket(): void
    {
        $this->postComment($this->requester);
        $notification = $this->technician->notifications()->sole();

        $this->actingAs($this->technician)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('technician.tickets.show', $this->ticket).'#comments');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $this->postComment($this->requester);
        $notification = $this->technician->notifications()->sole();

        $this->actingAs($this->admin)->get(route('notifications.open', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_viewing_the_ticket_marks_its_notifications_read(): void
    {
        $other = Ticket::factory()->create(['user_id' => $this->requester->id]);
        $this->postComment($this->technician, 'On this ticket');
        $this->postComment($this->admin, 'On another ticket', $other);

        $this->actingAs($this->requester)->get(route('tickets.show', $this->ticket))->assertOk();

        $this->assertSame(1, $this->requester->unreadNotifications()->count());
        $this->assertSame($other->id, $this->requester->unreadNotifications()->sole()->data['ticket_id']);
    }

    public function test_index_lists_notifications_and_mark_all_read_clears_them(): void
    {
        $this->postComment($this->requester, 'Printer jam again');

        $this->actingAs($this->admin)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Uma User: Printer jam again')
            ->assertSee('Mark all as read');

        $this->post(route('notifications.read'))->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_layout_shows_unread_badge_and_desktop_notification_hooks(): void
    {
        $this->postComment($this->requester);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-feed-url="'.route('notifications.feed').'"', false)
            ->assertSee('Enable desktop notifications')
            ->assertSee('data-notification-count', false);
    }
}
