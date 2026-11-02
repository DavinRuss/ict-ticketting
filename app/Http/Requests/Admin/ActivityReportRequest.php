<?php

namespace App\Http\Requests\Admin;

use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Ticket::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fromRules = ['nullable', 'date_format:Y-m-d'];
        $toRules = ['nullable', 'date_format:Y-m-d'];

        if ($this->filled('to')) {
            $fromRules[] = 'before_or_equal:to';
        }

        if ($this->filled('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        return [
            'from' => $fromRules,
            'to' => $toRules,
            'action' => ['nullable', Rule::in(TicketActivity::ACTIONS)],
            'ticket_reference' => ['nullable', 'string', 'max:20'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
