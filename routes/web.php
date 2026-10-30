<?php

use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', EnsureActiveAccount::class])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});
