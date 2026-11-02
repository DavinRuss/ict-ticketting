<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'IT Helpdesk') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <a href="/" aria-label="{{ config('app.name', 'IT Helpdesk') }}" class="block w-36 max-w-[42vw]">
                    <x-application-logo />
                </a>
                <nav class="flex items-center gap-3">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center rounded-md bg-gray-800 px-4 text-sm font-medium text-white hover:bg-gray-700">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-10 items-center rounded-md bg-gray-800 px-4 text-sm font-medium text-white hover:bg-gray-700">{{ __('Log in') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-7xl items-center px-4 py-16 sm:px-6 lg:px-8">
            <section class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Rivers State Internal Revenue Service') }}</p>
                <h1 class="mt-3 text-3xl font-semibold text-gray-900 sm:text-4xl">{{ __('IT Helpdesk') }}</h1>
                <p class="mt-4 max-w-xl text-lg leading-7 text-gray-600">{{ __('Submit a service request, follow its progress, and stay in touch with your support team.') }}</p>
                @guest
                    <a href="{{ route('login') }}" class="mt-7 inline-flex min-h-11 items-center rounded-md bg-gray-800 px-5 text-sm font-semibold text-white hover:bg-gray-700">{{ __('Log in to continue') }}</a>
                @endguest
            </section>
        </main>
    </body>
</html>