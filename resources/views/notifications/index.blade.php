<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Notifications') }}</h2>
            @if (Auth::user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Mark all as read') }}</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <p class="text-sm text-gray-600" data-notification-permission-hint>{{ __('Desktop notifications appear while this app is open in a browser tab. Allow them when your browser asks.') }}</p>

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($notifications->isEmpty())
                    <p class="p-6 text-sm text-gray-600">{{ __('You have no notifications.') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($notifications as $notification)
                            <li>
                                <a href="{{ route('notifications.open', $notification->id) }}" class="block p-4 hover:bg-gray-50">
                                    <p class="text-sm {{ $notification->read_at ? 'text-gray-700' : 'font-semibold text-gray-900' }}">
                                        {{ $notification->data['title'] ?? '' }}
                                        @unless ($notification->read_at)
                                            <span class="ms-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ __('New') }}</span>
                                        @endunless
                                    </p>
                                    @if (in_array($notification->data['ticket_id'] ?? null, $accessibleTicketIds, true))
                                        <p class="mt-1 text-sm text-gray-600">{{ $notification->data['body'] ?? '' }}</p>
                                    @else
                                        <p class="mt-1 text-sm italic text-gray-500">{{ __('You no longer have access to this ticket.') }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-gray-500">{{ $notification->created_at->format('d M Y, H:i') }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
