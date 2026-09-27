<?php

namespace App\Services\Invoicing\Relatorios;

use App\Services\Treasury\DreIntegrado;

/**
 * A demonstração de resultados das VENDAS: receita, descontos, notas, CMV,
 * lucro bruto e margem.
 *
 * CORRIGIDO A 27/09/2026 (pedido do cliente, TKT-000003):
 *
 *  · OS DESCONTOS NÃO ENTRAVAM NA RECEITA LÍQUIDA. Mostrava-se o desconto e
 *    calculava-se a receita sem ele (bruta − notas de crédito); a linha não
 *    reconciliava com nada. Agora: vendas − descontos + notas de débito −
 *    notas de crédito = receita líquida, sem IVA.
 *  · A EVOLUÇÃO MENSAL USAVA OUTRA BASE (o subtotal bruto, sem notas nem
 *    descontos) e o lucro de um mês não batia com o do quadro principal. Os
 *    dois saem agora das mesmas contas.
 *  · O CMV era a quantidade × o custo de HOJE do artigo — mudava meses
 *    passados sempre que se comprava mais caro. É o custo de compra à data
 *    da venda.
 *  · Os RASCUNHOS contavam como vendas.
 *
 * As contas vivem no `DreIntegrado` — este relatório, o DRE Integrado e a
 * evolução mensal dão o mesmo número para a mesma receita. As DESPESAS e o
 * resultado do período estão no DRE Integrado (Tesouraria › Relatórios).
 */
class LucrosEPerdas extends Base
{
    public function esquema(): array
    {
        return [
            'slug' => 'profit-loss',
            'titulo' => 'Lucros e Perdas (DRE)',
            'descricao' => 'Vendas, CMV e margem do período, sem IVA. O resultado com as despesas está no DRE Integrado (Tesouraria › Relatórios).',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [],
            'cartoes' => [
                self::cartao('Receita líquida', 'netRevenue', 'dinheiro', 'blue'),
                self::cartao('CMV', 'cogs', 'dinheiro', 'orange'),
                self::cartao('Lucro bruto', 'grossProfit', 'dinheiro', 'green'),
                self::cartao('Margem bruta', 'grossMargin', 'percentagem', 'purple'),
            ],
            'tabelas' => [
                ['titulo' => 'Demonstração de resultados', 'chave' => 'linhas', 'colunas' => [self::col('Rubrica', 'rotulo'), self::col('Valor', 'valor', 'dinheiro')]],
                ['titulo' => 'Últimos seis meses', 'chave' => 'monthly', 'colunas' => [self::col('Mês', 'label'), self::col('Receita líquida', 'revenue', 'dinheiro'), self::col('CMV', 'cogs', 'dinheiro'), self::col('Lucro bruto', 'profit', 'dinheiro')]],
            ],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        $dre = new DreIntegrado($tenantId, substr((string) $de, 0, 10), substr((string) $ate, 0, 10));
        $r = $dre->receita();
        $cmv = $dre->cmv();

        $grossRevenue = $r['vendas'];
        $discounts = $r['descontos'];
        $debits = $r['notas_debito'];
        $returns = $r['notas_credito'];
        $netRevenue = $r['receita_liquida'];
        $cogs = $cmv['liquido'];
        $grossProfit = round($netRevenue - $cogs, 2);
        $grossMargin = $netRevenue > 0 ? round($grossProfit / $netRevenue * 100, 1) : 0;

        // Evolução mensal (últimos 6 meses até ao fim do período) — as mesmas
        // contas do quadro principal.
        $monthly = [];
        $fim = \Carbon\Carbon::parse($ate)->endOfMonth();
        for ($i = 5; $i >= 0; $i--) {
            $month = $fim->copy()->subMonthsNoOverflow($i);
            $doMes = new DreIntegrado($tenantId, $month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString());
            $rev = $doMes->receita()['receita_liquida'];
            $cmvDoMes = $doMes->cmv()['liquido'];

            $monthly[] = [
                'label' => $month->translatedFormat('M/Y'),
                'revenue' => $rev,
                'cogs' => $cmvDoMes,
                'profit' => round($rev - $cmvDoMes, 2),
            ];
        }

        $linhas = [
            ['rotulo' => 'Vendas (sem IVA)', 'valor' => $grossRevenue],
            ['rotulo' => '− Descontos', 'valor' => -$discounts],
            ['rotulo' => '+ Notas de débito', 'valor' => $debits],
            ['rotulo' => '− Notas de crédito (devoluções e correcções)', 'valor' => -$returns],
            ['rotulo' => '= Receita líquida', 'valor' => $netRevenue],
            ['rotulo' => '− Custo das mercadorias vendidas (CMV)', 'valor' => -$cogs],
            ['rotulo' => '= Lucro bruto', 'valor' => $grossProfit],
        ];

        return compact('grossRevenue', 'discounts', 'debits', 'returns', 'netRevenue', 'cogs', 'grossProfit', 'grossMargin', 'monthly', 'linhas');
    }
}
