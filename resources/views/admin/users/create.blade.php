<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add account') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('admin.users.store') }}" class="max-w-xl space-y-6">
                    @csrf

                    @include('admin.users.partials.form', ['user' => null, 'canChangeAccess' => true])

                    <div>
                        <x-input-label for="password" :value="__('Initial password')" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        <p class="mt-1 text-sm text-gray-500">{{ __('Share it with the person securely and ask them to change it on their Profile page.') }}</p>
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm initial password')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Create account') }}</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
