<?php

namespace App\Services\Accounting;

use App\Models\Accounting\MoveLine;
use Illuminate\Support\Facades\DB;

class IncomeStatementNatureService
{
    /**
     * Gera a Demonstração de Resultados por Natureza (DRN)
     * Conforme o SNC Angola
     * 
     * @param int $tenantId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function generate($tenantId, $dateFrom, $dateTo)
    {
        $drn = [
            // RENDIMENTOS E GASTOS
            'vendas_servicos' => $this->getBalance($tenantId, $dateFrom, $dateTo, '71%'), // Vendas
            'subsidios_exploracao' => $this->getBalance($tenantId, $dateFrom, $dateTo, '75%'), // Subsídios
            'variacoes_producao' => $this->getBalance($tenantId, $dateFrom, $dateTo, '73%'), // Variação Produção
            'trabalhos_propria_empresa' => $this->getBalance($tenantId, $dateFrom, $dateTo, '72%'), // Trabalhos
            'cmvmc' => $this->getBalance($tenantId, $dateFrom, $dateTo, '61%'), // CMVMC
            'fst' => $this->getBalance($tenantId, $dateFrom, $dateTo, '62%'), // FST
            'gastos_pessoal' => $this->getBalance($tenantId, $dateFrom, $dateTo, '63%'), // Pessoal
            'ajustamentos_inventarios' => $this->getBalance($tenantId, $dateFrom, $dateTo, ['652%', '653%']), // Ajustamentos
            'imparidades' => $this->getBalance($tenantId, $dateFrom, $dateTo, '65%'), // Imparidades
            'provisoes' => $this->getBalance($tenantId, $dateFrom, $dateTo, '67%'), // Provisões
            'depreciações' => $this->getBalance($tenantId, $dateFrom, $dateTo, '64%'), // Depreciações
            'outros_rendimentos' => $this->getBalance($tenantId, $dateFrom, $dateTo, '74%'), // Outros Rendimentos
            'outros_gastos' => $this->getBalance($tenantId, $dateFrom, $dateTo, '68%'), // Outros Gastos
            
            // RESULTADOS FINANCEIROS
            'juros_rendimentos_similares' => $this->getBalance($tenantId, $dateFrom, $dateTo, '79%'), // Juros e Rendimentos
            'juros_gastos_similares' => $this->getBalance($tenantId, $dateFrom, $dateTo, '69%'), // Juros e Gastos
            
            // IMPOSTO
            'imposto_rendimento' => $this->getBalance($tenantId, $dateFrom, $dateTo, '89%'), // IRC
        ];
        
        // CÁLCULOS
        $drn['resultado_bruto'] = $drn['vendas_servicos']['total'] 
                                 - $drn['cmvmc']['total'];
        
        $drn['resultado_operacional'] = $drn['vendas_servicos']['total']
                                       + $drn['subsidios_exploracao']['total']
                                       + $drn['variacoes_producao']['total']
                                       + $drn['trabalhos_propria_empresa']['total']
                                       - $drn['cmvmc']['total']
                                       - $drn['fst']['total']
                                       - $drn['gastos_pessoal']['total']
                                       - $drn['ajustamentos_inventarios']['total']
                                       - $drn['imparidades']['total']
                                       - $drn['provisoes']['total']
                                       - $drn['depreciações']['total']
                                       + $drn['outros_rendimentos']['total']
                                       - $drn['outros_gastos']['total'];
        
        $drn['resultado_antes_impostos'] = $drn['resultado_operacional']
                                          + $drn['juros_rendimentos_similares']['total']
                                          - $drn['juros_gastos_similares']['total'];
        
        $drn['resultado_liquido'] = $drn['resultado_antes_impostos']
                                   - $drn['imposto_rendimento']['total'];
        
        // INDICADORES
        $totalRendimentos = $drn['vendas_servicos']['total'] 
                          + $drn['subsidios_exploracao']['total']
                          + $drn['variacoes_producao']['total']
                          + $drn['trabalhos_propria_empresa']['total']
                          + $drn['outros_rendimentos']['total']
                          + $drn['juros_rendimentos_similares']['total'];
        
        $drn['margem_bruta_percent'] = $drn['vendas_servicos']['total'] > 0 
            ? ($drn['resultado_bruto'] / $drn['vendas_servicos']['total']) * 100 
            : 0;
        
        $drn['margem_operacional_percent'] = $totalRendimentos > 0 
            ? ($drn['resultado_operacional'] / $totalRendimentos) * 100 
            : 0;
        
        $drn['margem_liquida_percent'] = $totalRendimentos > 0 
            ? ($drn['resultado_liquido'] / $totalRendimentos) * 100 
            : 0;
        
        return $drn;
    }
    
    /**
     * Calcula o saldo de um conjunto de contas
     * Para Rendimentos (classe 7): Crédito - Débito
     * Para Gastos (classe 6): Débito - Crédito
     * 
     * @param int $tenantId
     * @param string $dateFrom
     * @param string $dateTo
     * @param string|array $pattern
     * @return array
     */
    protected function getBalance($tenantId, $dateFrom, $dateTo, $pattern)
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        
        $query = MoveLine::where('tenant_id', $tenantId)
            ->whereHas('account', function($q) use ($patterns) {
                $q->where(function($query) use ($patterns) {
                    foreach ($patterns as $p) {
                        $query->orWhere('code', 'like', $p);
                    }
                });
            })
            ->whereHas('move', function($q) use ($dateFrom, $dateTo) {
                $q->where('state', 'posted')
                  ->whereBetween('date', [$dateFrom, $dateTo]);
            })
            ->with('account');
        
