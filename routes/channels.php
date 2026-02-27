<?php

use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// -- Channel User Lama --
// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

// -- Channel User Baru --
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{id1}.{id2}', function ($user, $id1, $id2) {
    if ((int) $user->id === (int) $id1 || (int) $user->id === (int) $id2) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

Broadcast::channel('group.call.{callId}', function ($user, $callId) {
    // Anda bisa menambahkan validasi apakah user ini adalah member dari grup tersebut
    // Untuk saat ini kita kembalikan data user agar bisa dikenali di frontend
    return ['id' => $user->id, 'name' => $user->name];
});
