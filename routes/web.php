<?php

declare(strict_types=1);

use App\Http\Controllers\AddressSearchController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSignUpController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\GameSelectionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
 * Every route is named. A named route is what `route('home')` in a Blade template
 * resolves, so a URL can change in one place instead of being hunted through the
 * views.
 *
 * Name them <plural>.<action> once there is a resource — `posts.index`,
 * `posts.store` — because that is what `php artisan make:controller --resource`
 * generates and what every Laravel reader already expects.
 */

Route::view('/', 'home')->name('home');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::middleware('auth')->group(function (): void {
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::get('/events/addresses/search', AddressSearchController::class)->middleware('throttle:30,1')->name('events.addresses.search');
    Route::get('/events/games/search', GameSearchController::class)->middleware('throttle:20,1')->name('events.games.search');
    Route::post('/events/games', GameSelectionController::class)->middleware('throttle:12,1')->name('events.games.store');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/status', [EventController::class, 'toggleStatus'])->name('events.toggle-status');
    Route::post('/events/{event}/sign-up', [EventSignUpController::class, 'store'])->name('events.sign-up.store');
    Route::delete('/events/{event}/sign-up', [EventSignUpController::class, 'destroy'])->name('events.sign-up.destroy');
});
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/games', [AdminGameController::class, 'index'])->name('games.index');
    Route::post('/games', [AdminGameController::class, 'store'])->name('games.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
