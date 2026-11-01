<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My assignments') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('technician.tickets.index') }}" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-4 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Needs work (assigned or in progress)') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                </div>
                <div>
                    <x-input-label for="priority" :value="__('Priority')" />
                    <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('All priorities') }}</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('priority')" />
                </div>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    @if (array_filter($filters))
                        <a href="{{ route('technician.tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                    @endif
                </div>
            </form>

            @if ($tickets->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    @if (array_filter($filters))
                        <p class="text-gray-700">{{ __('None of your assigned tickets match these filters.') }}</p>
                        <a href="{{ route('technician.tickets.index') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900">{{ __('Show all my assignments') }}</a>
                    @else
                        <p class="text-gray-700">{{ __('No tickets are assigned to you yet.') }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ __('An admin assigns tickets to technicians. They will appear here.') }}</p>
                    @endif
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Reference') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Title') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Requester') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Priority') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Assigned') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('technician.tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->reference }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900">{{ $ticket->title }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->requester->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->category->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->priority->label() }}</td>
                                    <td class="px-4 py-3">@include('tickets.partials.status-badge', ['status' => $ticket->status])</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $ticket->assigned_at?->format('d M Y, H:i') }}</td>
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
