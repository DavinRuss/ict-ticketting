<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin {email} {--name=Administrator}', function (string $email) {
    $password = (string) $this->secret('Password');
    $confirmation = (string) $this->secret('Confirm password');

    $validator = Validator::make([
        'name' => $this->option('name'),
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $confirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::defaults()],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $admin = new User([
        'name' => $this->option('name'),
        'email' => $email,
        'password' => $password,
    ]);
    $admin->role = UserRole::Admin;
    $admin->email_verified_at = now();
    $admin->save();

    $this->info("Admin account created for {$email}.");

    return 0;
})->purpose('Create an administrator account (prompts for the password)');
