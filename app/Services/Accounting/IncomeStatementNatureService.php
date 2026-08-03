<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;

/**
 * Demonstração de Resultados por Naturezas.
 *
 * AGNÓSTICA AO PLANO: cada linha semântica é ancorada num `integration_key` colocado
 * na conta-mãe da respetiva rubrica (ex.: 'sales'→61, 'cogs'→71, 'payroll'→73). A soma
 * é feita sobre TODA a subárvore dessa conta, identificada pelo PREFIXO DE CÓDIGO
 * (ex.: 61 → 611, 6111...), convenção universal de planos de contas (SNC e PGC-AO).
 * Assim funciona quer a chave esteja na mãe (PGC-AO) quer numa folha (planos antigos),
 * e independentemente de `parent_id` estar ou não preenchido.
 *
 * Mantém as mesmas chaves de saída que a view consome. Linhas avançadas sem conta
 * mapeável (subsídios, variação de produção, imparidades, provisões, juros, imposto)
 * ficam a 0 até serem configuradas contas próprias.
 */
class IncomeStatementNatureService
{
    /** @var \Illuminate\Support\Collection contas do tenant com moveLines do período */
    protected $accounts;

    public function generate($tenantId, $dateFrom, $dateTo)
    {
        $zero = ['total' => 0, 'details' => [], 'count' => 0];

        // Carrega todas as contas do tenant UMA vez, com as linhas lançadas do período.
        $this->accounts = Account::where('tenant_id', $tenantId)
            ->with(['moveLines' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('move', function ($m) use ($dateFrom, $dateTo) {
                    $m->where('state', 'posted')->whereBetween('date', [$dateFrom, $dateTo]);
                });
            }])
            ->get();

        // Linhas semânticas (subárvore por integration_key)
        $vendasServicos = $this->byKeys(['sales', 'services']);
        $cmvmc          = $this->byKeys(['cogs']);
        $gastosPessoal  = $this->byKeys(['payroll']);
        $depreciacoes   = $this->byKeys(['depreciation']);

        // Residuais por type (o que não caiu nas subárvores acima)
        $outrosRendimentos = $this->byType('revenue', ['sales', 'services']);
        $fst = $this->byType('expense', ['cogs', 'payroll', 'depreciation']);

        $drn = [
            'vendas_servicos' => $vendasServicos,
            'subsidios_exploracao' => $zero,
            'variacoes_producao' => $zero,
            'trabalhos_propria_empresa' => $zero,
            'cmvmc' => $cmvmc,
            'fst' => $fst,
            'gastos_pessoal' => $gastosPessoal,
            'ajustamentos_inventarios' => $zero,
            'imparidades' => $zero,
            'provisoes' => $zero,
            'depreciações' => $depreciacoes,
            'outros_rendimentos' => $outrosRendimentos,
            'outros_gastos' => $zero,
            'juros_rendimentos_similares' => $zero,
            'juros_gastos_similares' => $zero,
            'imposto_rendimento' => $zero,
        ];

        $drn['resultado_bruto'] = $drn['vendas_servicos']['total'] - $drn['cmvmc']['total'];

        $drn['resultado_operacional'] = $drn['vendas_servicos']['total']
            + $drn['outros_rendimentos']['total']
            - $drn['cmvmc']['total']
            - $drn['fst']['total']
            - $drn['gastos_pessoal']['total']
            - $drn['depreciações']['total'];

        $drn['resultado_antes_impostos'] = $drn['resultado_operacional'];
        $drn['resultado_liquido'] = $drn['resultado_antes_impostos'] - $drn['imposto_rendimento']['total'];

        $totalRendimentos = $drn['vendas_servicos']['total'] + $drn['outros_rendimentos']['total'];

        $drn['margem_bruta_percent'] = $drn['vendas_servicos']['total'] > 0
            ? ($drn['resultado_bruto'] / $drn['vendas_servicos']['total']) * 100 : 0;
        $drn['margem_operacional_percent'] = $totalRendimentos > 0
            ? ($drn['resultado_operacional'] / $totalRendimentos) * 100 : 0;
        $drn['margem_liquida_percent'] = $totalRendimentos > 0
            ? ($drn['resultado_liquido'] / $totalRendimentos) * 100 : 0;

        return $drn;
    }

    /** Prefixos de código das contas-âncora com um dado integration_key. */
    protected function keyedPrefixes(array $keys): array
    {
        return $this->accounts
            ->whereIn('integration_key', $keys)
            ->pluck('code')
            ->all();
    }

    protected function matchesPrefix(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if ($p !== '' && str_starts_with($code, $p)) {
                return true;
            }
        }
        return false;
    }

    /** Soma a subárvore (por prefixo de código) das contas ancoradas nos integration_keys dados. */
    protected function byKeys(array $keys)
    {
        $prefixes = $this->keyedPrefixes($keys);
        if (empty($prefixes)) {
            return ['total' => 0, 'details' => [], 'count' => 0];
        }
        return $this->aggregate($this->accounts->filter(
            fn ($a) => !$a->is_view && $this->matchesPrefix((string) $a->code, $prefixes)
        ));
    }

    /** Soma contas de um dado type, excluindo as subárvores já contabilizadas noutras linhas. */
    protected function byType($type, array $excludeKeys = [])
    {
        $exclude = $this->keyedPrefixes($excludeKeys);
        return $this->aggregate($this->accounts->filter(
            fn ($a) => $a->type === $type && !$a->is_view && !$this->matchesPrefix((string) $a->code, $exclude)
        ));
    }

    /** Direção do saldo por type: asset/expense = Déb−Créd; revenue/liability/equity = Créd−Déb. */
    protected function aggregate($accounts)
    {
        $total = 0;
        $details = [];
        foreach ($accounts as $account) {
            $debit = $account->moveLines->sum('debit');
            $credit = $account->moveLines->sum('credit');
            $balance = in_array($account->type, ['asset', 'expense'], true) ? ($debit - $credit) : ($credit - $debit);
            if (abs($balance) > 0.01) {
                $details[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $balance];
                $total += $balance;
            }
        }
        return ['total' => $total, 'details' => $details, 'count' => count($details)];
    }

    public function generateComparative($tenantId, $dateFrom1, $dateTo1, $dateFrom2, $dateTo2)
    {
        $period1 = $this->generate($tenantId, $dateFrom1, $dateTo1);
        $period2 = $this->generate($tenantId, $dateFrom2, $dateTo2);

        return [
            'period1' => $period1,
            'period2' => $period2,
            'variance' => [
                'resultado_liquido' => $period2['resultado_liquido'] - $period1['resultado_liquido'],
                'resultado_operacional' => $period2['resultado_operacional'] - $period1['resultado_operacional'],
            ],
        ];
    }
}
