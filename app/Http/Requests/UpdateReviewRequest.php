<?php

namespace App\Http\Requests;

class UpdateReviewRequest extends StoreReviewRequest
{
    public function authorize(): bool
    {
        $review = $this->route('ticket')->review;

        return $review !== null && $this->user()->can('update', $review);
    }
}
