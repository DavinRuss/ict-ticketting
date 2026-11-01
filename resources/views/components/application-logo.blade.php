<picture {{ $attributes->merge(['class' => 'block']) }}>
    <img
        src="{{ asset('images/rirs_logo_enhanced.png') }}"
        alt="{{ __('Rivers State Internal Revenue Service') }}"
        class="block h-auto w-full object-contain dark:hidden"
    >
    <img
        src="{{ asset('images/rirs_logo_transparent.png') }}"
        alt="{{ __('Rivers State Internal Revenue Service') }}"
        class="hidden h-auto w-full object-contain dark:block"
    >
</picture>