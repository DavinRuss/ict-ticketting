<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Review;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory()->state(['status' => TicketStatus::Resolved, 'resolved_at' => now()]),
            'user_id' => fn (array $attributes) => Ticket::findOrFail($attributes['ticket_id'])->user_id,
            'rating' => fake()->numberBetween(Review::MIN_RATING, Review::MAX_RATING),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
