<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $ticket->reference }} · {{ $ticket->title }}
            </h2>
            <a href="{{ route('technician.tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to my assignments') }}</a>
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
                    <h3 class="font-medium text-gray-900">{{ __('Add a work note') }}</h3>
                    @if ($ticket->status === App\Enums\TicketStatus::Closed)
                        <p class="mt-2 text-sm text-gray-600">{{ __('This ticket is closed. Work notes can no longer be added.') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->note->get('note')" />
                    @else
                        <form method="POST" action="{{ route('technician.tickets.notes.store', $ticket) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="work_note" :value="__('Note (internal: seen by you and admins, not the requester)')" />
                                <textarea id="work_note" name="note" rows="3" maxlength="2000" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ $errors->note->any() ? old('note') : '' }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->note->get('note')" />
                            </div>
                            <x-primary-button>{{ __('Add note') }}</x-primary-button>
                        </form>
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
                            <dd class="mt-1 text-gray-900">
                                {{ $ticket->requester->name }} · {{ $ticket->requester->email }}
                                @if ($ticket->requester->phone)
                                    · {{ $ticket->requester->phone }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Priority') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $ticket->priority->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Category') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $ticket->category->name }}</dd>
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
                    <h3 class="font-medium text-gray-900">{{ __('Update status') }}</h3>
                    @if (empty($nextStatuses))
                        <p class="mt-2 text-sm text-gray-600">
                            @if ($ticket->status === App\Enums\TicketStatus::Resolved)
                                {{ __('You have resolved this ticket. The requester or an admin closes it once the fix is confirmed.') }}
                            @else
                                {{ __('This ticket is :status. Its status can no longer change.', ['status' => strtolower($ticket->status->label())]) }}
                            @endif
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->status->get('status')" />
                    @else
                        <form method="POST" action="{{ route('technician.tickets.status', $ticket) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <x-input-label for="status" :value="__('New status')" />
                                <select id="status" name="status" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach ($nextStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status === App\Enums\TicketStatus::InProgress ? __('In progress (start work)') : __('Resolved (work done)') }}</option>
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
