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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <div id="desktop-notifications" data-feed-url="{{ route('notifications.feed') }}" class="hidden max-w-7xl mx-auto pt-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-800 flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('Get a desktop notification when someone comments on your tickets.') }}</span>
                    <span class="flex gap-4">
                        <button type="button" data-enable-notifications class="font-medium underline hover:text-indigo-950">{{ __('Enable desktop notifications') }}</button>
                        <button type="button" data-dismiss-notifications class="text-indigo-600 hover:text-indigo-900">{{ __('Not now') }}</button>
                    </span>
                </div>
            </div>

            @if (session('success'))
                <div class="max-w-7xl mx-auto pt-6 px-4 sm:px-6 lg:px-8">
                    <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
