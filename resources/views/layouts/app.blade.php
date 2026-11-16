<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @include('partials.theme-init')

        <style>[x-cloak] { display: none !important; }</style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="{ sidebarOpen: false }" data-user-id="{{ auth()->id() }}" class="font-sans antialiased text-gray-900">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <div class="lg:pl-72">
                <!-- Page Heading -->
                @isset($header)
                    <header class="border-b border-gray-200 bg-white">
                        <div class="flex items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
                            <button type="button" @click="sidebarOpen = true" class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden" aria-label="{{ __('Open menu') }}">
                                <x-icon name="menu" class="h-6 w-6" />
                            </button>
                            <div class="min-w-0 flex-1">{{ $header }}</div>
                            @include('partials.notifications')
                        </div>
                    </header>
                @endisset

                @if (session('success'))
                    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                        <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                        <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                            {{ session('error') }}
                        </div>
                    </div>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
