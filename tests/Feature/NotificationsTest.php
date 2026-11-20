<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_ticket_notifies_active_admins_only(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
        $technician = User::factory()->technician()->create();

        $this->actingAs($requester)->post('/tickets', [
            'title' => 'VPN is down',
            'description' => 'Cannot connect at all.',
            'category_id' => Category::factory()->create()->id,
            'priority' => 'high',
        ])->assertRedirect();

        Notification::assertSentTo($admin, TicketCreatedNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, TicketCreatedNotification::class);
        Notification::assertNotSentTo($requester, TicketCreatedNotification::class);
        Notification::assertNotSentTo($technician, TicketCreatedNotification::class);
    }

    public function test_assigning_a_ticket_notifies_the_technician(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)
            ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $technician->id])
            ->assertRedirect();

        Notification::assertSentTo($technician, TicketAssignedNotification::class);
        Notification::assertNotSentTo($otherTechnician, TicketAssignedNotification::class);
        Notification::assertNotSentTo($admin, TicketAssignedNotification::class);
    }

    public function test_a_chat_message_notifies_other_technicians_and_admins(): void
    {
        Notification::fake();

        $sender = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);

        $this->actingAs($sender)->postJson('/chat/messages', ['body' => 'Lunch in 10'])->assertCreated();

        Notification::assertSentTo($otherTechnician, ChatMessageNotification::class);
        Notification::assertSentTo($admin, ChatMessageNotification::class);
        Notification::assertNotSentTo($sender, ChatMessageNotification::class);
        Notification::assertNotSentTo($regularUser, ChatMessageNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, ChatMessageNotification::class);
    }

    public function test_notifications_endpoint_lists_only_the_signed_in_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $user->notify(new TicketCreatedNotification(Ticket::factory()->create()));
        $other->notify(new TicketCreatedNotification(Ticket::factory()->create()));

        $response = $this->actingAs($user)->getJson('/notifications')->assertOk();

        $response->assertJsonPath('unread_count', 1);
        $this->assertCount(1, $response->json('notifications'));
        $this->assertStringContainsString('TKT-', $response->json('notifications.0.message'));
    }

    public function test_a_user_can_mark_their_own_notification_read_but_not_someone_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $user->notify(new TicketCreatedNotification(Ticket::factory()->create()));
        $other->notify(new TicketCreatedNotification(Ticket::factory()->create()));

        $ownId = $user->notifications()->sole()->id;
        $otherId = $other->notifications()->sole()->id;

        $this->actingAs($user)->postJson("/notifications/{$otherId}/read")->assertNotFound();

        $this->actingAs($user)->postJson("/notifications/{$ownId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($user->notifications()->sole()->fresh()->read_at);
        $this->assertNull($other->notifications()->sole()->fresh()->read_at);
    }

    public function test_mark_all_read_clears_only_the_signed_in_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $user->notify(new TicketCreatedNotification(Ticket::factory()->create()));
        $user->notify(new ChatMessageNotification(ChatMessage::factory()->create()));
        $other->notify(new TicketCreatedNotification(Ticket::factory()->create()));

        $this->actingAs($user)->postJson('/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_guests_cannot_access_notification_endpoints(): void
    {
        $this->getJson('/notifications')->assertUnauthorized();
        $this->postJson('/notifications/read-all')->assertUnauthorized();
    }
}
