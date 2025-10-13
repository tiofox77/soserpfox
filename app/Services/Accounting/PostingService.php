<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Period;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PostingService
{
    /**
     * Gera lançamento contabilístico a partir de documento de venda
     * 
     * @param object $invoice Documento de venda (FT/FR/NC)
     * @return Move
     */
    public function postSalesInvoice($invoice)
    {
        return DB::transaction(function () use ($invoice) {
            $tenantId = $invoice->tenant_id;
            
            // Buscar diário de vendas
            $journal = Journal::where('tenant_id', $tenantId)
                ->where('type', 'sales')
                ->first();
            
            if (!$journal) {
                throw new \Exception('Diário de vendas não encontrado');
            }
            
            // Buscar período
            $period = Period::where('tenant_id', $tenantId)
                ->whereYear('date_start', '<=', $invoice->date)
                ->whereYear('date_end', '>=', $invoice->date)
                ->where('state', 'open')
                ->first();
            
            if (!$period) {
                throw new \Exception('Período contabilístico não encontrado ou fechado');
            }
            
            // Criar cabeçalho do lançamento
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'period_id' => $period->id,
                'date' => $invoice->date,
                'ref' => $invoice->number,
                'narration' => 'Venda ' . $invoice->number . ' - ' . ($invoice->customer_name ?? 'Cliente'),
                'state' => 'draft',
            ]);
            
            $lines = [];
            
            // Linha 1: Débito Clientes (total com IVA)
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '211'), // Clientes c/c
                'name' => 'Cliente: ' . ($invoice->customer_name ?? 'N/D'),
                'debit' => $invoice->total,
                'credit' => 0,
            ];
            
            // Linha 2: Crédito Vendas (base sem IVA)
            $baseAmount = $invoice->subtotal ?? ($invoice->total / 1.14); // Assumindo IVA 14%
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '71'), // Vendas de mercadorias
                'name' => 'Vendas - ' . $invoice->number,
                'debit' => 0,
                'credit' => $baseAmount,
            ];
            
            // Linha 3: Crédito IVA Liquidado
            $vatAmount = $invoice->total - $baseAmount;
            if ($vatAmount > 0) {
                $lines[] = [
                    'tenant_id' => $tenantId,
                    'move_id' => $move->id,
                    'account_id' => $this->getAccountByCode($tenantId, '2433'), // IVA Liquidado
                    'name' => 'IVA 14% - ' . $invoice->number,
                    'debit' => 0,
                    'credit' => $vatAmount,
                ];
            }
            
            // Criar linhas
            foreach ($lines as $lineData) {
                MoveLine::create($lineData);
            }
            
            // Postar automaticamente
            $move->update(['state' => 'posted']);
            
            return $move;
        });
    }
    
    /**
     * Gera lançamento de recebimento (RC)
     * 
     * @param object $receipt Recibo
     * @return Move
     */
    public function postReceipt($receipt)
    {
        return DB::transaction(function () use ($receipt) {
            $tenantId = $receipt->tenant_id;
            
            // Buscar diário
            $journal = Journal::where('tenant_id', $tenantId)
                ->where('type', 'cash')
                ->first();
            
            if (!$journal) {
                throw new \Exception('Diário de caixa não encontrado');
            }
            
            // Buscar período
            $period = Period::where('tenant_id', $tenantId)
                ->whereYear('date_start', '<=', $receipt->date)
                ->whereYear('date_end', '>=', $receipt->date)
                ->where('state', 'open')
                ->first();
            
            if (!$period) {
                throw new \Exception('Período contabilístico não encontrado ou fechado');
            }
            
            // Criar cabeçalho
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'period_id' => $period->id,
                'date' => $receipt->date,
                'ref' => $receipt->number,
                'narration' => 'Recebimento ' . $receipt->number,
                'state' => 'draft',
            ]);
            
            $lines = [];
            
            // Linha 1: Débito Caixa/Banco
            $accountCode = $receipt->payment_method === 'cash' ? '11' : '12'; // Caixa ou Banco
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, $accountCode),
                'name' => 'Recebimento de ' . ($receipt->customer_name ?? 'Cliente'),
                'debit' => $receipt->amount,
                'credit' => 0,
            ];
            
            // Linha 2: Crédito Clientes
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '211'), // Clientes c/c
                'name' => 'Recebimento de ' . ($receipt->customer_name ?? 'Cliente'),
                'debit' => 0,
                'credit' => $receipt->amount,
            ];
            
            // Criar linhas
            foreach ($lines as $lineData) {
                MoveLine::create($lineData);
            }
            
            // Postar
            $move->update(['state' => 'posted']);
            
            return $move;
        });
    }
    
    /**
     * Gera lançamento de compra
     * 
     * @param object $purchase Documento de compra
     * @return Move
     */
    public function postPurchase($purchase)
    {
        return DB::transaction(function () use ($purchase) {
            $tenantId = $purchase->tenant_id;
            
            // Buscar diário de compras
            $journal = Journal::where('tenant_id', $tenantId)
                ->where('type', 'purchases')
                ->first();
            
            if (!$journal) {
                throw new \Exception('Diário de compras não encontrado');
            }
            
            // Buscar período
            $period = Period::where('tenant_id', $tenantId)
                ->whereYear('date_start', '<=', $purchase->date)
                ->whereYear('date_end', '>=', $purchase->date)
                ->where('state', 'open')
                ->first();
            
            if (!$period) {
                throw new \Exception('Período contabilístico não encontrado ou fechado');
            }
            
            // Criar cabeçalho
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'period_id' => $period->id,
                'date' => $purchase->date,
                'ref' => $purchase->number ?? 'COMPRA-' . now()->format('YmdHis'),
                'narration' => 'Compra ' . ($purchase->supplier_name ?? 'Fornecedor'),
                'state' => 'draft',
            ]);
            
            $lines = [];
            $baseAmount = $purchase->subtotal ?? ($purchase->total / 1.14);
            $vatAmount = $purchase->total - $baseAmount;
            
            // Linha 1: Débito Compras/Gastos
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '61'), // Compras de mercadorias
                'name' => 'Compra - ' . ($purchase->supplier_name ?? 'N/D'),
                'debit' => $baseAmount,
                'credit' => 0,
            ];
            
            // Linha 2: Débito IVA Dedutível
            if ($vatAmount > 0) {
                $lines[] = [
                    'tenant_id' => $tenantId,
                    'move_id' => $move->id,
                    'account_id' => $this->getAccountByCode($tenantId, '2432'), // IVA Dedutível
                    'name' => 'IVA 14% - Compra',
                    'debit' => $vatAmount,
                    'credit' => 0,
                ];
            }
            
            // Linha 3: Crédito Fornecedores
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '221'), // Fornecedores c/c
                'name' => 'Fornecedor: ' . ($purchase->supplier_name ?? 'N/D'),
                'debit' => 0,
                'credit' => $purchase->total,
            ];
            
            // Criar linhas
            foreach ($lines as $lineData) {
                MoveLine::create($lineData);
            }
            
            // Postar
            $move->update(['state' => 'posted']);
            
            return $move;
        });
    }
    
    /**
     * Gera lançamento de pagamento
     * 
     * @param object $payment Pagamento
     * @return Move
     */
    public function postPayment($payment)
    {
        return DB::transaction(function () use ($payment) {
            $tenantId = $payment->tenant_id;
            
            // Buscar diário
            $journal = Journal::where('tenant_id', $tenantId)
                ->where('type', 'bank')
                ->first();
            
            if (!$journal) {
                throw new \Exception('Diário de banco não encontrado');
            }
            
            // Buscar período
            $period = Period::where('tenant_id', $tenantId)
                ->whereYear('date_start', '<=', $payment->date)
                ->whereYear('date_end', '>=', $payment->date)
                ->where('state', 'open')
                ->first();
            
            if (!$period) {
                throw new \Exception('Período contabilístico não encontrado ou fechado');
            }
            
            // Criar cabeçalho
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'period_id' => $period->id,
                'date' => $payment->date,
                'ref' => $payment->number,
                'narration' => 'Pagamento ' . $payment->number,
                'state' => 'draft',
            ]);
            
            $lines = [];
            
            // Linha 1: Débito Fornecedores
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, '221'), // Fornecedores c/c
                'name' => 'Pagamento a ' . ($payment->supplier_name ?? 'Fornecedor'),
                'debit' => $payment->amount,
                'credit' => 0,
            ];
            
            // Linha 2: Crédito Caixa/Banco
            $accountCode = $payment->payment_method === 'cash' ? '11' : '12';
            $lines[] = [
                'tenant_id' => $tenantId,
                'move_id' => $move->id,
                'account_id' => $this->getAccountByCode($tenantId, $accountCode),
                'name' => 'Pagamento a ' . ($payment->supplier_name ?? 'Fornecedor'),
                'debit' => 0,
                'credit' => $payment->amount,
            ];
            
            // Criar linhas
            foreach ($lines as $lineData) {
                MoveLine::create($lineData);
            }
            
            // Postar
            $move->update(['state' => 'posted']);
            
            return $move;
        });
    }
    
    /**
     * Busca conta por código
     * 
     * @param int $tenantId
     * @param string $code
     * @return int
     */
    protected function getAccountByCode($tenantId, $code)
    {
        $account = \App\Models\Accounting\Account::where('tenant_id', $tenantId)
            ->where('code', 'like', $code . '%')
            ->where('is_view', false)
            ->first();
        
        if (!$account) {
            throw new \Exception("Conta contabilística {$code} não encontrada");
        }
        
        return $account->id;
    }
    
    /**
     * Valida se um lançamento está balanceado
     * 
     * @param Move $move
     * @return bool
     */
    public function validateBalance(Move $move)
    {
        $totalDebit = $move->lines()->sum('debit');
        $totalCredit = $move->lines()->sum('credit');
        
        return abs($totalDebit - $totalCredit) < 0.01;
    }
}
