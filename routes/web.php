<?php

use Illuminate\Support\Facades\Route;
use NexusPlugin\Blindbox\Http\Controllers\AdminController;

Route::middleware(['web', 'auth'])->prefix('blindbox')->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('blindbox.admin.index');
    Route::post('/admin/prize/{id}', [AdminController::class, 'updatePrize'])->name('blindbox.admin.prize.update');
    Route::post('/admin/prize', [AdminController::class, 'createPrize'])->name('blindbox.admin.prize.create');
    Route::delete('/admin/prize/{id}', [AdminController::class, 'deletePrize'])->name('blindbox.admin.prize.delete');
    Route::get('/admin/history', [AdminController::class, 'history'])->name('blindbox.admin.history');
});
