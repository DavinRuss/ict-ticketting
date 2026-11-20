<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Activity reports') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('Filter the audit log, export a selection, or download all activity.') }}</p>
            </div>
            <a href="{{ route('admin.reports.export') }}" class="inline-flex min-h-10 items-center rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ __('Export all activities') }}
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-4 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <x-input-label for="from" :value="__('From date')" />
                    <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error class="mt-1" :messages="$errors->get('from')" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('To date')" />
                    <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error class="mt-1" :messages="$errors->get('to')" />
                </div>
                <div>
                    <x-input-label for="ticket_reference" :value="__('Ticket reference')" />
                    <input id="ticket_reference" name="ticket_reference" type="search" value="{{ $filters['ticket_reference'] ?? '' }}" placeholder="TKT-000123" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error class="mt-1" :messages="$errors->get('ticket_reference')" />
                </div>
                <div>
                    <x-input-label for="action" :value="__('Activity type')" />
                    <select id="action" name="action" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All activity types') }}</option>
                        @foreach ($actions as $action => $label)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('action')" />
                </div>
                <div>
                    <x-input-label for="technician_id" :value="__('Technician')" />
                    <select id="technician_id" name="technician_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All technicians') }}</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected((string) ($filters['technician_id'] ?? '') === (string) $technician->id)>{{ $technician->name }}@unless ($technician->is_active) ({{ __('inactive') }})@endunless</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('technician_id')" />
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-5">
                    <x-primary-button>{{ __('Apply filters') }}</x-primary-button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.reports.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Clear filters') }}</a>
                        <a href="{{ route('admin.reports.export', $filters) }}" class="inline-flex min-h-10 items-center rounded-md border border-indigo-200 px-4 text-sm font-medium text-indigo-700 hover:bg-indigo-50">{{ __('Export filtered CSV') }}</a>
                    @endif
                    <span class="text-sm text-gray-500">{{ trans_choice(':count activity|:count activities', $total) }}</span>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm" aria-labelledby="activity-list-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6">
                    <h3 id="activity-list-heading" class="font-medium text-gray-900">{{ __('Activity log') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('Internal work notes are included in admin exports.') }}</p>
                </div>

                @if ($activities->isEmpty())
                    <p class="px-4 py-8 text-center text-sm text-gray-600 sm:px-6">{{ array_filter($filters) ? __('No activity matches these filters.') : __('No ticket activity has been recorded yet.') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.reports.export-selected') }}">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-medium">
                                            <span class="sr-only">{{ __('Select') }}</span>
                                        </th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ __('Date') }}</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ __('Ticket') }}</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ __('Activity') }}</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ __('Actor') }}</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ __('Technician change') }}</th>
                                        <th scope="col" class="min-w-64 px-4 py-3 font-medium">{{ __('Note') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($activities as $activity)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <input type="checkbox" name="activity_ids[]" value="{{ $activity->id }}" aria-label="{{ __('Select activity :id', ['id' => $activity->id]) }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('admin.tickets.show', $activity->ticket) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $activity->ticket->reference }}</a>
                                                <p class="mt-1 max-w-xs truncate text-xs text-gray-500">{{ $activity->ticket->title }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-800">{{ $activity->description() }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $activity->user?->name ?? __('System') }}</td>
                                            <td class="px-4 py-3 text-gray-700">
                                                @if ($activity->fromTechnician || $activity->toTechnician)
                                                    {{ $activity->fromTechnician?->name ?? __('Unassigned') }} → {{ $activity->toTechnician?->name ?? __('Unassigned') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-700 whitespace-pre-line">{{ $activity->note ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-4 sm:px-6">
                            <p class="text-xs text-gray-500">{{ __('Select up to 25 activities on this page to export.') }}</p>
                            <x-primary-button>{{ __('Export selected activities') }}</x-primary-button>
                        </div>
                    </form>

                    <div class="border-t border-gray-100 px-4 py-4 sm:px-6">
                        {{ $activities->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>