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
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:from-indigo-700 hover:to-violet-700">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New ticket') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            @php
                $totalTickets = $statusCounts->sum();
                $ticketListRoute = $isAdmin ? 'admin.tickets.index' : ($isTechnician ? 'technician.tickets.index' : 'tickets.index');

                $donutColors = [
                    'open' => '#f59e0b',
                    'assigned' => '#6366f1',
                    'in_progress' => '#8b5cf6',
                    'resolved' => '#10b981',
                    'closed' => '#64748b',
                ];
                $donutTotal = $statusCounts->sum();
                $donutDenominator = max(1, $donutTotal);
                $donutRadius = 42;
                $donutCircumference = 2 * M_PI * $donutRadius;
                $donutSegments = [];
                $donutAccumulated = 0.0;
                foreach ($statuses as $status) {
                    $count = (int) $statusCounts[$status->value];
                    if ($count <= 0) {
                        continue;
                    }
                    $length = ($count / $donutDenominator) * $donutCircumference;
                    $donutSegments[] = [
                        'label' => $status->label(),
                        'value' => $count,
                        'color' => $donutColors[$status->value] ?? '#6366f1',
                        'dasharray' => round($length, 2).' '.round($donutCircumference, 2),
                        'dashoffset' => round(-$donutAccumulated, 2),
                    ];
                    $donutAccumulated += $length;
                }

                $areaSeries = $activitySeries->firstWhere('key', 'opened');
                $areaPath = '';
                if ($areaSeries && $areaSeries['coordinates']->isNotEmpty()) {
                    $coords = $areaSeries['coordinates'];
                    $baseline = 208;
                    $areaPath = 'M '.$coords->first()['x'].' '.$baseline;
                    foreach ($coords as $point) {
                        $areaPath .= ' L '.$point['x'].' '.$point['y'];
                    }
                    $areaPath .= ' L '.$coords->last()['x'].' '.$baseline.' Z';
                }
            @endphp

            @if ($isAdmin)
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="w-full sm:max-w-xs">
                        <x-input-label for="technician_id" :value="__('Technician activity')" />
                        <select id="technician_id" name="technician_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All technicians and tickets') }}</option>
                            @foreach ($technicianOptions as $technician)
                                <option value="{{ $technician->id }}" @selected($selectedTechnician?->id === $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('technician_id')" />
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:from-indigo-700 hover:to-violet-700">{{ __('Apply') }}</button>
                    @if ($selectedTechnician)
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                    @endif
                </form>
            @endif

            {{-- Stat cards --}}
            <section aria-label="{{ __('Service totals') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 p-5 text-white shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-white/80">{{ $isAdmin ? __('Total tickets') : ($isTechnician ? __('Assigned tickets') : __('My tickets')) }}</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                            <x-icon name="queue" class="h-6 w-6" />
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold">{{ $totalTickets }}</p>
                </div>

                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 p-5 text-white shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-white/80">{{ __('Pending') }}</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                            <x-icon name="clock" class="h-6 w-6" />
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold">{{ $pendingCount }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ __('Open and assigned') }}</p>
                </div>

                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-fuchsia-500 to-purple-600 p-5 text-white shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-white/80">{{ __('In progress') }}</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                            <x-icon name="bolt" class="h-6 w-6" />
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold">{{ $inProgressCount }}</p>
                </div>

                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 p-5 text-white shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-white/80">{{ __('Finished') }}</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15">
                            <x-icon name="check" class="h-6 w-6" />
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold">{{ $finishedCount }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ __('Resolved and closed') }}</p>
                </div>
            </section>

            {{-- Charts row --}}
            <div class="grid gap-6 xl:grid-cols-3">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 xl:col-span-2">
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
                        <svg viewBox="0 0 760 250" class="w-full min-w-[620px]" role="img" aria-labelledby="activity-chart-title activity-chart-description">
                            <title id="activity-chart-title">{{ __('Daily service flow') }}</title>
                            <desc id="activity-chart-description">{{ __('A line chart showing ticket openings and work progress over the last 14 days.') }}</desc>
                            <defs>
                                <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.30" />
                                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            @foreach ([0, 0.5, 1] as $fraction)
                                @php($gridY = 208 - (170 * $fraction))
                                <line x1="52" y1="{{ $gridY }}" x2="740" y2="{{ $gridY }}" stroke="#e5e7eb" stroke-width="1" />
                                <text x="42" y="{{ $gridY + 4 }}" text-anchor="end" font-size="11" fill="#6b7280">{{ (int) round($chartMaximum * $fraction) }}</text>
                            @endforeach
                            @if ($areaPath)
                                <path d="{{ $areaPath }}" fill="url(#areaGradient)" />
                            @endif
                            @foreach ($activitySeries as $series)
                                <polyline points="{{ $series['points'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
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

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="font-medium text-gray-900">{{ __('Status breakdown') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Current ticket volume') }}</p>
                    <div class="mt-5 flex items-center justify-center">
                        <svg viewBox="0 0 120 120" class="h-44 w-44" role="img" aria-label="{{ __('Ticket status breakdown') }}">
                            <g transform="rotate(-90 60 60)">
                                <circle cx="60" cy="60" r="{{ $donutRadius }}" fill="none" stroke="#e5e7eb" stroke-width="16" />
                                @foreach ($donutSegments as $segment)
                                    <circle cx="60" cy="60" r="{{ $donutRadius }}" fill="none" stroke="{{ $segment['color'] }}" stroke-width="16" stroke-dasharray="{{ $segment['dasharray'] }}" stroke-dashoffset="{{ $segment['dashoffset'] }}" />
                                @endforeach
                            </g>
                            <text x="60" y="57" text-anchor="middle" font-size="24" font-weight="700" fill="#111827">{{ $donutTotal }}</text>
                            <text x="60" y="72" text-anchor="middle" font-size="11" fill="#6b7280">{{ __('tickets') }}</text>
                        </svg>
                    </div>
                    <ul class="mt-5 space-y-2">
                        @foreach ($statuses as $status)
                            @php($count = (int) $statusCounts[$status->value])
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="inline-flex items-center gap-2 text-gray-700">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $donutColors[$status->value] ?? '#6366f1' }}"></span>
                                    {{ $status->label() }}
                                </span>
                                <span class="font-medium tabular-nums text-gray-900">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            {{-- Category mix --}}
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="font-medium text-gray-900">{{ __('Services by category') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Current ticket volume') }}</p>
                    </div>
                    <a href="{{ route($ticketListRoute) }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">{{ __('View all') }}</a>
                </div>
                @if ($serviceMix->isEmpty())
                    <p class="mt-6 text-sm text-gray-600">{{ __('No service requests in this view yet.') }}</p>
                @else
                    <ul class="mt-5 grid gap-5 lg:grid-cols-2">
                        @foreach ($serviceMix as $service)
                            <li>
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="truncate text-gray-700">{{ $service->name }}</span>
                                    <span class="font-medium tabular-nums text-gray-900">{{ $service->total }}</span>
                                </div>
                                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" style="width: {{ round(100 * $service->total / $serviceMixMaximum) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($isAdmin)
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
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

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
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
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
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
        </div>
    </div>
</x-app-layout>
