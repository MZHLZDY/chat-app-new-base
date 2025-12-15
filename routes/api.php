<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgoraCallController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
//         return \Illuminate\Support\Facades\Broadcast::auth($request);
//     });
// });

// Authentication Route
Route::middleware(['auth', 'json'])->prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->withoutMiddleware('auth');
    Route::post('register', [AuthController::class, 'register'])->withoutMiddleware('auth');
    Route::delete('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

// 2. Public Setting
Route::prefix('setting')->group(function () {
    Route::get('', [SettingController::class, 'index']);
});

// 3. Protected Routes (Harus Login & Verified)
Route::middleware(['auth', 'json'])->group(function () {

    // Setting Update
    Route::prefix('setting')->group(function () {
        Route::post('', [SettingController::class, 'update']);
    });
    // Master Data Management
    Route::prefix('master')->group(function () {
        
        // Users (Kontak Chat)
        Route::get('users', [UserController::class, 'get']);
        Route::post('users', [UserController::class, 'index']);
        Route::post('users/store', [UserController::class, 'store']);
        Route::apiResource('users', UserController::class)
            ->except(['index', 'store'])->scoped(['user' => 'uuid']);

        // Roles
        Route::get('roles', [RoleController::class, 'get']);
        Route::post('roles', [RoleController::class, 'index']);
        Route::post('roles/store', [RoleController::class, 'store']);
        Route::apiResource('roles', RoleController::class)
            ->except(['index', 'store']);
    });

    Route::prefix('chat')->group(function () {
        Route::get('contacts', [ChatController::class, 'getContacts']);
        Route::post('heartbeat', [ChatController::class, 'heartbeat']);
        Route::post('add-contact', [ChatController::class, 'addContact']);
        Route::get('contacts/{id}', [ChatController::class, 'showContact']);
        Route::put('contacts/{id}', [ChatController::class, 'updateContact']); 
        Route::get('messages/{id}', [ChatController::class, 'getMessages']);
        Route::post('send', [ChatController::class, 'sendMessage']);
        Route::post('send-file', [ChatController::class, 'sendMessage']);
        Route::get('download/{id}', [ChatController::class, 'downloadFile']);
        Route::delete('delete/{id}', [ChatController::class, 'deleteMessage']);
    });

    Route::prefix('call')->group(function () {
        Route::post('/invite', [AgoraCallController::class, 'inviteCall']);
        Route::post('/answer', [AgoraCallController::class, 'answerCall']);
        Route::post('/end', [AgoraCallController::class, 'endCall']);
        Route::post('/token', [AgoraCallController::class, 'generateToken']);
        Route::post('/notification/mark-read', [AgoraCallController::class, 'markNotificationAsRead']);
        Route::get('/notification/active', [AgoraCallController::class, 'getActiveCallNotifications']);
    });

    Route::prefix('group-call')->as('group-call.')->group(function () {
      Route::post('/invite', [AgoraCallController::class, 'inviteGroupCall'])->name('invite');
      Route::post('/answer', [AgoraCallController::class, 'answerGroupCall'])->name('answer');
      Route::post('/end', [AgoraCallController::class, 'endGroupCall'])->name('end');
      Route::post('/cancel', [AgoraCallController::class, 'cancelGroupCall'])->name('cancel'); // <-- PASTIKAN BARIS INI ADA
      Route::post('/leave', [AgoraCallController::class, 'leaveGroupCall'])->name('leave');
      Route::post('/recall', [AgoraCallController::class, 'recallParticipant'])->name('recall');
      Route::post('/missed', [AgoraCallController::class, 'missedGroupCall'])->name('missed');
      Route::post('/token', [AgoraCallController::class, 'generateGroupToken'])->name('token');
    });
});
