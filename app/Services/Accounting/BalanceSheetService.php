<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\MoveLine;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    /**
     * Gera o Balanço (Demonstração da Posição Financeira)
     * Conforme o SNC Angola
     * 
     * @param int $tenantId
     * @param string $dateEnd
     * @return array
     */
    public function generate($tenantId, $dateEnd)
    {
        $balanceSheet = [
            'activo' => $this->getActivo($tenantId, $dateEnd),
            'passivo' => $this->getPassivo($tenantId, $dateEnd),
            'capital_proprio' => $this->getCapitalProprio($tenantId, $dateEnd),
        ];
        
        // Totais
        $balanceSheet['total_activo'] = $this->sumGroup($balanceSheet['activo']);
        $balanceSheet['total_passivo'] = $this->sumGroup($balanceSheet['passivo']);
        $balanceSheet['total_capital_proprio'] = $this->sumGroup($balanceSheet['capital_proprio']);
        $balanceSheet['total_passivo_capital'] = $balanceSheet['total_passivo'] + $balanceSheet['total_capital_proprio'];
        
        // Validação: Activo = Passivo + Capital Próprio
        $balanceSheet['balanced'] = abs($balanceSheet['total_activo'] - $balanceSheet['total_passivo_capital']) < 0.01;
        $balanceSheet['difference'] = $balanceSheet['total_activo'] - $balanceSheet['total_passivo_capital'];
        
        return $balanceSheet;
    }
    
    /**
     * Obtém o Activo (Classe 1 e 2)
     */
    protected function getActivo($tenantId, $dateEnd)
    {
        return [
            'activo_nao_corrente' => [
                'label' => 'ACTIVO NÃO CORRENTE',
                'items' => [
                    'activos_fixos_tangiveis' => $this->getBalance($tenantId, $dateEnd, '43%'), // Imobilizações Corpóreas
                    'activos_intangiveis' => $this->getBalance($tenantId, $dateEnd, '44%'), // Imobilizações Incorpóreas
                    'investimentos_financeiros' => $this->getBalance($tenantId, $dateEnd, '41%'), // Investimentos Financeiros
                ],
            ],
            'activo_corrente' => [
                'label' => 'ACTIVO CORRENTE',
                'items' => [
                    'inventarios' => $this->getBalance($tenantId, $dateEnd, '3%'), // Inventários
                    'clientes' => $this->getBalance($tenantId, $dateEnd, '21%'), // Clientes
                    'estado' => $this->getBalance($tenantId, $dateEnd, '24%'), // Estado e Outros Entes Públicos
                    'outras_contas_receber' => $this->getBalance($tenantId, $dateEnd, '26%'), // Outras Contas a Receber
                    'caixa_bancos' => $this->getBalance($tenantId, $dateEnd, ['11%', '12%']), // Caixa + Depósitos Bancários
                ],
            ],
        ];
    }
    
    /**
     * Obtém o Passivo (Classe 5 - parcial)
     */
    protected function getPassivo($tenantId, $dateEnd)
    {
        return [
            'passivo_nao_corrente' => [
                'label' => 'PASSIVO NÃO CORRENTE',
                'items' => [
                    'emprestimos_longo_prazo' => $this->getBalance($tenantId, $dateEnd, '252%'), // Empréstimos de LP
                    'provisoes' => $this->getBalance($tenantId, $dateEnd, '29%'), // Provisões
                ],
            ],
            'passivo_corrente' => [
                'label' => 'PASSIVO CORRENTE',
                'items' => [
                    'fornecedores' => $this->getBalance($tenantId, $dateEnd, '22%'), // Fornecedores
                    'estado' => $this->getBalance($tenantId, $dateEnd, '23%'), // Estado e Outros Entes Públicos (a pagar)
                    'emprestimos_curto_prazo' => $this->getBalance($tenantId, $dateEnd, '251%'), // Empréstimos de CP
                    'outras_contas_pagar' => $this->getBalance($tenantId, $dateEnd, ['27%', '28%']), // Outras Contas a Pagar
                ],
            ],
        ];
    }
    
    /**
     * Obtém o Capital Próprio (Classe 5 - parcial)
     */
    protected function getCapitalProprio($tenantId, $dateEnd)
    {
        return [
            'capital_proprio' => [
                'label' => 'CAPITAL PRÓPRIO',
                'items' => [
                    'capital_social' => $this->getBalance($tenantId, $dateEnd, '51%'), // Capital
                    'acoes_quotas_proprias' => $this->getBalance($tenantId, $dateEnd, '52%'), // Acções/Quotas Próprias
                    'reservas' => $this->getBalance($tenantId, $dateEnd, ['54%', '55%']), // Reservas
                    'resultados_transitados' => $this->getBalance($tenantId, $dateEnd, '56%'), // Resultados Transitados
                    'resultado_liquido' => $this->getBalance($tenantId, $dateEnd, '81%'), // Resultado Líquido do Período
                ],
            ],
        ];
    }
    
    /**
     * Calcula o saldo de um conjunto de contas
     * 
     * @param int $tenantId
     * @param string $dateEnd
     * @param string|array $pattern
     * @return array
     */
    protected function getBalance($tenantId, $dateEnd, $pattern)
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];
        
        $query = Account::where('tenant_id', $tenantId)
            ->where(function($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('code', 'like', $p);
                }
            });
        
        $accounts = $query->with(['moveLines' => function($q) use ($dateEnd) {
            $q->whereHas('move', function($moveQuery) use ($dateEnd) {
                $moveQuery->where('state', 'posted')
                         ->where('date', '<=', $dateEnd);
            });
        }])->get();
        
        $total = 0;
        $details = [];
        
        foreach ($accounts as $account) {
            $debit = $account->moveLines->sum('debit');
            $credit = $account->moveLines->sum('credit');
            
            // Activo e Gastos: Débito - Crédito
            // Passivo e Rendimentos: Crédito - Débito
            $balance = $account->type === 'asset' || $account->type === 'expense'
                ? ($debit - $credit)
                : ($credit - $debit);
            
            if (abs($balance) > 0.01) {
                $details[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];
                $total += $balance;
            }
        }
        
        return [
            'total' => $total,
            'details' => $details,
            'count' => count($details),
        ];
    }
    
    /**
     * Soma os valores de um grupo
     */
    protected function sumGroup($group)
    {
        $total = 0;
        
        foreach ($group as $section) {
            if (isset($section['items'])) {
                foreach ($section['items'] as $item) {
                    $total += $item['total'] ?? 0;
                }
            }
        }
        
        return $total;
    }
    
    /**
     * Gera o Balanço Comparativo (dois períodos)
     * 
     * @param int $tenantId
     * @param string $dateEnd1
     * @param string $dateEnd2
     * @return array
     */
    public function generateComparative($tenantId, $dateEnd1, $dateEnd2)
    {
        $period1 = $this->generate($tenantId, $dateEnd1);
        $period2 = $this->generate($tenantId, $dateEnd2);
        
        return [
            'period1' => $period1,
            'period2' => $period2,
            'date1' => $dateEnd1,
            'date2' => $dateEnd2,
            'variance' => $this->calculateVariance($period1, $period2),
        ];
    }
    
    /**
     * Calcula variações entre dois períodos
     */
    protected function calculateVariance($period1, $period2)
    {
        return [
            'activo' => $period2['total_activo'] - $period1['total_activo'],
            'passivo' => $period2['total_passivo'] - $period1['total_passivo'],
            'capital_proprio' => $period2['total_capital_proprio'] - $period1['total_capital_proprio'],
            'activo_percent' => $period1['total_activo'] != 0 
                ? (($period2['total_activo'] - $period1['total_activo']) / abs($period1['total_activo'])) * 100 
                : 0,
            'passivo_percent' => $period1['total_passivo'] != 0 
                ? (($period2['total_passivo'] - $period1['total_passivo']) / abs($period1['total_passivo'])) * 100 
                : 0,
            'capital_proprio_percent' => $period1['total_capital_proprio'] != 0 
                ? (($period2['total_capital_proprio'] - $period1['total_capital_proprio']) / abs($period1['total_capital_proprio'])) * 100 
                : 0,
        ];
    }
}
