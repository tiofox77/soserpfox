<?php

namespace App\Console\Commands;

use App\Services\Invoicing\Relatorios\LucrosEPerdas;
use App\Services\Treasury\DreIntegrado;
use App\Services\Treasury\RelatoriosDeTesouraria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * OS TRÊS DRE DE UMA EMPRESA, LADO A LADO — só lê (27/09/2026).
 *
 * Para o suporte conferir o que o cliente vê sem entrar na conta dele: o da
 * Faturação, o da Tesouraria e o Integrado, do mesmo período. Nada de dados
 * pessoais: só números e o nome da empresa.
 *
 *   relatorios:dre-integrado --tenant=42 [--de=2026-09-01 --ate=2026-09-30]
 *   relatorios:dre-integrado --ticket=TKT-000003 --assunto=DRE   (encontra a empresa)
 */
class VerDreIntegrado extends Command
{
    protected $signature = 'relatorios:dre-integrado
        {--tenant= : a empresa}
        {--ticket= : n.º do pedido de suporte, para encontrar a empresa}
        {--assunto= : palavra no assunto ou na descrição do pedido}
        {--de= : início (AAAA-MM-DD); por omissão o mês corrente}
        {--ate= : fim (AAAA-MM-DD)}';

    protected $description = 'Mostra os três DRE de uma empresa, lado a lado (só lê)';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');

        if (! $tenantId && $this->option('ticket')) {
            $ids = DB::table('support_tickets')
                ->where('ticket_number', $this->option('ticket'))
                ->when($this->option('assunto'), fn ($q, $a) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$a}%")->orWhere('description', 'like', "%{$a}%")))
                ->pluck('tenant_id')->unique()->values();

            foreach ($ids as $id) {
                $this->line("Pedido {$this->option('ticket')} → empresa #{$id} — ".DB::table('tenants')->where('id', $id)->value('name'));
            }

            if ($ids->count() !== 1) {
                $this->warn($ids->isEmpty() ? 'Nenhum pedido encontrado.' : 'Mais de uma empresa — escolha com --tenant.');

                return self::SUCCESS;
            }

            $tenantId = (int) $ids->first();
        }

        if (! $tenantId) {
            $this->error('Indique --tenant ou --ticket.');

            return self::FAILURE;
        }

        $de = $this->option('de') ?: now()->startOfMonth()->toDateString();
        $ate = $this->option('ate') ?: now()->endOfMonth()->toDateString();
        $kz = fn ($v) => number_format((float) $v, 2, ',', ' ');

        $this->info("EMPRESA #{$tenantId} — ".DB::table('tenants')->where('id', $tenantId)->value('name')."  ({$de} a {$ate})");

        $f = (new LucrosEPerdas())->dados($tenantId, ['period' => 'custom', 'dateFrom' => $de, 'dateTo' => $ate]);
        $this->line('');
        $this->line('<options=bold>DRE da Faturação (sem IVA)</>');
        foreach ($f['linhas'] as $l) {
            $this->line(sprintf('  %-48s %18s', $l['rotulo'], $kz($l['valor'])));
        }

        $t = (new RelatoriosDeTesouraria($tenantId, $de, $ate))->demonstracaoDeResultados();
        $this->line('');
        $this->line('<options=bold>DRE da Tesouraria (com IVA)</>');
        foreach (['Receita bruta' => $t['grossRevenue'], 'Devoluções (NC)' => -$t['deductions'], 'Receita líquida' => $t['netRevenue'],
            'Compras (facturas)' => -$t['operationalCosts'], 'Lucro bruto' => $t['grossProfit'], 'Despesas' => -$t['totalExpenses'],
            'Resultado' => $t['netProfit']] as $rot => $v) {
            $this->line(sprintf('  %-48s %18s', $rot, $kz($v)));
        }

        $i = (new DreIntegrado($tenantId, $de, $ate))->dados();
        $this->line('');
        $this->line('<options=bold>DRE Integrado (sem IVA)</>');
        foreach ($i['linhas'] as $l) {
            $this->line(sprintf('  %-48s %18s', str_repeat('  ', $l['nivel']).$l['rotulo'], $kz($l['valor'])));
        }
        foreach ($i['fora_do_resultado'] as $x) {
            $this->line(sprintf('  fora: %-42s saídas %14s  entradas %14s', mb_substr($x['rotulo'], 0, 42), $kz($x['saidas']), $kz($x['entradas'])));
        }
        if ($i['por_classificar']) {
            $this->warn('  Por classificar: '.implode(', ', array_column($i['por_classificar'], 'categoria')));
        }

        return self::SUCCESS;
    }
}