        $lines = $query->get();
        
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        
        // Determinar se é Rendimento (7) ou Gasto (6)
        $firstPattern = is_array($pattern) ? $pattern[0] : $pattern;
        $isRevenue = str_starts_with($firstPattern, '7') || str_starts_with($firstPattern, '79');
        
        $total = $isRevenue 
            ? ($totalCredit - $totalDebit) 
            : ($totalDebit - $totalCredit);
        
        // Agrupar detalhes por conta de 2º nível
        $details = [];
        foreach ($lines as $line) {
            $key = substr($line->account->code, 0, 3); // Pega os 3 primeiros dígitos
            
            if (!isset($details[$key])) {
                $details[$key] = [
                    'code' => $key,
                    'name' => $line->account->name,
                    'debit' => 0,
                    'credit' => 0,
                    'balance' => 0,
                ];
            }
            
            $details[$key]['debit'] += $line->debit;
            $details[$key]['credit'] += $line->credit;
        }
        
        // Calcular balanços
        foreach ($details as $key => &$detail) {
            $detail['balance'] = $isRevenue 
                ? ($detail['credit'] - $detail['debit']) 
                : ($detail['debit'] - $detail['credit']);
        }
        
        return [
            'total' => $total,
            'debit' => $totalDebit,
            'credit' => $totalCredit,
            'details' => array_values($details),
            'count' => count($details),
        ];
    }
    
    /**
     * Gera DRN Comparativa (dois períodos)
     * 
     * @param int $tenantId
     * @param string $dateFrom1
     * @param string $dateTo1
     * @param string $dateFrom2
     * @param string $dateTo2
     * @return array
     */
    public function generateComparative($tenantId, $dateFrom1, $dateTo1, $dateFrom2, $dateTo2)
    {
        $period1 = $this->generate($tenantId, $dateFrom1, $dateTo1);
        $period2 = $this->generate($tenantId, $dateFrom2, $dateTo2);
        
        return [
            'period1' => $period1,
            'period2' => $period2,
            'variance' => $this->calculateVariance($period1, $period2),
        ];
    }
    
    /**
     * Calcula variações entre dois períodos
     */
    protected function calculateVariance($period1, $period2)
    {
        return [
            'resultado_bruto' => $period2['resultado_bruto'] - $period1['resultado_bruto'],
            'resultado_operacional' => $period2['resultado_operacional'] - $period1['resultado_operacional'],
            'resultado_liquido' => $period2['resultado_liquido'] - $period1['resultado_liquido'],
            'margem_bruta_percent' => $period2['margem_bruta_percent'] - $period1['margem_bruta_percent'],
            'margem_operacional_percent' => $period2['margem_operacional_percent'] - $period1['margem_operacional_percent'],
            'margem_liquida_percent' => $period2['margem_liquida_percent'] - $period1['margem_liquida_percent'],
        ];
    }
}
