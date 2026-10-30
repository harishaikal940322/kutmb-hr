<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('protected')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});
