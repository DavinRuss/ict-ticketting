<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Technician workspace') }}
            </h2>
            <a href="{{ route('technician.tickets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('All my assignments') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($statuses as $status)
                    <a href="{{ route('technician.tickets.index', ['status' => $status->value]) }}" class="bg-white p-4 shadow-sm sm:rounded-lg hover:bg-gray-50">
                        <p class="text-sm text-gray-500">{{ $status->label() }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $statusCounts[$status->value] }}</p>
                    </a>
                @endforeach
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">{{ __('Needs your work') }}</h3>
                    <a href="{{ route('technician.tickets.index', ['status' => 'active']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('View all') }}</a>
                </div>
                @if ($activeTickets->isEmpty())
                    <p class="mt-4 text-gray-700">{{ __('Nothing to work on right now. Tickets an admin assigns to you will appear here.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($activeTickets as $ticket)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                <a href="{{ route('technician.tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-900">{{ $ticket->reference }} · {{ $ticket->title }}</a>
                                <span class="flex items-center gap-2 text-gray-500">
                                    {{ $ticket->priority->label() }}
                                    @include('tickets.partials.status-badge', ['status' => $ticket->status])
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
