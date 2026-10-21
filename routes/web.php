<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Coach;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\Player;
use App\Http\Controllers\Trainer;
use App\Support\NavigationBuilder;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('join/{code}', [JoinController::class, 'show'])->name('join.show');

Route::post('join/{code}', [JoinController::class, 'register'])->middleware('guest')->name('join.register');

Route::post('join/{code}/coach', [JoinController::class, 'registerCoach'])->middleware('guest')->name('join.register-coach');

Route::middleware(['auth', 'verified', 'role:player', 'child.restricted'])->group(function () {
    Route::get('join/{code}/select', [JoinController::class, 'select'])->name('player.join.select');
    Route::post('join/{code}/select', [JoinController::class, 'associate'])->name('player.join.associate');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('password/force', [ForcePasswordChangeController::class, 'edit'])->name('password.force');
    Route::put('password/force', [ForcePasswordChangeController::class, 'update'])->name('password.force.update');
});

Route::delete('impersonate/leave', [Admin\ImpersonationController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonate.leave');

Route::middleware(['auth', 'verified', 'password.change'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(NavigationBuilder::dashboardRoute(auth()->user()->role)))
        ->name('dashboard');

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/deactivate', [Admin\UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [Admin\UserController::class, 'reactivate'])->name('users.reactivate');
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/impersonate', [Admin\ImpersonationController::class, 'take'])->name('users.impersonate');

        Route::get('impersonation-history', Admin\ImpersonationHistoryController::class)->name('impersonation-history');
    });

    Route::middleware('role:trainer')->prefix('trainer')->name('trainer.')->group(function () {
        Route::get('dashboard', Trainer\DashboardController::class)->name('dashboard');

        Route::get('share-links', [Trainer\ShareLinkController::class, 'index'])->name('share-links.index');
        Route::post('share-links', [Trainer\ShareLinkController::class, 'store'])->name('share-links.store');
        Route::post('share-links/regenerate', [Trainer\ShareLinkController::class, 'regenerate'])->name('share-links.regenerate');
        Route::delete('share-links/{shareLink}', [Trainer\ShareLinkController::class, 'deactivate'])->name('share-links.deactivate');

        Route::get('coaches', [Trainer\CoachController::class, 'index'])->name('coaches.index');
        Route::post('coaches', [Trainer\CoachController::class, 'store'])->name('coaches.store');
        Route::post('coaches/invitations/{shareLink}/resend', [Trainer\CoachController::class, 'resend'])->name('coaches.resend');
        Route::delete('coaches/{coachProfile}', [Trainer\CoachController::class, 'destroy'])->name('coaches.destroy');

        Route::get('players', [Trainer\PlayerController::class, 'index'])->name('players.index');
        Route::get('players/availability-count', [Trainer\PlayerController::class, 'availabilityCount'])->name('players.availability-count');
    });

    Route::middleware('role:coach')->prefix('coach')->name('coach.')->group(function () {
        Route::get('dashboard', Coach\DashboardController::class)->name('dashboard');

        Route::get('availability', [Coach\AvailabilityController::class, 'edit'])->name('availability.edit');
        Route::put('availability', [Coach\AvailabilityController::class, 'update'])->name('availability.update');
    });

    Route::middleware(['role:player', 'player.context'])->prefix('player')->name('player.')->group(function () {
        Route::get('dashboard', Player\DashboardController::class)->name('dashboard');

        Route::get('availability', [Player\AvailabilityController::class, 'edit'])->name('availability.edit');
        Route::put('availability', [Player\AvailabilityController::class, 'update'])->name('availability.update');

        Route::middleware('child.restricted')->group(function () {
            Route::resource('family', Player\ChildProfileController::class)
                ->parameters(['family' => 'childProfile'])
                ->except('show');

            Route::post('family/{childProfile}/trainers', [Player\ChildTrainerController::class, 'store'])->name('family.trainers.store');
            Route::delete('family/{childProfile}/trainers/{trainer}', [Player\ChildTrainerController::class, 'destroy'])->name('family.trainers.destroy');
        });

        Route::post('context', [Player\ContextController::class, 'update'])->name('context.update');
    });
});

require __DIR__.'/settings.php';
