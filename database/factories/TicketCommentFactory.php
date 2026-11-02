<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => fn (array $attributes) => Ticket::findOrFail($attributes['ticket_id'])->user_id,
            'body' => fake()->paragraph(),
        ];
    }
}
