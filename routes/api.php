<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgoraCallController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ChatController;
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
