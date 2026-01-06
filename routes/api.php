<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgoraController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// Authentication Route
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    
    // Email Verification Routes (Redirect to Frontend)
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    
    Route::post('email/resend', [AuthController::class, 'resendVerification'])
        ->name('verification.resend');
    
    // Protected Auth Routes
    Route::middleware('auth:api')->group(function () {
        Route::delete('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// 2. Public Setting
Route::prefix('setting')->group(function () {
    Route::get('', [SettingController::class, 'index']);
});

// 3. Protected Routes (Harus Login & Verified)
Route::middleware(['auth', 'json'])->group(function () {

    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/stats/detailed', [DashboardController::class, 'getDetailedStats']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/email', [ProfileController::class, 'updateEmail']);
        Route::post('/profile/password', [ProfileController::class, 'updatePassword']);
    });
    
    // Setting Update
    Route::prefix('setting')->group(function () {
        Route::post('', [SettingController::class, 'update']);
    });
    // Master Data Management
    Route::get('/master/users', [ChatController::class, 'getContacts']);

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
        Route::put('message/{id}/read', [ChatController::class, 'markAsRead']);
        Route::post('clear/{id}', [ChatController::class, 'clearChat']);

        // Group Chat Routes
        Route::get('groups', [GroupController::class, 'index']);
        Route::post('groups', [GroupController::class, 'store']); 
        Route::get('groups/{id}', [GroupController::class, 'show']);
        Route::put('groups/{id}', [GroupController::class, 'update']); 
        Route::post('group/{id}/leave', [GroupController::class, 'leaveGroup']); 
        Route::get('group-messages/{groupId}', [GroupController::class, 'getMessages']); 
        Route::post('group/send', [GroupController::class, 'sendMessage']); 
        Route::delete('group/delete/{msgId}', [GroupController::class, 'deleteMessage']); 
        Route::get('group/download/{msgId}', [GroupController::class, 'downloadAttachment']);
        Route::get('users/search', [GroupController::class, 'searchUsers']); 
        Route::post('groups/{id}/members', [GroupController::class, 'addMembers']);
        Route::delete('groups/{id}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::delete('group/{id}/clear', [GroupController::class, 'clearChat']);
    });

    Route::prefix('call')->group(function () {
        // Generate Token
        Route::post('/token', [AgoraController::class, 'generateToken']);

        // Call Actions
        Route::post('/invite', [AgoraController::class, 'inviteCall']);
        Route::post('/answer', [AgoraController::class, 'answerCall']);
        Route::post('/reject', [AgoraController::class, 'rejectCall']);
        Route::post('/cancel', [AgoraController::class, 'cancelCall']);
        Route::post('/end', [AgoraController::class, 'endCall']);

        // Histori panggilan
        Route::get('/history', [AgoraController::class, 'getCallHistory']);
    });

    Route::prefix('group-call')->as('group-call.')->group(function () {
      Route::post('/invite', [AgoraController::class, 'inviteGroupCall'])->name('invite');
      Route::post('/answer', [AgoraController::class, 'answerGroupCall'])->name('answer');
      Route::post('/end', [AgoraController::class, 'endGroupCall'])->name('end');
      Route::post('/cancel', [AgoraController::class, 'cancelGroupCall'])->name('cancel'); // <-- PASTIKAN BARIS INI ADA
      Route::post('/leave', [AgoraController::class, 'leaveGroupCall'])->name('leave');
      Route::post('/recall', [AgoraController::class, 'recallParticipant'])->name('recall');
      Route::post('/missed', [AgoraController::class, 'missedGroupCall'])->name('missed');
      Route::post('/token', [AgoraController::class, 'generateGroupToken'])->name('token');
    });
});
