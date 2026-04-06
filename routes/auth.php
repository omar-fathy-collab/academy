<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Academic\RegistrationController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/register', [UserController::class, 'showRegistrationForm'])->name('register');
        Route::post('/register', [UserController::class, 'registerStudent'])->name('register.submit');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
        Route::post('/register/student', [UserController::class, 'registerStudent'])->name('register.submit.alternative');
    });

    Route::get('/loginpage', [AuthController::class, 'showLoginForm'])->name('login');
    Route::get('/register/student', [UserController::class, 'showRegistrationForm'])->name('register.form');
});

Route::get('/registration/success', [RegistrationController::class, 'success'])->name('registration.success');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Unauthorized / Access Denied
Route::get('/unauthorized', function () {
    return response()->view('errors.403', [], 403);
})->name('unauthorized');

// Social Authentication Routes
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::get('/auth/github', [SocialAuthController::class, 'redirectToGithub'])->name('auth.github');
Route::get('/auth/github/callback', [SocialAuthController::class, 'handleGithubCallback'])->name('auth.github.callback');
