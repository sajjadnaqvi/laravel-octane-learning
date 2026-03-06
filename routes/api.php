<?php

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ============================================
// Public Authentication Routes
// ============================================
Route::prefix('auth')->group(function () {
    // Register new user
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1') // 5 attempts per minute
        ->name('auth.register');
    
    // Login user
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1') // 10 attempts per minute
        ->name('auth.login');
});

// ============================================
// Protected Authentication Routes
// ============================================
Route::prefix('auth')->middleware(['auth:api'])->group(function () {
    // Get authenticated user details
    Route::get('/user', [AuthController::class, 'user'])
        ->name('auth.user');
    
    // Logout from current device
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');
    
    // Logout from all devices
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])
        ->name('auth.logout.all');
    
    // Refresh access token
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->name('auth.refresh');
    
    // Get all active tokens
    Route::get('/tokens', [AuthController::class, 'tokens'])
        ->name('auth.tokens');
    
    // Revoke a specific token
    Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken'])
        ->name('auth.tokens.revoke');
});

// ============================================
// Legacy Routes (Keep for backward compatibility)
// ============================================
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::get('/test-octane', function() {
    $time = now();
    // $users = User::orderBy('id')->get(); 
    $users = DB::raw('select * from users order by id desc limit 10'); 
    // dump($time);
    return response()->json([
        'time' => $time,
        'users' => $users
    ]);

});

