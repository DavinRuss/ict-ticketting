<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/chat')->assertRedirect('/login');
        $this->post('/chat/messages', ['body' => 'hi'])->assertRedirect('/login');
    }

    public function test_regular_users_cannot_use_the_chat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/chat')->assertForbidden();
        $this->actingAs($user)->postJson('/chat/messages', ['body' => 'hi'])->assertForbidden();
    }

    public function test_technicians_and_admins_can_open_the_chat_and_see_messages(): void
    {
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        ChatMessage::factory()->for($technician)->create(['body' => 'Server room is cold']);

        foreach ([$technician, $admin] as $actor) {
            $this->actingAs($actor)->get('/chat')
                ->assertOk()
                ->assertSee('Team chat')
                ->assertSee('Server room is cold')
                ->assertSee('Tina Tech');
        }
    }

    public function test_an_admin_can_send_a_message_and_it_broadcasts(): void
    {
        Event::fake([ChatMessageSent::class]);

        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);

        $this->actingAs($admin)
            ->postJson('/chat/messages', ['body' => 'Hello team'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Hello team')
            ->assertJsonPath('message.author.name', 'Ada Admin');

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $admin->id,
            'body' => 'Hello team',
        ]);

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_a_technician_can_send_a_message(): void
    {
        Event::fake([ChatMessageSent::class]);

        $technician = User::factory()->technician()->create();

        $this->actingAs($technician)
            ->postJson('/chat/messages', ['body' => 'On it'])
            ->assertCreated();

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $technician->id,
            'body' => 'On it',
        ]);
    }

    public function test_message_body_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/chat/messages', [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($admin)->postJson('/chat/messages', ['body' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_service_rejects_a_non_role_user(): void
    {
        $user = User::factory()->create();

        try {
            app(ChatService::class)->send($user, 'hello');
            $this->fail('A regular user must not send chat messages.');
        } catch (ValidationException) {
            $this->assertSame(0, ChatMessage::count());
        }
    }
}
