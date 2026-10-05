<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Public content API (read-only, cached by the Next.js frontend).
| Base URL: /api/v1
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::get('globals', [V1\GlobalController::class, 'index'])->name('globals.index');
    Route::get('globals/{key}', [V1\GlobalController::class, 'show'])->name('globals.show');

    Route::get('pages', [V1\PageController::class, 'index'])->name('pages.index');
    Route::get('pages/{slug}', [V1\PageController::class, 'show'])->name('pages.show');

    Route::get('posts', [V1\PostController::class, 'index'])->name('posts.index');
    Route::get('posts/{slug}', [V1\PostController::class, 'show'])->name('posts.show');

    Route::get('events', [V1\EventController::class, 'index'])->name('events.index');
    Route::get('events/{slug}', [V1\EventController::class, 'show'])->name('events.show');

    Route::get('programs', [V1\ProgramController::class, 'index'])->name('programs.index');
    Route::get('programs/{slug}', [V1\ProgramController::class, 'show'])->name('programs.show');

    Route::get('board-members', [V1\DirectoryController::class, 'boardMembers'])->name('board-members.index');
    Route::get('partners', [V1\DirectoryController::class, 'partners'])->name('partners.index');

    Route::post('registrations', [V1\RegistrationController::class, 'store'])
        ->middleware('throttle:registrations')
        ->name('registrations.store');
});
