<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Middleware\AuthenticateAssistant;
use Illuminate\Support\Facades\Route;

/*
 * Accès en lecture seule du service Assistant IA (voir config/assistant.php).
 * Aucune route d'écriture : l'assistant consulte et explique, il ne modifie jamais rien.
 */
Route::prefix('assistant')->name('api.assistant.')->middleware('throttle:120,1')->group(function () {
    Route::get('/resolve-phone', [AssistantController::class, 'resolvePhone'])
        ->middleware(AuthenticateAssistant::class.':service')->name('resolve-phone');

    Route::middleware(AuthenticateAssistant::class)->group(function () {
        Route::get('/me', [AssistantController::class, 'me'])->name('me');
        Route::get('/rules', [AssistantController::class, 'rules'])->name('rules');
        Route::get('/me/contributions', [AssistantController::class, 'contributions'])->name('contributions');
        Route::get('/me/penalty', [AssistantController::class, 'penalty'])->name('penalty');
    });
});
