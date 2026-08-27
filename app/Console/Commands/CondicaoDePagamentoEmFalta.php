<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Dá aos clientes antigos a condição de pagamento que a empresa definiu.
 *
 * A partir de agora um cliente novo nasce com ela (ver App\Models\Client), mas
 * quem já cá estava ficou sem nenhuma — e nesta base eram TODOS. Sem condição
 * o vencimento da factura cai no prazo geral das definições em vez do prazo
 * daquele cliente: não se perde nada, mas também não se ganha o que o catálogo
 * de condições prometia.
 *
 * SÓ PREENCHE O QUE ESTÁ VAZIO. Um cliente a quem alguém escolheu uma condição
 * à mão não é tocado — mesmo que seja diferente da padrão, porque essa escolha
 * foi deliberada.
 *
 * Por omissão só CONTA. Escrever exige `--aplicar`, porque isto mexe em dados
 * de clientes reais.
 */
class CondicaoDePagamentoEmFalta extends Command
{
    protected $signature = 'clientes:condicao-em-falta
                            {--tenant= : Só esta empresa (id)}
                            {--aplicar : Grava. Sem isto, só mostra o que faria}';

    protected $description = 'Atribui aos clientes sem condição de pagamento a condição padrão da empresa';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $linhas = [];
        $total = 0;

        foreach ($empresas as $empresa) {
            $semCondicao = Client::withoutGlobalScopes()
                ->where('tenant_id', $empresa->id)
                ->whereNull('payment_term_id')
                ->count();

            if (!$semCondicao) {
                continue;
            }

            $padrao = PaymentTerm::padraoDe($empresa->id);

            if (!$padrao) {
                // Sem catálogo não há o que atribuir. Dizê-lo é melhor do que
                // saltar em silêncio: é uma empresa que fica de fora.
                $linhas[] = [$empresa->id, $empresa->name, $semCondicao, '— sem condições no catálogo —', 0];
                continue;
            }

            if ($aplicar) {
                Client::withoutGlobalScopes()
                    ->where('tenant_id', $empresa->id)
                    ->whereNull('payment_term_id')
                    ->update([
                        'payment_term_id'   => $padrao->id,
                        'payment_term_days' => $padrao->days,
                    ]);
            }

            $linhas[] = [$empresa->id, $empresa->name, $semCondicao, $padrao->name, $padrao->days];
            $total += $semCondicao;
        }

        if (!$linhas) {
            $this->info('Não há clientes sem condição de pagamento.');

            return self::SUCCESS;
        }

        $this->table(['Empresa', 'Nome', 'Clientes', 'Condição', 'Dias'], $linhas);

        if ($aplicar) {
            $this->info("Atribuída a condição padrão a {$total} cliente(s).");
        } else {
            $this->warn("{$total} cliente(s) ficariam com a condição padrão. Nada foi gravado — repita com --aplicar.");
        }

        return self::SUCCESS;
    }
}
