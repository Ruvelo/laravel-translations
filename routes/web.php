<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ruvelo\Translations\Http\Controllers\ChangesController;
use Ruvelo\Translations\Http\Controllers\EditModeController;
use Ruvelo\Translations\Http\Controllers\EditorController;
use Ruvelo\Translations\Http\Controllers\EntryController;
use Ruvelo\Translations\Http\Controllers\LocaleController;
use Ruvelo\Translations\Http\Controllers\OverviewController;
use Ruvelo\Translations\Http\Controllers\SuggestionController;
use Ruvelo\Translations\Http\Middleware\AuthorizeEditing;

// Every route needs the `translations-edit` gate. Locale codes never look
// like "changes" or "locales", so the fixed pages can't collide with them.
Route::group([
    'prefix' => config('translations.path', 'translations'),
    'domain' => config('translations.domain'),
    'middleware' => [...config('translations.middleware', ['web']), AuthorizeEditing::class],
    'as' => 'translations.',
], function () {
    $locale = '[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,3}';

    Route::get('/', OverviewController::class)->name('index');
    Route::get('/changes', ChangesController::class)->name('changes');
    Route::post('/locales', [LocaleController::class, 'store'])->name('locales.store');
    Route::post('/edit-mode', EditModeController::class)->name('edit-mode');

    Route::get('/{locale}', EditorController::class)->where('locale', $locale)->name('editor');
    Route::put('/{locale}/entries', [EntryController::class, 'update'])->where('locale', $locale)->name('entries.update');
    Route::delete('/{locale}/entries', [EntryController::class, 'destroy'])->where('locale', $locale)->name('entries.destroy');
    Route::post('/{locale}/suggest', SuggestionController::class)->where('locale', $locale)
        ->middleware('throttle:translations-suggest')->name('suggest');
});
