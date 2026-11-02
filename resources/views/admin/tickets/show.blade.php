<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $ticket->reference }} · {{ $ticket->title }}
            </h2>
            <a href="{{ route('admin.tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to all tickets') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-medium text-gray-500">{{ __('Description') }}</h3>
                    <p class="mt-2 whitespace-pre-line text-gray-900">{{ $ticket->description }}</p>
                </div>

                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Requester feedback') }}</h3>
                    @if ($ticket->review)
                        @include('tickets.partials.review-summary', ['review' => $ticket->review])
                    @else
                        <p class="mt-2 text-sm text-gray-600">{{ $ticket->status->acceptsReviews() ? __('The requester has not reviewed this ticket yet.') : __('The requester can review this ticket once it is resolved.') }}</p>
                    @endif
                </div>

                @include('tickets.partials.comments', ['ticket' => $ticket, 'allowCommentForm' => false])

                @include('tickets.partials.activity-timeline', ['activities' => $activities])
            </div>

            <div class="space-y-6">
                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('Status') }}</dt>
                            <dd class="mt-1">@include('tickets.partials.status-badge', ['status' => $ticket->status])</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Requester') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $ticket->requester->name }} · {{ $ticket->requester->email }}</dd>
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
                            <dd class="mt-1 text-gray-900">
                                {{ $ticket->technician?->name ?? __('Not assigned yet') }}
                                @if ($ticket->technician && ! $ticket->technician->is_active)
                                    <span class="text-xs text-red-700">({{ __('deactivated') }})</span>
                                @endif
                            </dd>
                        </div>
                        @if ($ticket->assigner)
                            <div>
                                <dt class="text-gray-500">{{ __('Assigned by') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $ticket->assigner->name }}</dd>
                            </div>
                        @endif
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
                    </dl>
                </div>

                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-medium text-gray-900">{{ $ticket->technician ? __('Reassign technician') : __('Assign technician') }}</h3>
                    @if (! $canAssign)
                        <p class="mt-2 text-sm text-gray-600">{{ __('Only open or assigned tickets can be assigned. This ticket is :status.', ['status' => strtolower($ticket->status->label())]) }}</p>
                        <x-input-error class="mt-2" :messages="$errors->assignment->get('technician_id')" />
                    @elseif ($technicians->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">{{ __('There are no active technicians.') }} <a href="{{ route('admin.users.create') }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Add a technician account') }}</a></p>
                    @else
                        <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <x-input-label for="technician_id" :value="__('Technician')" />
                                <select id="technician_id" name="technician_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">{{ __('Choose a technician') }}</option>
                                    @foreach ($technicians as $technician)
                                        <option value="{{ $technician->id }}" @selected((string) old('technician_id') === (string) $technician->id) @disabled($technician->id === $ticket->assigned_to)>
                                            {{ $technician->name }}@if ($technician->id === $ticket->assigned_to) ({{ __('current') }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->assignment->get('technician_id')" />
                            </div>
                            <div>
                                <x-input-label for="assignment_note" :value="__('Note (optional, shown to the requester)')" />
                                <textarea id="assignment_note" name="note" rows="2" maxlength="1000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $errors->assignment->any() ? old('note') : '' }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->assignment->get('note')" />
                            </div>
                            <x-primary-button>{{ $ticket->technician ? __('Reassign') : __('Assign') }}</x-primary-button>
                        </form>
                    @endif
                </div>

                <div class="bg-white p-4 sm:p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-medium text-gray-900">{{ __('Change status') }}</h3>
                    @if (empty($nextStatuses))
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $ticket->status === App\Enums\TicketStatus::Open ? __('Assign a technician first.') : __('This ticket is closed. Its status can no longer change.') }}
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->status->get('status')" />
                    @else
                        <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <x-input-label for="status" :value="__('New status')" />
                                <select id="status" name="status" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach ($nextStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->status->get('status')" />
                            </div>
                            <div>
                                <x-input-label for="status_note" :value="__('Note (optional, shown to the requester)')" />
                                <textarea id="status_note" name="note" rows="2" maxlength="1000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $errors->status->any() ? old('note') : '' }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->status->get('note')" />
                            </div>
                            <x-primary-button>{{ __('Update status') }}</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
