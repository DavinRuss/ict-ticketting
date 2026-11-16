@php
    $initialUnread = auth()->user()->unreadNotifications()->count();
@endphp

<div
    x-data="notificationCenter({{ $initialUnread }})"
    x-init="init()"
    @app:notification.window="onPush($event.detail)"
    @click.outside="open = false"
    class="relative">

    <button type="button"
            @click="toggle()"
            class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :aria-label="unread > 0 ? '{{ __('Notifications') }} — ' + unread + ' {{ __('unread') }}' : '{{ __('Notifications') }}'">
        <x-icon name="bell" class="h-6 w-6" />
        <span x-show="unread > 0" x-cloak
              x-text="unread > 99 ? '99+' : unread"
              class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-semibold leading-none text-white ring-2 ring-white"></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg sm:w-96"
         role="dialog" aria-label="{{ __('Notifications') }}">

        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <p class="text-sm font-semibold text-gray-800">{{ __('Notifications') }}</p>
            <button type="button" x-show="unread > 0" @click="markAllRead()"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                {{ __('Mark all as read') }}
            </button>
        </div>

        <ul class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
            <template x-for="n in items" :key="n.id">
                <li>
                    <button type="button" @click="openNotification(n)"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="n.read_at ? 'opacity-60' : ''">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read_at ? 'bg-gray-200' : 'bg-indigo-500'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-800" x-text="n.title"></span>
                            <span class="mt-0.5 block text-xs text-gray-500 line-clamp-2" x-text="n.message"></span>
                            <span class="mt-1 block text-[11px] text-gray-400" x-text="formatTime(n.created_at)"></span>
                        </span>
                    </button>
                </li>
            </template>

            <li x-show="!loading && items.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                {{ __('No notifications yet.') }}
            </li>
            <li x-show="loading" class="px-4 py-8 text-center text-sm text-gray-500">
                {{ __('Loading…') }}
            </li>
        </ul>
    </div>
</div>
