<?php

namespace App\Http\Requests\Admin;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivitySelectionRequest extends FormRequest
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
        return [
            'activity_ids' => ['required', 'array', 'min:1', 'max:25'],
            'activity_ids.*' => ['required', 'integer', 'distinct', Rule::exists('ticket_activities', 'id')],
        ];
    }
}
