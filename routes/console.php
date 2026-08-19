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

// Facturas de renovação: a conta do período seguinte, 8 dias antes do fim.
//
// NÃO ESTÁ AGENDADO AQUI, DE PROPÓSITO. Corre à boleia do tráfego — ver
// App\Http\Middleware\FacturarRenovacoes — como as notificações e as
// submissões à AGT, e pela mesma razão: este alojamento não tem processo
// permanente e o `schedule:run` pode nunca ser chamado. Pôr a única coisa que
// faz a plataforma cobrar dependente de um cron que não se sabe se existe era
// repetir o erro que deixou clientes sem receberem a segunda factura.
//
// Basta haver alguém autenticado a usar o sistema: uma vez por hora, o pedido
// dele serve de relógio. O comando `subscriptions:renovar` continua a existir
// para ver (`--so-ver`) e forçar à mão.

// Expirar subscriptions vencidas - Executar a cada hora
Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Notificações agendadas dos templates activos.
//
// JÁ NÃO É PRECISO CRON. O envio corre à boleia do tráfego — ver
// App\Http\Middleware\DespacharNotificacoes — porque este alojamento não tem
// processo permanente e o `schedule:run` podia nunca ser chamado. Os modelos
// ficavam activos no ecrã e não saía notificação nenhuma, sem erro nenhum.
//
// A entrada FICA aqui, e não é contradição: quem tiver cron ganha uma rede de
// segurança para os dias sem ninguém a trabalhar, em que o tráfego não
// dispara nada. Correr pelos dois caminhos não duplica avisos — a memória do
// que já saiu (notification_sends) trata disso, com índice único por modelo,
// registo, canal, destinatário e dia.
//
// Sem cron configurado, esta linha simplesmente nunca corre e nada se perde.
Schedule::command('notifications:send-scheduled')
    ->twiceDaily(9, 15)
    ->withoutOverlapping()
    ->onOneServer();

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
