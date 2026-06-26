<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LeadController;
use App\Http\Controllers\API\UserController;

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

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });
});

// Protected Routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Users Management (Team)
    Route::apiResource('users', UserController::class);
    Route::put('users/{user}/password', [UserController::class, 'changePassword']);
    Route::get('users/{user}/activities', [UserController::class, 'activities']);
    
    // Leads Management
    Route::apiResource('leads', LeadController::class);
    Route::put('leads/{lead}/assign', [LeadController::class, 'assign']);
    Route::put('leads/{lead}/stage', [LeadController::class, 'updateStage']);
    Route::post('leads/bulk-assign', [LeadController::class, 'bulkAssign']);
    
    // Test route
    Route::get('test', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is working!',
            'user' => auth()->user()
        ]);
    });
});

// Public test route
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Lumen CRM API is running',
        'version' => '1.0.0',
        'timestamp' => now()
    ]);
});