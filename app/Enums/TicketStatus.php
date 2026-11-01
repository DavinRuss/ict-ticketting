<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * The single source of truth for the status workflow:
     * open -> assigned -> in_progress -> resolved -> closed.
     * Reassignment keeps an assigned ticket assigned.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned],
            self::Assigned => [self::Assigned, self::InProgress],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Statuses a role may set from this one with a status change. Assigning is
     * separate (it needs a technician) and only admins do it.
     * Technicians resolve their own work directly; confirming it by closing a
     * resolved ticket is for the requester or an admin.
     *
     * @return list<self>
     */
    public function transitionsFor(UserRole $role): array
    {
        $permitted = match ($role) {
            UserRole::Admin => [self::InProgress, self::Resolved, self::Closed],
            UserRole::Technician => [self::InProgress, self::Resolved],
            UserRole::User => [],
        };

        return array_values(array_filter(
            $this->allowedTransitions(),
            fn (self $next) => in_array($next, $permitted, true),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-800',
            self::Assigned => 'bg-indigo-100 text-indigo-800',
            self::InProgress => 'bg-yellow-100 text-yellow-800',
            self::Resolved => 'bg-green-100 text-green-800',
            self::Closed => 'bg-gray-200 text-gray-700',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
