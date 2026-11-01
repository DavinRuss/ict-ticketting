<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
        ];
    }

    public function assignedTo(User $technician, ?User $assigner = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Assigned,
            'assigned_to' => $technician->id,
            'assigned_by' => $assigner?->id ?? User::factory()->admin(),
            'assigned_at' => now(),
        ]);
    }
}
