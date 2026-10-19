<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Coach;
use App\Http\Controllers\Player;
use App\Http\Controllers\Trainer;
use App\Support\NavigationBuilder;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('password/force', [ForcePasswordChangeController::class, 'edit'])->name('password.force');
    Route::put('password/force', [ForcePasswordChangeController::class, 'update'])->name('password.force.update');
});

Route::middleware(['auth', 'verified', 'password.change'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(NavigationBuilder::dashboardRoute(auth()->user()->role)))
        ->name('dashboard');

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:trainer')->prefix('trainer')->name('trainer.')->group(function () {
        Route::get('dashboard', Trainer\DashboardController::class)->name('dashboard');
    });

    Route::middleware('role:coach')->prefix('coach')->name('coach.')->group(function () {
        Route::get('dashboard', Coach\DashboardController::class)->name('dashboard');
    });

    Route::middleware('role:player')->prefix('player')->name('player.')->group(function () {
        Route::get('dashboard', Player\DashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
