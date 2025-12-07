<?php

use Illuminate\Support\Facades\Route;
use NexusPlugin\Blindbox\Http\Controllers\BlindboxController;

// 不使用Laravel的auth中间件，因为NexusPHP有自己的认证系统
Route::prefix('api/blindbox')->group(function () {
    Route::post('/draw', [BlindboxController::class, 'draw']);
    Route::get('/history', [BlindboxController::class, 'history']);
    Route::get('/prizes', [BlindboxController::class, 'prizes']);
});
