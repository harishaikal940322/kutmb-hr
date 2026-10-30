<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('protected')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('users', UserDirectoryController::class)
        ->middleware(['user-directory-preview', 'permission:iam.users.view'])
        ->name('users.index');
});
