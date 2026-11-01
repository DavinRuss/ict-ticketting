<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $ticket->reference }} · {{ $ticket->title }}
            </h2>
            <a href="{{ route('tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to my tickets') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-medium text-gray-500">{{ __('Description') }}</h3>
                    <p class="mt-2 whitespace-pre-line text-gray-900">{{ $ticket->description }}</p>
                </div>

                @include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])
            </div>

            <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg h-fit">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-1">@include('tickets.partials.status-badge', ['status' => $ticket->status])</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Priority') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $ticket->priority->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Category') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $ticket->category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Technician') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $ticket->technician?->name ?? __('Not assigned yet') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Submitted') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $ticket->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    @foreach (['assigned_at' => __('Assigned'), 'started_at' => __('Work started'), 'resolved_at' => __('Resolved'), 'closed_at' => __('Closed')] as $field => $label)
                        @if ($ticket->{$field})
                            <div>
                                <dt class="text-gray-500">{{ $label }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $ticket->{$field}->format('d M Y, H:i') }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div>
                        <dt class="text-gray-500">{{ __('Last updated') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $ticket->updated_at->format('d M Y, H:i') }}</dd>
                    </div>
                </dl>
                <p class="mt-6 text-xs text-gray-500">{{ __('Status and technician are set by the IT team. You will see each change listed under Updates.') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
