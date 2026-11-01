<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public function test_workflow_moves_forward_one_step_at_a_time(): void
    {
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Assigned));
        $this->assertTrue(TicketStatus::Assigned->canTransitionTo(TicketStatus::InProgress));
        $this->assertTrue(TicketStatus::InProgress->canTransitionTo(TicketStatus::Resolved));
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::Closed));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Resolved));
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Closed));
        $this->assertFalse(TicketStatus::InProgress->canTransitionTo(TicketStatus::Open));
        $this->assertSame([], TicketStatus::Closed->allowedTransitions());
    }
}
