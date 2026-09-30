<?php

use App\Http\Controllers\Api\Plataforma\AparelhosPwaApiController;
use App\Http\Controllers\Api\Plataforma\EmpresaPlanoApiController;
use App\Http\Controllers\Api\Plataforma\EmpresaUtilizadoresApiController;
use App\Http\Controllers\Api\Plataforma\ModulosApiController;
use App\Http\Controllers\Api\Plataforma\PedidosDeEstabelecimentosApiController;
use App\Http\Middleware\ContextoAdministrativoDoAgente;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Agent\LicencasController;

// Lista fechada: nao encaminhar caminhos/classes fornecidos pelo cliente.
Route::prefix('admin')->middleware(ContextoAdministrativoDoAgente::class)->group(function () {
    Route::middleware('throttle:agent-read')->group(function () {
        Route::middleware('agent.scope:licenses:read')->group(function () {
            Route::get('licenciamento', [LicencasController::class, 'index']);
            Route::get('licenciamento/instalacoes/{id}', [LicencasController::class, 'instalacao']);
        });
        Route::middleware('agent.scope:users:read')->group(function () {
            Route::get('empresas/{empresa}/utilizadores', [EmpresaUtilizadoresApiController::class, 'index']);
            Route::get('empresas/{empresa}/utilizadores/procurar', [EmpresaUtilizadoresApiController::class, 'procurar']);
        });
        Route::middleware('agent.scope:subscriptions:read')->group(function () {
            Route::get('empresas/{empresa}/plano', [EmpresaPlanoApiController::class, 'index']);
            Route::get('empresas/{empresa}/plano-a-medida', [EmpresaPlanoApiController::class, 'medida']);
        });
        Route::middleware('agent.scope:modules:read')->group(function () {
            Route::get('modulos', [ModulosApiController::class, 'index']);
            Route::get('modulos/{id}', [ModulosApiController::class, 'ficha']);
        });
        Route::get('pedidos-de-estabelecimentos', [PedidosDeEstabelecimentosApiController::class, 'index'])->middleware('agent.scope:venues:read');
        Route::get('aparelhos-pwa', [AparelhosPwaApiController::class, 'index'])->middleware('agent.scope:devices:read');
    });
    Route::middleware(['throttle:agent-write', 'agent.idempotencia'])->group(function () {
        Route::middleware('agent.scope:licenses:write')->group(function () {
            Route::post('licenciamento/licencas', [LicencasController::class, 'emitir']);
            Route::post('licenciamento/instalacoes/{id}/renovar', [LicencasController::class, 'renovar']);
            Route::post('licenciamento/pedidos/{id}/aprovar', [LicencasController::class, 'aprovarPedido']);
            Route::post('licenciamento/pedidos/{id}/recusar', [LicencasController::class, 'recusarPedido']);
        });
        Route::middleware('agent.scope:users:write')->group(function () {
            Route::post('empresas/{empresa}/utilizadores', [EmpresaUtilizadoresApiController::class, 'juntar']);
            Route::put('empresas/{empresa}/utilizadores/{utilizador}/papel', [EmpresaUtilizadoresApiController::class, 'papel']);
            Route::delete('empresas/{empresa}/utilizadores/{utilizador}', [EmpresaUtilizadoresApiController::class, 'retirar']);
        });
        Route::middleware('agent.scope:subscriptions:write')->group(function () {
            Route::post('empresas/{empresa}/plano/resumo', [EmpresaPlanoApiController::class, 'resumo']);
            Route::put('empresas/{empresa}/plano', [EmpresaPlanoApiController::class, 'guardar']);
            Route::post('empresas/{empresa}/plano-a-medida', [EmpresaPlanoApiController::class, 'guardarMedida']);
        });
        Route::middleware('agent.scope:modules:write')->group(function () {
            Route::post('modulos', [ModulosApiController::class, 'guardar']);
            Route::put('modulos/{id}', [ModulosApiController::class, 'guardar']);
            Route::post('modulos/{id}/alternar', [ModulosApiController::class, 'alternar']);
            Route::delete('modulos/{id}', [ModulosApiController::class, 'apagar']);
        });
        Route::middleware('agent.scope:venues:write')->group(function () {
            Route::post('pedidos-de-estabelecimentos/{id}/aprovar', [PedidosDeEstabelecimentosApiController::class, 'aprovar']);
            Route::post('pedidos-de-estabelecimentos/{id}/recusar', [PedidosDeEstabelecimentosApiController::class, 'recusar']);
        });
    });
});
