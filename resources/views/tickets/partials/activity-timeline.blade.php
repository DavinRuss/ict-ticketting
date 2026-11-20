<div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
    <h3 class="text-sm font-medium text-gray-500">{{ __('Updates') }}</h3>
    <ol class="mt-3 space-y-3">
        @foreach ($activities as $activity)
            <li class="text-sm">
                <p class="text-gray-900">
                    {{ $activity->description() }}
                    @if ($activity->isInternal())
                        <span class="ms-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Internal') }}</span>
                    @endif
                    @if ($activity->to_status && $activity->action !== App\Models\TicketActivity::ACTION_STATUS_CHANGED)
                        · @include('tickets.partials.status-badge', ['status' => $activity->to_status])
                    @endif
                </p>
                <p class="text-gray-500">{{ $activity->user?->name ?? __('System') }} · {{ $activity->created_at->format('d M Y, H:i') }}</p>
                @if ($activity->note)
                    <p class="mt-1 whitespace-pre-line text-gray-700">{{ $activity->note }}</p>
                @endif
            </li>
        @endforeach
    </ol>

    {{ $activities->links() }}
</div>
