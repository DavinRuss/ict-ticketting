<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
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

    public function test_each_role_may_only_set_its_own_part_of_the_workflow(): void
    {
        $this->assertSame([TicketStatus::InProgress], TicketStatus::Assigned->transitionsFor(UserRole::Technician));
        $this->assertSame([TicketStatus::Resolved], TicketStatus::InProgress->transitionsFor(UserRole::Technician));
        $this->assertSame([], TicketStatus::Resolved->transitionsFor(UserRole::Technician));
        $this->assertSame([], TicketStatus::Open->transitionsFor(UserRole::Technician));

        $this->assertSame([TicketStatus::InProgress], TicketStatus::Assigned->transitionsFor(UserRole::Admin));
        $this->assertSame([TicketStatus::Closed], TicketStatus::Resolved->transitionsFor(UserRole::Admin));
        $this->assertSame([], TicketStatus::Open->transitionsFor(UserRole::Admin));

        foreach (TicketStatus::cases() as $status) {
            $this->assertSame([], $status->transitionsFor(UserRole::User));
            $this->assertNotContains(TicketStatus::Assigned, $status->transitionsFor(UserRole::Admin));
        }
    }
}
