<?php

namespace App\Http\Requests\Technician;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketIndexRequest extends FormRequest
{
    public const ACTIVE = 'active';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAssigned', Ticket::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([self::ACTIVE, ...TicketStatus::values()])],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ];
    }
}
