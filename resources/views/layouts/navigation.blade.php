@php
    $itemClasses = fn (string $route) => request()->routeIs($route)
        ? 'bg-white/15 text-white shadow-sm'
        : 'text-white/80 hover:bg-white/10 hover:text-white';
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-30 bg-gray-900/60 lg:hidden" @click="sidebarOpen = false" x-transition.opacity></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full transform flex-col bg-gradient-to-b from-indigo-600 via-violet-600 to-fuchsia-600 text-white transition-transform duration-200 ease-in-out lg:translate-x-0"
    aria-label="{{ __('Main navigation') }}">

    {{-- Brand --}}
    <div class="flex h-20 shrink-0 items-center gap-3 px-5">
        <div class="w-12 shrink-0 rounded-xl bg-white/95 p-1.5 shadow-sm">
            <x-application-logo class="w-full" />
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold leading-tight">{{ __('IT Helpdesk') }}</p>
            <p class="truncate text-xs text-white/70">{{ __('Service desk') }}</p>
        </div>
    </div>

    {{-- Menu --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('dashboard') }}">
            <x-icon name="dashboard" class="h-5 w-5 shrink-0" />
            <span>{{ __('Dashboard') }}</span>
        </a>

        <a href="{{ route('tickets.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('tickets.*') }}">
            <x-icon name="ticket" class="h-5 w-5 shrink-0" />
            <span>{{ Auth::user()->isAdmin() ? __('Tickets') : __('My tickets') }}</span>
        </a>

        @if (Auth::user()->hasRole(App\Enums\UserRole::Technician))
            <a href="{{ route('technician.dashboard') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('technician.*') }}">
                <x-icon name="assignments" class="h-5 w-5 shrink-0" />
                <span>{{ __('My assignments') }}</span>
            </a>
        @endif

        @if (Auth::user()->hasRole(App\Enums\UserRole::Technician, App\Enums\UserRole::Admin))
            <a href="{{ route('chat.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('chat.*') }}">
                <x-icon name="chat" class="h-5 w-5 shrink-0" />
                <span>{{ __('Team chat') }}</span>
            </a>
        @endif

        @can('manage', App\Models\Ticket::class)
            <div class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-white/50">{{ __('Administration') }}</div>
            <a href="{{ route('admin.tickets.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('admin.tickets.*') }}">
                <x-icon name="queue" class="h-5 w-5 shrink-0" />
                <span>{{ __('All tickets') }}</span>
            </a>
            <a href="{{ route('admin.reviews.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('admin.reviews.*') }}">
                <x-icon name="feedback" class="h-5 w-5 shrink-0" />
                <span>{{ __('Feedback') }}</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('admin.reports.*') }}">
                <x-icon name="reports" class="h-5 w-5 shrink-0" />
                <span>{{ __('Reports') }}</span>
            </a>
        @endcan

        @can('viewAny', App\Models\User::class)
            <a href="{{ route('admin.users.index') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemClasses('admin.users.*') }}">
                <x-icon name="accounts" class="h-5 w-5 shrink-0" />
                <span>{{ __('Accounts') }}</span>
            </a>
        @endcan
    </nav>

    {{-- User + theme + logout --}}
    <div class="shrink-0 border-t border-white/20 px-3 py-4">
        <div class="flex items-center gap-3 rounded-lg px-3 py-2">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20">
                <x-icon name="profile" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium leading-tight">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-white/70">{{ Auth::user()->role->label() }}</p>
            </div>
            <x-theme-toggle />
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white">
                <x-icon name="logout" class="h-5 w-5 shrink-0" />
                <span>{{ __('Log out') }}</span>
            </button>
        </form>
    </div>
</aside>
