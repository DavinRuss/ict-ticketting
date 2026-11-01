<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    protected $errorBag = 'review';

    public function authorize(): bool
    {
        return $this->user()->can('create', [Review::class, $this->route('ticket')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:'.Review::MIN_RATING.','.Review::MAX_RATING],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => __('Choose a rating from :min to :max stars.', ['min' => Review::MIN_RATING, 'max' => Review::MAX_RATING]),
            'rating.integer' => __('Choose a rating from :min to :max stars.', ['min' => Review::MIN_RATING, 'max' => Review::MAX_RATING]),
            'rating.between' => __('Choose a rating from :min to :max stars.', ['min' => Review::MIN_RATING, 'max' => Review::MAX_RATING]),
        ];
    }
}
