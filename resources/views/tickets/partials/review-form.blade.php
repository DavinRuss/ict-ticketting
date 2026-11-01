@php($current = (int) old('rating', $review?->rating))
<form method="POST" action="{{ $review ? route('tickets.review.update', $ticket) : route('tickets.review.store', $ticket) }}" class="mt-4 space-y-4">
    @csrf
    @if ($review)
        @method('PUT')
    @endif
    <fieldset>
        <legend class="block font-medium text-sm text-gray-700">{{ __('Rating') }}</legend>
        <div class="mt-2 flex flex-wrap gap-4">
            @foreach (range(App\Models\Review::MIN_RATING, App\Models\Review::MAX_RATING) as $value)
                <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                    <input type="radio" name="rating" value="{{ $value }}" required @checked($current === $value) class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    {{ trans_choice(':count star|:count stars', $value) }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->review->get('rating')" />
    </fieldset>
    <div>
        <x-input-label for="comment" :value="__('Comment (optional)')" />
        <textarea id="comment" name="comment" rows="3" maxlength="1000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('comment', $review?->comment) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->review->get('comment')" />
    </div>
    <x-primary-button>{{ $review ? __('Update review') : __('Submit review') }}</x-primary-button>
</form>
