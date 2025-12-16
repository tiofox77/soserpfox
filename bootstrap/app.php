<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global para identificar tenant
        $middleware->append(\App\Http\Middleware\IdentifyTenant::class);
        
        // Middleware global para verificar se tenant está ativo
        $middleware->append(\App\Http\Middleware\CheckTenantActive::class);
        
        // Middleware global para verificar subscription (após identificar tenant)
        // IMPORTANTE: Só executa em rotas web que exigem autenticação
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckSubscription::class);
        
        // Middleware para registrar último login
        $middleware->append(\App\Http\Middleware\RecordLastLogin::class);
        
        // Middleware aliases
        $middleware->alias([
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'tenant.module' => \App\Http\Middleware\CheckTenantModule::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'tenant.active' => \App\Http\Middleware\CheckTenantActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
