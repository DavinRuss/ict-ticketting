@php($inputClass = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm')

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user?->name)" required autofocus autocomplete="off" />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user?->email)" required autocomplete="off" />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

<div>
    <x-input-label for="phone" :value="__('Phone (optional)')" />
    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $user?->phone)" autocomplete="off" />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>

@if ($canChangeAccess)
    <div>
        <x-input-label for="role" :value="__('Role')" />
        <select id="role" name="role" class="{{ $inputClass }}" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user?->role->value ?? 'user') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('role')" />
    </div>
@else
    <div>
        <x-input-label :value="__('Role')" />
        <p class="mt-1 text-sm text-gray-700">{{ $user->role->label() }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ __('You cannot change your own role or deactivate your own account. Ask another admin.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('role')" />
        <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
    </div>
@endif
