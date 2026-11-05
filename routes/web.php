<?php

use App\Http\Controllers\LocalSystemAdminBootstrapController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('register', [LocalSystemAdminBootstrapController::class, 'create'])
    ->name('register');
Route::post('register', [LocalSystemAdminBootstrapController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.store');

Route::middleware(['auth', 'active-account'])->group(function () {
    Route::get('security/mfa/setup', [MfaController::class, 'show'])
        ->name('mfa.setup');
    Route::post('security/mfa/setup', [MfaController::class, 'start'])
        ->middleware('password.confirm')
        ->name('mfa.setup.start');
    Route::post('security/mfa/confirm', [MfaController::class, 'confirm'])
        ->middleware('password.confirm')
        ->name('mfa.setup.confirm');
});

Route::middleware('protected')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('users', UserDirectoryController::class)
        ->middleware(['user-directory-preview', 'permission:iam.users.view'])
        ->name('users.index');
});
