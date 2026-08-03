<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AGTCallbackController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas API públicas (sem autenticação session/web).
| Prefix: /api
|
*/

// AGT Facturação Electrónica — Callback (POST da AGT para notificar resultado)
Route::post('/facturacaoelectronica/callback', [AGTCallbackController::class, 'handle'])
    ->name('api.agt.callback');
