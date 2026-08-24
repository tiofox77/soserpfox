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

// Pedidos de licença de instalações novas. Públicos por necessidade (ainda não
// têm licença nem credenciais) mas com limite de chamadas: só criam um pedido,
// e nada sai sem aprovação humana no painel.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/license/request', [\App\Http\Controllers\Api\LicenseRequestController::class, 'criar'])
        ->name('api.license.request');
    Route::get('/license/request/{codigo}', [\App\Http\Controllers\Api\LicenseRequestController::class, 'consultar'])
        ->name('api.license.request.check');
});
