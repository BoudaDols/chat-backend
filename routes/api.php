<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\MessageController;

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

Route::get('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    // Chat Rooms
    Route::get('chat-rooms', [ChatRoomController::class, 'index']);
    Route::post('chat-rooms', [ChatRoomController::class, 'store']);
    Route::get('chat-rooms/{chatRoom}', [ChatRoomController::class, 'show']);
    
    // Messages
    Route::get('chat-rooms/{chatRoom}/messages', [MessageController::class, 'index']);
    Route::post('chat-rooms/{chatRoom}/messages', [MessageController::class, 'store']);
});