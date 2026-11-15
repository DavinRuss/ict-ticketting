<div id="comments" class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
    <h3 class="text-sm font-medium text-gray-500">{{ __('Comments') }}</h3>
    @if ($ticket->comments->isEmpty())
        <p class="mt-3 text-sm text-gray-600">{{ __('No comments yet.') }}</p>
    @else
        <ol class="mt-3 space-y-4">
            @foreach ($ticket->comments as $comment)
                <li class="text-sm">
                    <p class="text-gray-500">
                        <span class="font-medium text-gray-900">{{ $comment->user->name }}</span>
                        @if ($comment->user_id === $ticket->user_id)
                            · {{ __('Requester') }}
                        @else
                            · {{ $comment->user->role->label() }}
                        @endif
                        · {{ $comment->created_at->format('d M Y, H:i') }}
                    </p>
                    <p class="mt-1 whitespace-pre-line text-gray-700">{{ $comment->body }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($ticket->status === App\Enums\TicketStatus::Closed)
        <p class="mt-4 text-sm text-gray-600">{{ __('This ticket is closed. Comments can no longer be added.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->comment->get('body')" />
    @else
        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <x-input-label for="comment_body" :value="__('Add a comment (seen by the requester, the assigned technician and admins)')" />
                <textarea id="comment_body" name="body" rows="3" maxlength="2000" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $errors->comment->any() ? old('body') : '' }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->comment->get('body')" />
            </div>
            <x-primary-button>{{ __('Post comment') }}</x-primary-button>
        </form>
    @endif
</div>
