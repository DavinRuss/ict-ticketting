<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_reference_is_generated_from_id(): void
    {
        $first = Ticket::factory()->create();
        $second = Ticket::factory()->create();

        $this->assertSame(sprintf('TKT-%06d', $first->id), $first->fresh()->reference);
        $this->assertSame(sprintf('TKT-%06d', $second->id), $second->fresh()->reference);
        $this->assertNotSame($first->reference, $second->reference);
    }

    public function test_enum_and_date_casts(): void
    {
        $ticket = Ticket::factory()->resolved()->create()->fresh();

        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertSame(UserRole::Technician, $ticket->technician->role);
        $this->assertSame(UserRole::Admin, $ticket->assigner->role);
        $this->assertTrue($ticket->started_at->lt($ticket->resolved_at));
    }

    public function test_relationships(): void
    {
        $requester = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $ticket = Ticket::factory()
            ->for($requester, 'requester')
            ->for($category)
            ->resolved($technician, $admin)
            ->create();

        $ticket->comments()->create(['user_id' => $technician->id, 'body' => 'On it.']);
        $ticket->activities()->create([
            'user_id' => $admin->id,
            'action' => 'assigned',
            'from_status' => TicketStatus::Open,
            'to_status' => TicketStatus::Assigned,
        ]);
        Review::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $requester->id, 'rating' => 5]);

        $ticket = $ticket->fresh(['requester', 'category', 'technician', 'assigner', 'comments.user', 'activities.user', 'review.user']);

        $this->assertTrue($ticket->requester->is($requester));
        $this->assertTrue($ticket->category->is($category));
        $this->assertTrue($ticket->technician->is($technician));
        $this->assertTrue($ticket->assigner->is($admin));
        $this->assertTrue($ticket->comments->first()->user->is($technician));
        $this->assertSame(TicketStatus::Assigned, $ticket->activities->first()->to_status);
        $this->assertSame(5, $ticket->review->rating);

        $this->assertTrue($requester->tickets->first()->is($ticket));
        $this->assertTrue($technician->assignedTickets->first()->is($ticket));
        $this->assertTrue($category->tickets->first()->is($ticket));
    }

    public function test_only_one_review_per_ticket(): void
    {
        $review = Review::factory()->create();

        $this->expectException(QueryException::class);

        Review::factory()->create(['ticket_id' => $review->ticket_id, 'user_id' => $review->user_id]);
    }

    public function test_users_with_tickets_cannot_be_deleted(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(QueryException::class);

        $ticket->requester->delete();
    }

    public function test_tickets_with_history_cannot_be_deleted(): void
    {
        $ticket = Ticket::factory()->create();
        $ticket->activities()->create(['action' => 'created', 'to_status' => TicketStatus::Open]);

        $this->expectException(QueryException::class);

        $ticket->delete();
    }

    public function test_seeder_creates_expected_sample_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertTrue(User::where('email', 'admin@example.com')->where('role', UserRole::Admin)->exists());
        $this->assertSame(2, User::where('role', UserRole::Technician)->count());
        $this->assertSame(3, User::where('role', UserRole::User)->count());
        $this->assertSame(6, Category::count());
        $this->assertSame(15, Ticket::count());
        $this->assertSame(0, Ticket::whereNull('reference')->count());

        foreach (TicketStatus::cases() as $status) {
            $this->assertGreaterThan(0, Ticket::where('status', $status)->count(), "No {$status->value} tickets seeded");
        }

        $this->assertSame(0, Ticket::where('status', TicketStatus::Open)->whereNotNull('assigned_to')->count());
        $this->assertSame(0, Ticket::where('status', '!=', TicketStatus::Open)->whereNull('assigned_to')->count());
        $this->assertSame(Ticket::count(), TicketActivity::where('action', 'created')->count());
        $this->assertSame(0, Review::whereHas('ticket', fn ($q) => $q->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed]))->count());
    }
}
