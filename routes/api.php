<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ruvelo\Translations\Http\Controllers\Api\ChangeController;
use Ruvelo\Translations\Http\Controllers\Api\EntryController;
use Ruvelo\Translations\Http\Controllers\Api\LocaleController;
use Ruvelo\Translations\Http\Middleware\AuthorizeEditing;

// The JSON API. Off by default: set TRANSLATIONS_API=true (or translations.api.enabled).
Route::group([
    'prefix' => config('translations.api.prefix', 'api/translations'),
    'domain' => config('translations.domain'),
    'middleware' => [...config('translations.api.middleware', ['api', 'auth:sanctum']), AuthorizeEditing::class],
    'as' => 'translations.api.',
], function () {
    $locale = '[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,3}';

    Route::get('locales', [LocaleController::class, 'index'])->name('locales.index');
    Route::post('locales', [LocaleController::class, 'store'])->name('locales.store');
    Route::get('locales/{locale}/entries', [EntryController::class, 'index'])->where('locale', $locale)->name('entries.index');
    Route::put('locales/{locale}/entries', [EntryController::class, 'update'])->where('locale', $locale)->name('entries.update');
    Route::delete('locales/{locale}/entries', [EntryController::class, 'destroy'])->where('locale', $locale)->name('entries.destroy');
    Route::get('changes', [ChangeController::class, 'index'])->name('changes.index');
});
