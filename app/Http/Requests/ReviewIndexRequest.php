<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Review::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'between:'.Review::MIN_RATING.','.Review::MAX_RATING],
            'technician' => ['nullable', 'integer', Rule::exists('tickets', 'assigned_to')],
        ];
    }
}
