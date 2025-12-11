<?php

use App\Http\Controllers\AuthController;
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
        Route::post('add-contact', [ChatController::class, 'addContact']);
        Route::get('messages/{id}', [ChatController::class, 'getMessages']);
        Route::post('send', [ChatController::class, 'sendMessage']);
        Route::post('send-file', [ChatController::class, 'sendFile']);
        Route::delete('delete/{id}', [ChatController::class, 'deleteMessage']);
    });
});
