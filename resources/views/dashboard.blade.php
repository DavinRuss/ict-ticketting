<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New ticket') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-gray-900">
                {{ __('You are signed in as :name (:role).', ['name' => Auth::user()->name, 'role' => Auth::user()->role->label()]) }}
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                @foreach ($statuses as $status)
                    <a href="{{ route('tickets.index', ['status' => $status->value]) }}" class="bg-white p-4 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="text-sm text-gray-500">{{ $status->label() }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $statusCounts[$status->value] }}</p>
                    </a>
                @endforeach
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">{{ __('Recent tickets') }}</h3>
                    <a href="{{ route('tickets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('View all') }}</a>
                </div>
                @if ($recentTickets->isEmpty())
                    <p class="mt-4 text-gray-700">{{ __('You have not submitted any tickets yet.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($recentTickets as $ticket)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-900">{{ $ticket->reference }} · {{ $ticket->title }}</a>
                                @include('tickets.partials.status-badge', ['status' => $ticket->status])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
