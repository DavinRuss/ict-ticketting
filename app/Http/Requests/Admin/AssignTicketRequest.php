<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    protected $errorBag = 'assignment';

    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'technician_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Technician->value)->where('is_active', true),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technician_id.required' => __('Choose a technician.'),
            'technician_id.exists' => __('Tickets can only be assigned to an active technician.'),
        ];
    }
}
