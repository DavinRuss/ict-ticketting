<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Team chat') }}
            </h2>
            <span class="text-sm text-gray-500">{{ __('Technicians & admins') }}</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <p class="text-sm text-gray-600">{{ __('Messages are shared with every technician and admin in real time.') }}</p>
                </div>

                <ul id="chat-messages" class="h-[28rem] overflow-y-auto p-4 space-y-3">
                    @forelse ($messages as $message)
                        <li class="flex items-start gap-3">
                            <div class="flex-1">
                                <p class="text-xs text-gray-500">
                                    <span class="font-medium text-gray-700">{{ $message->user->name }}</span>
                                    <span class="text-gray-400">· {{ $message->created_at->format('H:i') }}</span>
                                </p>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $message->body }}</p>
                            </div>
                        </li>
                    @empty
                        <li id="chat-empty" class="text-center text-sm text-gray-500 py-8">{{ __('No messages yet. Say hello!') }}</li>
                    @endforelse
                </ul>

                <form id="chat-form" method="POST" action="{{ route('chat.messages.store') }}" class="border-t border-gray-200 p-4">
                    @csrf
                    <label for="chat-body" class="sr-only">{{ __('Message') }}</label>
                    <div class="flex items-end gap-3">
                        <textarea id="chat-body" name="body" rows="2" maxlength="2000" required
                                  placeholder="{{ __('Type a message…') }}"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-primary-button>{{ __('Send') }}</x-primary-button>
                    </div>
                    <p id="chat-error" class="mt-2 text-sm text-red-600" hidden></p>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/chat.js'])
</x-app-layout>
