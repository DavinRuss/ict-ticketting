<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My tickets') }}
            </h2>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New ticket') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('tickets.index') }}" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-col gap-4 sm:flex-row sm:items-end">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                </div>
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
            </form>

            @if ($tickets->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    @if (! empty($filters['status']))
                        <p class="text-gray-700">{{ __('You have no tickets with this status.') }}</p>
                        <a href="{{ route('tickets.index') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900">{{ __('Show all tickets') }}</a>
                    @else
                        <p class="text-gray-700">{{ __('You have not submitted any tickets yet.') }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Having an IT problem? Submit a ticket and the IT team will pick it up.') }}</p>
                        <a href="{{ route('tickets.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ __('Submit your first ticket') }}</a>
                    @endif
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Reference') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Title') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Priority') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Technician') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Submitted') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->reference }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900">{{ $ticket->title }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->category->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->priority->label() }}</td>
                                    <td class="px-4 py-3">@include('tickets.partials.status-badge', ['status' => $ticket->status])</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->technician?->name ?? __('Not assigned') }}</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $ticket->created_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $tickets->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
