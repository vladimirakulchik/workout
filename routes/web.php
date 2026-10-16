<?php

use App\Http\Controllers\Auth\ForcePasswordChangeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('password/force', [ForcePasswordChangeController::class, 'edit'])->name('password.force');
    Route::put('password/force', [ForcePasswordChangeController::class, 'update'])->name('password.force.update');
});

Route::middleware(['auth', 'verified', 'password.change'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
