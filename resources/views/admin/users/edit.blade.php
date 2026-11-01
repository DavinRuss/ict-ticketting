<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit account: :name', ['name' => $user->name]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-xl space-y-6">
                    @csrf
                    @method('PATCH')

                    @include('admin.users.partials.form', ['canChangeAccess' => Gate::allows('changeAccess', $user)])

                    @can('changeAccess', $user)
                        <div>
                            <label for="is_active" class="inline-flex items-center">
                                <input type="hidden" name="is_active" value="0">
                                <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $user->is_active))>
                                <span class="ms-2 text-sm text-gray-700">{{ __('Account is active') }}</span>
                            </label>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Deactivated accounts are signed out and cannot log in. Their history is kept.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
                        </div>
                    @endcan

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
