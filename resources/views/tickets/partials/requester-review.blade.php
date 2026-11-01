@if ($ticket->status === App\Enums\TicketStatus::Resolved)
    <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
        <h3 class="text-lg font-medium text-gray-900">{{ __('Is the problem fixed?') }}</h3>
        <p class="mt-1 text-sm text-gray-600">{{ __("The technician has marked this ticket resolved. If everything works, confirm the fix to close the ticket. If it still isn't working, contact the IT team instead of closing it.") }}</p>
        <form method="POST" action="{{ route('tickets.close', $ticket) }}" class="mt-4">
            @csrf
            @method('PUT')
            <x-primary-button>{{ __('Confirm fix and close') }}</x-primary-button>
        </form>
        <x-input-error class="mt-2" :messages="$errors->status->get('status')" />
    </div>
@endif

@if ($ticket->status->acceptsReviews())
    <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
        <h3 class="text-lg font-medium text-gray-900">{{ __('Your feedback') }}</h3>
        @if ($ticket->review)
            @include('tickets.partials.review-summary', ['review' => $ticket->review])
            <details class="mt-4" @if ($errors->review->any()) open @endif>
                <summary class="cursor-pointer text-sm text-indigo-600 hover:text-indigo-900">{{ __('Edit your review') }}</summary>
                @include('tickets.partials.review-form', ['ticket' => $ticket, 'review' => $ticket->review])
            </details>
        @else
            <p class="mt-1 text-sm text-gray-600">{{ __('How satisfied are you with how this ticket was handled? Only the IT admins see your review.') }}</p>
            @include('tickets.partials.review-form', ['ticket' => $ticket, 'review' => null])
        @endif
    </div>
@endif
