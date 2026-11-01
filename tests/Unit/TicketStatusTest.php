<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    /**
     * @return array<string, array{TicketStatus, TicketStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'open to assigned' => [TicketStatus::Open, TicketStatus::Assigned],
            'assigned to in progress' => [TicketStatus::Assigned, TicketStatus::InProgress],
            'in progress to resolved' => [TicketStatus::InProgress, TicketStatus::Resolved],
            'resolved to closed' => [TicketStatus::Resolved, TicketStatus::Closed],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions(TicketStatus $from, TicketStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    public function test_every_other_transition_is_rejected(): void
    {
        $allowed = array_map(
            fn (array $pair) => $pair[0]->value.'>'.$pair[1]->value,
            self::allowedTransitions(),
        );

        foreach (TicketStatus::cases() as $from) {
            foreach (TicketStatus::cases() as $to) {
                if (in_array($from->value.'>'.$to->value, $allowed, true)) {
                    continue;
                }

                $this->assertFalse(
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value} should be rejected",
                );
            }
        }
    }

    public function test_closed_is_terminal(): void
    {
        $this->assertSame([], TicketStatus::Closed->allowedTransitions());
    }

    public function test_labels(): void
    {
        $this->assertSame('In Progress', TicketStatus::InProgress->label());
        $this->assertSame('Open', TicketStatus::Open->label());
    }
}
