@php($unreadNotifications = Auth::user()->unreadNotifications()->count())
<a href="{{ route('notifications.index') }}" class="relative me-3 inline-flex items-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100" title="{{ __('Notifications') }}">
    <span class="sr-only">{{ __('Notifications') }}</span>
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <span data-notification-count class="{{ $unreadNotifications ? '' : 'hidden' }} absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-red-600 px-1.5 text-center text-xs font-semibold leading-5 text-white">{{ $unreadNotifications }}</span>
</a>
