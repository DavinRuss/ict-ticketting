<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register all of the event broadcasting channels that your application
| supports. The given channel authorization callbacks are used to check
| if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('chat', function (User $user) {
    return $user->is_active && $user->hasRole(UserRole::Technician, UserRole::Admin);
});

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->is_active && (string) $user->getKey() === $id;
});
