<?php

namespace App\Console\Commands;

use App\Services\Billing\AvisosDeSubscricao;
use Illuminate\Console\Command;

/**
 * Faz a varredura dos avisos de facturação ao cliente.
 *
 * Corre sozinho à boleia do tráfego (App\Http\Middleware\AvisarSubscricoes);
 * existe como comando para se poder VER o que sairia antes de ligar seja o que
 * for — que é obrigatório, porque do outro lado estão pessoas reais.
 */
class AvisarSubscricoes extends Command
{
    protected $signature = 'subscricoes:avisos
                            {--so-ver : Mostra o que enviaria, sem enviar nem deixar rasto}';

    protected $description = 'Avisa os clientes das facturas a vencer, vencidas e dos períodos a acabar';

    public function handle(AvisosDeSubscricao $avisos): int
    {
        $soVer = (bool) $this->option('so-ver');

        if ($soVer) {
            $this->warn('Modo de leitura: nada é enviado e nada fica registado.');
        } else {
            if (!config('billing.avisos_ao_cliente', false)) {
                $this->warn('O disparo automático está DESLIGADO (config/billing.php). '
                    . 'Esta passagem envia, mas não haverá outras sem alguém as pedir.');
            }

            if (!config('billing.avisos_sms', false)) {
                $this->line('SMS desligado — só sai email. (billing.avisos_sms)');
            }
        }

        $r = $avisos->varrer($soVer);

        if (!empty($r['detalhe'])) {
            $this->table(
                ['Aviso', 'Empresa', 'Dias', 'Factura'],
                array_map(fn ($l) => [
                    $l['aviso'],
                    $l['empresa'] ?? '—',
                    $l['dias'],
                    $l['factura'],
                ], $r['detalhe'])
            );
        }

        $this->info(sprintf(
            '%d email(s), %d SMS, %d repetido(s), %d falhado(s), %d sem contacto.',
            $r['email'], $r['sms'], $r['repetidos'], $r['falhados'], $r['sem_contacto']
        ));

        if (!empty($r['avariou'])) {
            // Um resumo a zeros por avaria lê-se exactamente como um resumo
            // a zeros por não haver nada a fazer. Tem de se distinguir, e o
            // código de saída tem de ser diferente de zero.
            $this->error('AVARIA: a base de dados recusou reservas — ver o log. '
                . 'NADA foi enviado a esses clientes.');

            return self::FAILURE;
        }

        if (!empty($r['tecto_atingido'])) {
            $this->warn('Tecto da passagem atingido — o resto sai na passagem seguinte.');
        }

        return self::SUCCESS;
    }
}
