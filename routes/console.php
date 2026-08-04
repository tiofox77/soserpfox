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

// Hotel: lembretes de pré-chegada (2 dias antes) - diariamente às 10h
Schedule::command('hotel:send-prearrival --days=2')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->onOneServer();

// Expirar subscriptions vencidas - Executar a cada hora
Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Notificações agendadas dos templates activos.
//
// O comando existia e nunca era chamado por ninguém — os templates ficavam
// marcados como activos no ecrã de definições e não saía notificação nenhuma,
// sem erro em lado nenhum. Duas vezes por dia chega para avisos de validade,
// stock e vencimentos, e não incomoda ninguém à noite.
Schedule::command('notifications:send-scheduled')
    ->twiceDaily(9, 15)
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Arquivar a trilha de auditoria antiga.
//
// Uma venda de balcão com três artigos gera 14 linhas — a 50 vendas/dia são
// ~255 mil linhas e ~120 MB por ano e por empresa. Sem arquivo a tabela cresce
// para sempre.
//
// Não faz nada enquanto AUDIT_RETENTION_DAYS estiver a 0 (o valor por omissão),
// que é o que se quer até haver política de retenção acordada com o cliente:
// num ERP fiscal o prazo legal é de anos, não de meses.
Schedule::command('audit:archive')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));
