<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImageController;

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

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/upload-image', [ImageController::class, 'uploadImage']);
    Route::post('/upload-chunk', [ImageController::class, 'uploadChunk']);
    Route::get('/image-status/{id}', [ImageController::class, 'getImageStatus']);

    // Role-Based Access Control (RBAC) Routes
    Route::middleware('role:admin,user')->group(function () {
        Route::get('/user-images', [ImageController::class, 'getUserImages']);
    });

  
});
