<button type="button" data-theme-toggle aria-label="{{ __('Switch to dark theme') }}" title="{{ __('Switch to dark theme') }}" aria-pressed="false" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
    <svg data-theme-icon="moon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z" />
    </svg>
    <svg data-theme-icon="sun" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" hidden>
        <circle cx="12" cy="12" r="4" />
        <path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
    </svg>
    <span class="sr-only" data-theme-toggle-label>{{ __('Switch to dark theme') }}</span>
</button>