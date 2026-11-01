<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Status states build on each other (assigned -> inProgress -> resolved -> closed)
 * so every ticket has the timestamps and assignment its status implies.
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = Carbon::instance(fake()->dateTimeBetween('-30 days', '-7 days'));

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim(fake()->sentence(6), '.'),
            'description' => fake()->paragraphs(2, true),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function assigned(?User $technician = null, ?User $admin = null): static
    {
        return $this->state(function (array $attributes) use ($technician, $admin) {
            $assignedAt = Carbon::parse($attributes['created_at'])->addHours(fake()->numberBetween(1, 12));

            return [
                'status' => TicketStatus::Assigned,
                'assigned_to' => $technician ?? User::factory()->technician(),
                'assigned_by' => $admin ?? User::factory()->admin(),
                'assigned_at' => $assignedAt,
                'updated_at' => $assignedAt,
            ];
        });
    }

    public function inProgress(?User $technician = null, ?User $admin = null): static
    {
        return $this->assigned($technician, $admin)->state(function (array $attributes) {
            $startedAt = Carbon::parse($attributes['assigned_at'])->addHours(fake()->numberBetween(1, 24));

            return [
                'status' => TicketStatus::InProgress,
                'started_at' => $startedAt,
                'updated_at' => $startedAt,
            ];
        });
    }

    public function resolved(?User $technician = null, ?User $admin = null): static
    {
        return $this->inProgress($technician, $admin)->state(function (array $attributes) {
            $resolvedAt = Carbon::parse($attributes['started_at'])->addHours(fake()->numberBetween(1, 48));

            return [
                'status' => TicketStatus::Resolved,
                'resolved_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ];
        });
    }

    public function closed(?User $technician = null, ?User $admin = null): static
    {
        return $this->resolved($technician, $admin)->state(function (array $attributes) {
            $closedAt = Carbon::parse($attributes['resolved_at'])->addHours(fake()->numberBetween(1, 24));

            return [
                'status' => TicketStatus::Closed,
                'closed_at' => $closedAt,
                'updated_at' => $closedAt,
            ];
        });
    }
}
