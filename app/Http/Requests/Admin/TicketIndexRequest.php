<?php

namespace App\Http\Requests\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketIndexRequest extends FormRequest
{
    public const UNASSIGNED = 'unassigned';

    public function authorize(): bool
    {
        return $this->user()?->can('manage', Ticket::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'technician' => ['nullable', Rule::when(
                $this->input('technician') !== self::UNASSIGNED,
                ['integer', Rule::exists('users', 'id')->where(fn ($query) => self::filterableTechnicians($query))],
            )],
        ];
    }

    /**
     * Current technicians plus users who still hold an assignment.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function filterableTechnicians($query)
    {
        return $query->where(fn ($query) => $query
            ->where('role', UserRole::Technician->value)
            ->orWhereIn('id', Ticket::query()->select('assigned_to')->whereNotNull('assigned_to')));
    }
}
