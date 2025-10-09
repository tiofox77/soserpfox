<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificar produtos expirando - Executar todos os dias às 8h
Schedule::command('products:check-expiry --notify')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Verificação adicional às 17h (fim do dia)
Schedule::command('products:check-expiry --notify')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->onOneServer();

// Rejeitar pedidos pendentes há mais de 7 dias - Executar diariamente às 9h
Schedule::command('orders:reject-expired --days=7')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));
