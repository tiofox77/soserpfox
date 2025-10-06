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
