<?php

namespace App\Console\Commands;

use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Illuminate\Console\Command;

/**
 * Emite as facturas dos períodos que estão a acabar.
 *
 * Corre sozinho à boleia do tráfego (ver App\Http\Middleware\FacturarRenovacoes)
 * porque este alojamento não tem processo permanente; existe também como
 * comando para se poder ver e forçar à mão.
 */
class RenovarSubscricoes extends Command
{
    protected $signature = 'subscriptions:renovar
                            {--dias= : Antecedência em dias (por omissão 8)}
                            {--so-ver : Mostra o que faria, sem gravar nada}';

    protected $description = 'Emite a factura do período seguinte das subscrições que estão a acabar';

    public function handle(RenovacaoDeSubscricoes $renovacao): int
    {
        $dias = (int) ($this->option('dias')
            ?: config('billing.dias_de_antecedencia', RenovacaoDeSubscricoes::DIAS_DE_ANTECEDENCIA));
        $soVer = (bool) $this->option('so-ver');

        if ($soVer) {
            $this->warn('Modo de leitura: nada será gravado.');
        } elseif (!config('billing.renovacao_automatica', false)) {
            // Correr à mão continua a valer — o interruptor só governa a
            // emissão sozinha. Mas quem o corre tem de saber que, ao sair
            // daqui, nada mais volta a emitir.
            $this->warn('A emissão automática está DESLIGADA (config/billing.php). '
                . 'Esta passagem grava, mas não haverá outras sem alguém as pedir.');
        }

        $r = $renovacao->emitirFacturasAVencer($dias, $soVer);

        if ($r['detalhe']) {
            $this->table(
                ['Empresa', 'Plano', 'Termina', 'Valor (Kz)', 'Factura'],
                array_map(fn ($l) => [
                    $l['empresa'],
                    $l['plano'],
                    $l['termina'],
                    number_format($l['valor'], 2, ',', '.'),
                    $l['factura'] ?? '—',
                ], $r['detalhe'])
            );
        }

        $this->info(sprintf(
            '%d factura(s) %s, %d ignorada(s) (já facturadas ou sem dados).',
            $r['emitidas'],
            $soVer ? 'por emitir' : 'emitida(s)',
            $r['ignoradas']
        ));

        return self::SUCCESS;
    }
}
