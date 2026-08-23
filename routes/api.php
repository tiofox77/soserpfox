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

// Servidor de licenças: check-in das instalações offline (phone-home). Sem
// auth de sessão — a confiança vem da ASSINATURA do token. Inofensivo se a
// chave privada (LICENSE_SIGNING_KEY) não estiver configurada: não renova.
Route::post('/license/checkin', [\App\Http\Controllers\Api\LicenseServerController::class, 'checkin'])
    ->name('api.license.checkin');

// Servidor de atualizações: "há update para mim?". Mesma confiança pela
// assinatura do token; a versão-alvo vem do rollout por-tenant (super admin).
Route::post('/license/update', [\App\Http\Controllers\Api\UpdateServerController::class, 'check'])
    ->name('api.license.update');
