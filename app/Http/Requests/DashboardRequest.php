<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'technician_id' => $this->user()->isAdmin()
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Technician->value)->where('is_active', true)]
                : ['prohibited'],
        ];
    }
}
