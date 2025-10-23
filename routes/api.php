<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\UserController;

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

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// amazonq-ignore-next-line
Route::get('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    // User Profile & Status
    Route::get('profile', [UserController::class, 'profile']);
    Route::put('profile', [UserController::class, 'updateProfile']);
    Route::put('status', [UserController::class, 'updateStatus']);
    Route::put('privacy-settings', [UserController::class, 'updatePrivacySettings']);
    Route::post('users/{user}/block', [UserController::class, 'blockUser']);
    Route::delete('users/{user}/block', [UserController::class, 'unblockUser']);
    Route::get('blocked-users', [UserController::class, 'blockedUsers']);
    Route::get('users/search', [UserController::class, 'searchUsers']);
    
    // Chat Rooms
    Route::get('chat-rooms', [ChatRoomController::class, 'index']);
    Route::post('chat-rooms', [ChatRoomController::class, 'store']);
    Route::get('chat-rooms/{chatRoom}', [ChatRoomController::class, 'show']);
    Route::put('chat-rooms/{chatRoom}', [ChatRoomController::class, 'update']);
    Route::delete('chat-rooms/{chatRoom}', [ChatRoomController::class, 'destroy']);
    Route::post('chat-rooms/{chatRoom}/leave', [ChatRoomController::class, 'leave']);
    
    // Chat Room Participants
    Route::post('chat-rooms/{chatRoom}/participants', [ChatRoomController::class, 'addParticipant']);
    Route::delete('chat-rooms/{chatRoom}/participants/{user}', [ChatRoomController::class, 'removeParticipant']);
    Route::post('chat-rooms/{chatRoom}/participants/{user}/mute', [ChatRoomController::class, 'muteParticipant']);
    Route::delete('chat-rooms/{chatRoom}/participants/{user}/mute', [ChatRoomController::class, 'unmuteParticipant']);
    
    // Messages
    Route::get('chat-rooms/{chatRoom}/messages', [MessageController::class, 'index']);
    Route::post('chat-rooms/{chatRoom}/messages', [MessageController::class, 'store']);
    Route::put('chat-rooms/{chatRoom}/messages/{message}', [MessageController::class, 'update']);
    Route::delete('chat-rooms/{chatRoom}/messages/{message}', [MessageController::class, 'destroy']);
    Route::get('chat-rooms/{chatRoom}/messages/search', [MessageController::class, 'search']);
    Route::post('chat-rooms/{chatRoom}/messages/{message}/forward', [MessageController::class, 'forward']);
    
    // Message Reactions
    Route::post('chat-rooms/{chatRoom}/messages/{message}/reactions', [MessageController::class, 'addReaction']);
    Route::delete('chat-rooms/{chatRoom}/messages/{message}/reactions', [MessageController::class, 'removeReaction']);
    
    // Media
    Route::post('media/upload', [MediaController::class, 'upload']);
    Route::get('media/{type}/{filename}', [MediaController::class, 'serve']);
});