<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

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

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify', [AuthController::class, 'verify']);
    
    // Events
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/open', [EventController::class, 'open']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    
    // User registration
    Route::post('/users', [UserController::class, 'store']);
    
    // QR Code validation
    Route::post('/qr/validate', [UserController::class, 'validateQR']);
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    });
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // Events management
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    Route::get('/events/{event}/stats', [EventController::class, 'stats']);
    
    // Users management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/users/stats', [UserController::class, 'stats']);
    Route::post('/users/{user}/resend-email', [UserController::class, 'resendEmail']);
});

// User authentication
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
