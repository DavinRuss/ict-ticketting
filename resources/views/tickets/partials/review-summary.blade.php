<div class="mt-3 space-y-2 text-sm">
    <p class="text-lg">@include('tickets.partials.rating', ['rating' => $review->rating]) <span class="ms-1 text-sm text-gray-700">{{ $review->rating }}/{{ App\Models\Review::MAX_RATING }}</span></p>
    @if ($review->comment)
        <p class="whitespace-pre-line text-gray-900">{{ $review->comment }}</p>
    @endif
    <p class="text-xs text-gray-500">
        {{ __('Submitted :date', ['date' => $review->created_at->format('d M Y, H:i')]) }}
        @if ($review->updated_at->gt($review->created_at))
            · {{ __('edited :date', ['date' => $review->updated_at->format('d M Y, H:i')]) }}
        @endif
    </p>
</div>
