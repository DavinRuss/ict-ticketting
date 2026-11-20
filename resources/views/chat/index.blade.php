<x-app-layout>
    @php
        $currentUserId = auth()->id();
        $initials = fn (string $name) => collect(explode(' ', trim($name)))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Team chat') }}
            </h2>
            <span class="text-sm text-gray-500">{{ __('Technicians & admins') }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-gray-900/5">
                {{-- Gradient header --}}
                <div class="bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                                <x-icon name="chat" class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Team chat') }}</p>
                                <p class="text-xs text-white/70">{{ __('Messages are shared with every technician and admin in real time.') }}</p>
                            </div>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                            {{ __('Live') }}
                        </span>
                    </div>

                    {{-- Team presence --}}
                    <div class="mt-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-white/60">{{ __('Team status') }}</p>
                        <ul id="staff-list" class="mt-2 flex flex-wrap gap-2">
                            @foreach ($staff as $member)
                                <li class="flex items-center gap-1.5 rounded-full bg-white/10 py-1 pl-1.5 pr-3 text-xs text-white" data-user-id="{{ $member->id }}">
                                    <span data-status-dot class="h-2 w-2 rounded-full bg-red-500 ring-2 ring-white/20" title="{{ __('Offline') }}"></span>
                                    <span class="font-medium">{{ $member->name }}</span>
                                    <span class="text-white/50">{{ $member->role->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Messages --}}
                <ul id="chat-messages" class="h-[30rem] space-y-4 overflow-y-auto bg-gradient-to-b from-gray-50 to-gray-100/60 px-4 py-5 sm:px-6">
                    @forelse ($messages as $message)
                        @php
                            $isOwn = $message->user_id === $currentUserId;
                            $liked = $message->reactions->contains('user_id', $currentUserId);
                            $likes = $message->reactions->count();
                        @endphp
                        <li class="flex items-end gap-2.5 {{ $isOwn ? 'flex-row-reverse' : '' }}" data-message-id="{{ $message->id }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white {{ $isOwn ? 'bg-gradient-to-br from-indigo-500 to-fuchsia-500' : 'bg-gradient-to-br from-slate-500 to-slate-700' }}">
                                {{ $initials($message->user->name) }}
                            </div>
                            <div class="max-w-[75%] {{ $isOwn ? 'items-end' : 'items-start' }} flex flex-col">
                                <div class="mb-1 flex items-baseline gap-2 px-1 {{ $isOwn ? 'flex-row-reverse' : '' }}">
                                    <span class="text-xs font-semibold text-gray-700">{{ $isOwn ? __('You') : $message->user->name }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $message->created_at->format('H:i') }}</span>
                                    @unless ($isOwn)
                                        <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ $message->user->role->label() }}</span>
                                    @endunless
                                </div>
                                <div class="rounded-2xl px-4 py-2.5 text-sm shadow-sm {{ $isOwn ? 'rounded-br-md bg-gradient-to-br from-indigo-500 to-violet-600 text-white' : 'rounded-bl-md border border-gray-200 bg-white text-gray-800' }}">
                                    <p class="whitespace-pre-line">{{ $message->body }}</p>
                                </div>
                                <button type="button"
                                        class="like-button mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs transition {{ $liked ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 hover:bg-gray-200/70 hover:text-gray-600' }}"
                                        data-message-id="{{ $message->id }}"
                                        aria-label="{{ __('Like message') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 {{ $liked ? 'fill-current' : '' }}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 012.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 00.322-1.672V3a.75.75 0 01.75-.75 2.25 2.25 0 012.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 01-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 00-1.423-.23H6.633z" />
                                    </svg>
                                    <span class="like-count font-medium">{{ $likes }}</span>
                                </button>
                            </div>
                        </li>
                    @empty
                        <li id="chat-empty" class="py-16 text-center">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100">
                                <x-icon name="chat" class="h-7 w-7 text-indigo-500" />
                            </div>
                            <p class="text-sm font-medium text-gray-600">{{ __('No messages yet.') }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Say hello to the team!') }}</p>
                        </li>
                    @endforelse
                </ul>

                {{-- Composer --}}
                <form id="chat-form" method="POST" action="{{ route('chat.messages.store') }}" class="border-t border-gray-200 bg-white p-4">
                    @csrf
                    <label for="chat-body" class="sr-only">{{ __('Message') }}</label>
                    <div class="flex items-end gap-3">
                        <textarea id="chat-body" name="body" rows="2" maxlength="2000" required
                                  placeholder="{{ __('Type a message…') }}"
                                  class="block w-full resize-none rounded-xl border-gray-300 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <button type="submit"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-md transition hover:from-indigo-500 hover:to-violet-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M3.478 2.404a.75.75 0 00-.926.941l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.404z" />
                            </svg>
                            <span class="sr-only">{{ __('Send') }}</span>
                        </button>
                    </div>
                    <p id="chat-error" class="mt-2 text-sm text-red-600" hidden></p>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/chat.js'])
</x-app-layout>
