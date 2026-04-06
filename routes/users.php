<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// User management routes
Route::middleware(['auth'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('/users/{user}/get', [UserController::class, 'getUser'])->name('users.get');
    Route::get('/users/fetch/data', [UserController::class, 'fetchUsers'])->name('users.fetch');
});
