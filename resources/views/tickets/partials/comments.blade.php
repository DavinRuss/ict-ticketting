<section class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
    <h3 class="text-lg font-medium text-gray-900">{{ __('Conversation') }}</h3>

    @if ($allowCommentForm)
        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <x-input-label for="comment_body" :value="__('Add a comment')" />
                <textarea id="comment_body" name="body" rows="3" maxlength="5000" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $errors->comment->any() ? old('body') : '' }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->comment->get('body')" />
            </div>
            <x-primary-button>{{ __('Post comment') }}</x-primary-button>
        </form>
    @endif

    <ol class="mt-4 space-y-4">
        @forelse ($comments as $comment)
            <li class="border-t border-gray-100 pt-3">
                <p class="text-sm text-gray-700">{{ $comment->user->name }} · {{ $comment->created_at->format('d M Y, H:i') }}</p>
                <p class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $comment->body }}</p>
            </li>
        @empty
            <li class="border-t border-gray-100 pt-3 text-sm text-gray-600">{{ __('No comments yet.') }}</li>
        @endforelse
    </ol>

    {{ $comments->links() }}
</section>