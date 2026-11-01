<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New ticket') }}
        </h2>
    </x-slot>

    @php($inputClass = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm')

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-4 sm:p-8 shadow-sm sm:rounded-lg">
                @if ($categories->isEmpty())
                    <p class="text-gray-700">{{ __('Tickets cannot be submitted yet because no categories are set up. Please contact the IT team.') }}</p>
                @else
                    <form method="POST" action="{{ route('tickets.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus maxlength="255" />
                            <p class="mt-1 text-sm text-gray-500">{{ __('A short summary, e.g. "Laptop will not power on".') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('title')" />
                        </div>

                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="6" class="{{ $inputClass }}" required maxlength="5000">{{ old('description') }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">{{ __('What happened, when it started, and any error messages you saw.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('description')" />
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="category_id" :value="__('Category')" />
                                <select id="category_id" name="category_id" class="{{ $inputClass }}" required>
                                    <option value="">{{ __('Choose a category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
                            </div>

                            <div>
                                <x-input-label for="priority" :value="__('Priority')" />
                                <select id="priority" name="priority" class="{{ $inputClass }}" required>
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('priority')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Submit ticket') }}</x-primary-button>
                            <a href="{{ route('tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
