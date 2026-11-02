<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $isAdmin ? __('Service operations') : ($isTechnician ? __('Technician overview') : __('My service dashboard')) }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $isAdmin ? ($selectedTechnician ? __('Service activity for :name.', ['name' => $selectedTechnician->name]) : __('A live view of service demand, delivery, and technician workload.')) : ($isTechnician ? __('Your current assignments and recent work progress.') : __('Your requests and their latest progress.')) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($isAdmin)
                    <a href="{{ route('admin.tickets.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">{{ __('Open ticket queue') }}</a>
                @endif
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New ticket') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php($totalTickets = $statusCounts->sum())
            @php($ticketListRoute = $isAdmin ? 'admin.tickets.index' : ($isTechnician ? 'technician.tickets.index' : 'tickets.index'))

            @if ($isAdmin)
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm rounded-lg">
                    <div class="w-full sm:max-w-xs">
                        <x-input-label for="technician_id" :value="__('Technician activity')" />
                        <select id="technician_id" name="technician_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('All technicians and tickets') }}</option>
                            @foreach ($technicianOptions as $technician)
                                <option value="{{ $technician->id }}" @selected($selectedTechnician?->id === $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('technician_id')" />
                    </div>
                    <x-primary-button>{{ __('Apply') }}</x-primary-button>
                    @if ($selectedTechnician)
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                    @endif
                </form>
            @endif

            <section aria-label="{{ __('Service totals') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="bg-white p-4 sm:p-5 shadow-sm rounded-lg border-l-4 border-gray-400">
                    <p class="text-sm text-gray-500">{{ $isAdmin ? __('Total tickets') : ($isTechnician ? __('Assigned tickets') : __('My tickets')) }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalTickets }}</p>
                </div>
                <div class="bg-white p-4 sm:p-5 shadow-sm rounded-lg border-l-4 border-blue-500">
                    <p class="text-sm text-gray-500">{{ __('Pending') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $pendingCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Open and assigned') }}</p>
                </div>
                <div class="bg-white p-4 sm:p-5 shadow-sm rounded-lg border-l-4 border-amber-500">
                    <p class="text-sm text-gray-500">{{ __('In progress') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $inProgressCount }}</p>
                </div>
                <div class="bg-white p-4 sm:p-5 shadow-sm rounded-lg border-l-4 border-emerald-600">
                    <p class="text-sm text-gray-500">{{ __('Finished') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $finishedCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Resolved and closed') }}</p>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="bg-white p-4 sm:p-6 shadow-sm rounded-lg xl:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ __('Daily service flow') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Opened, started, resolved, and closed over the last 14 days.') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600">
                            @foreach ($activitySeries as $series)
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $series['color'] }}"></span>
                                    {{ $series['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-5 overflow-x-auto" role="region" aria-label="{{ __('Daily service activity chart') }}" tabindex="0">
                        <svg viewBox="0 0 760 250" class="min-w-[620px] w-full" role="img" aria-labelledby="activity-chart-title activity-chart-description">
                            <title id="activity-chart-title">{{ __('Daily service flow') }}</title>
                            <desc id="activity-chart-description">{{ __('A line chart showing ticket openings and work progress over the last 14 days.') }}</desc>
                            @foreach ([0, 0.5, 1] as $fraction)
                                @php($gridY = 208 - (170 * $fraction))
                                <line x1="52" y1="{{ $gridY }}" x2="740" y2="{{ $gridY }}" stroke="#e5e7eb" stroke-width="1" />
                                <text x="42" y="{{ $gridY + 4 }}" text-anchor="end" font-size="11" fill="#6b7280">{{ (int) round($chartMaximum * $fraction) }}</text>
                            @endforeach
                            @foreach ($activitySeries as $series)
                                <polyline points="{{ $series['points'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                @foreach ($series['coordinates'] as $point)
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="{{ $series['color'] }}">
                                        <title>{{ $series['label'] }}: {{ $point['value'] }}</title>
                                    </circle>
                                @endforeach
                            @endforeach
                            @foreach ($dailyActivity as $day)
                                @if ($loop->index % 2 === 0 || $loop->last)
                                    <text x="{{ 52 + (680 / 13 * $loop->index) }}" y="235" text-anchor="middle" font-size="11" fill="#6b7280">{{ $day['label'] }}</text>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                </section>

                <section class="bg-white p-4 sm:p-6 shadow-sm rounded-lg">
                    <h3 class="font-medium text-gray-900">{{ __('Services by category') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Current ticket volume') }}</p>
                    @if ($serviceMix->isEmpty())
                        <p class="mt-6 text-sm text-gray-600">{{ __('No service requests in this view yet.') }}</p>
                    @else
                        <ul class="mt-5 space-y-4">
                            @foreach ($serviceMix as $service)
                                <li>
                                    <div class="flex justify-between gap-3 text-sm">
                                        <span class="truncate text-gray-700">{{ $service->name }}</span>
                                        <span class="font-medium tabular-nums text-gray-900">{{ $service->total }}</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ round(100 * $service->total / $serviceMixMaximum) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            @if ($isAdmin)
                <section class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="flex flex-wrap items-end justify-between gap-3 p-4 sm:p-6">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ __('Technician progress') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Current workload and status changes over the last 14 days.') }}</p>
                        </div>
                        <a href="{{ route('admin.tickets.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">{{ __('View assignments') }}</a>
                    </div>
                    @if ($technicians->isEmpty())
                        <p class="border-t border-gray-100 px-4 py-6 text-sm text-gray-600 sm:px-6">{{ __('No active technicians.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-medium sm:px-6">{{ __('Technician') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Active tickets') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('In progress') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Started · 14d') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Resolved · 14d') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($technicians as $technician)
                                        <tr>
                                            <th scope="row" class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-900 sm:px-6">
                                                <a href="{{ route('dashboard', ['technician_id' => $technician->id]) }}" class="text-indigo-700 hover:text-indigo-900">{{ $technician->name }}</a>
                                            </th>
                                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $technician->active_tickets_count }}</td>
                                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $technician->in_progress_count }}</td>
                                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $technician->started_count }}</td>
                                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $technician->resolved_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="bg-white p-4 sm:p-6 shadow-sm rounded-lg">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ __('Recent service activity') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Latest submissions, assignments, and status changes.') }}</p>
                        </div>
                        <a href="{{ route('admin.tickets.index') }}" class="shrink-0 text-sm font-medium text-indigo-700 hover:text-indigo-900">{{ __('All tickets') }}</a>
                    </div>
                    @if ($recentActivity->isEmpty())
                        <p class="mt-5 text-sm text-gray-600">{{ __('No recorded service activity yet.') }}</p>
                    @else
                        <ol class="mt-4 divide-y divide-gray-100">
                            @foreach ($recentActivity as $activity)
                                <li class="flex flex-wrap items-start justify-between gap-x-6 gap-y-1 py-3 text-sm">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.tickets.show', $activity->ticket) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $activity->ticket->reference }} · {{ $activity->ticket->title }}</a>
                                        <p class="mt-1 text-gray-700">{{ $activity->description() }} <span class="text-gray-500">· {{ $activity->user?->name ?? __('System') }}</span></p>
                                    </div>
                                    <time class="whitespace-nowrap text-xs text-gray-500" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, H:i') }}</time>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @else
                <section class="bg-white p-4 sm:p-6 shadow-sm rounded-lg">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-medium text-gray-900">{{ $isTechnician ? __('Recently assigned tickets') : __('Recent tickets') }}</h3>
                        <a href="{{ route($ticketListRoute) }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">{{ __('View all') }}</a>
                    </div>
                    @if ($recentTickets->isEmpty())
                        <p class="mt-4 text-sm text-gray-600">{{ $isTechnician ? __('No tickets are currently assigned to you.') : __('You have not submitted any tickets yet.') }}</p>
                    @else
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($recentTickets as $ticket)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                    <div class="min-w-0">
                                        <a href="{{ route($isTechnician ? 'technician.tickets.show' : 'tickets.show', $ticket) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $ticket->reference }} · {{ $ticket->title }}</a>
                                        <p class="mt-1 text-xs text-gray-500">{{ $ticket->category->name }} · {{ $ticket->created_at->format('M j, Y') }}</p>
                                    </div>
                                    @include('tickets.partials.status-badge', ['status' => $ticket->status])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section aria-label="{{ __('Ticket status breakdown') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($statuses as $status)
                    <a href="{{ route($ticketListRoute, ['status' => $status->value]) }}" class="bg-white p-4 shadow-sm rounded-lg hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
                        <p class="text-xs font-medium text-gray-500">{{ $status->label() }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ $statusCounts[$status->value] }}</p>
                    </a>
                @endforeach
            </section>
        </div>
    </div>
</x-app-layout>