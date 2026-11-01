<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('All tickets') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
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
                <div>
                    <x-input-label for="category" :value="__('Category')" />
                    <select id="category" name="category" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('category')" />
                </div>
                <div>
                    <x-input-label for="technician" :value="__('Technician')" />
                    <select id="technician" name="technician" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('Any technician') }}</option>
                        <option value="unassigned" @selected(($filters['technician'] ?? '') === 'unassigned')>{{ __('Not assigned') }}</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected((string) ($filters['technician'] ?? '') === (string) $technician->id)>{{ $technician->name }}@unless ($technician->is_active) ({{ __('deactivated') }})@endunless</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('technician')" />
                </div>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                    @endif
                </div>
            </form>

            @if ($tickets->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    @if (array_filter($filters))
                        <p class="text-gray-700">{{ __('No tickets match these filters.') }}</p>
                        <a href="{{ route('admin.tickets.index') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900">{{ __('Show all tickets') }}</a>
                    @else
                        <p class="text-gray-700">{{ __('No tickets have been submitted yet.') }}</p>
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
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Technician') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Submitted') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->reference }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900">{{ $ticket->title }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ticket->requester->name }}</td>
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
