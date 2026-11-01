<?php

namespace Tests\Feature\Admin;

use App\Enums\TicketStatus;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function review(int $rating, ?User $technician = null, array $attributes = []): Review
    {
        $ticket = Ticket::factory()->assignedTo($technician ?? User::factory()->technician()->create(), $this->admin)
            ->create(['status' => TicketStatus::Resolved]);

        return Review::factory()->for($ticket)->for($ticket->requester)->create(['rating' => $rating, ...$attributes]);
    }

    public function test_only_admins_can_open_the_feedback_page(): void
    {
        $this->get('/admin/reviews')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/reviews')->assertForbidden();
        $this->actingAs(User::factory()->technician()->create())->get('/admin/reviews')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/reviews')->assertOk()->assertSee('No reviews have been submitted yet.');
    }

    public function test_admin_sees_every_review_with_its_ticket_and_technician(): void
    {
        $tech = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $review = $this->review(4, $tech, ['comment' => 'Sorted my VPN quickly.']);

        $this->actingAs($this->admin)->get('/admin/reviews')
            ->assertOk()
            ->assertSee($review->ticket->reference)
            ->assertSee('Sorted my VPN quickly.')
            ->assertSee('Tina Tech')
            ->assertSee($review->user->name)
            ->assertSee('Average 4.0 / 5 from 1 review');

        $this->actingAs($this->admin)->get("/admin/tickets/{$review->ticket_id}")
            ->assertOk()->assertSee('Requester feedback')->assertSee('Sorted my VPN quickly.')->assertSee('4/5');
    }

    public function test_feedback_is_paginated_and_filterable(): void
    {
        $tech = User::factory()->technician()->create();
        foreach (range(1, 15) as $i) {
            $this->review(5);
        }
        $low = $this->review(1, $tech);
        $mid = $this->review(3, $tech);

        $this->actingAs($this->admin)->get('/admin/reviews')
            ->assertViewHas('reviews', fn ($reviews) => $reviews->count() === 15 && $reviews->total() === 17);
        $this->actingAs($this->admin)->get('/admin/reviews?page=2')
            ->assertViewHas('reviews', fn ($reviews) => $reviews->count() === 2);

        $this->actingAs($this->admin)->get('/admin/reviews?rating=1')
            ->assertViewHas('reviews', fn ($reviews) => $reviews->pluck('id')->all() === [$low->id]);
        $this->actingAs($this->admin)->get("/admin/reviews?technician={$tech->id}")
            ->assertViewHas('reviews', fn ($reviews) => $reviews->pluck('id')->sort()->values()->all() === [$low->id, $mid->id])
            ->assertViewHas('average', fn ($average) => (float) $average === 2.0);
        $this->actingAs($this->admin)->get("/admin/reviews?technician={$tech->id}&rating=5")
            ->assertOk()->assertSee('No reviews match these filters.');

        $this->actingAs($this->admin)->get('/admin/reviews?rating=6')->assertSessionHasErrors('rating');
        $this->actingAs($this->admin)->get('/admin/reviews?technician=999999')->assertSessionHasErrors('technician');
    }

    public function test_ticket_page_says_when_there_is_no_review_yet(): void
    {
        $open = Ticket::factory()->create();
        $resolved = Ticket::factory()->create(['status' => TicketStatus::Resolved]);

        $this->actingAs($this->admin)->get("/admin/tickets/{$open->id}")->assertSee('The requester can review this ticket once it is resolved.');
        $this->actingAs($this->admin)->get("/admin/tickets/{$resolved->id}")->assertSee('The requester has not reviewed this ticket yet.');
    }

    public function test_technicians_do_not_see_reviews_on_their_tickets(): void
    {
        $tech = User::factory()->technician()->create();
        $review = $this->review(2, $tech, ['comment' => 'Took three days.']);

        $this->actingAs($tech)->get("/technician/tickets/{$review->ticket_id}")->assertOk()->assertDontSee('Took three days.');
    }
}
