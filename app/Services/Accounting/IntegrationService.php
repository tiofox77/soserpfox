<?php

namespace App\Services\Accounting;

use App\Models\Accounting\IntegrationMapping;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
    /**
     * Verificar se integração está ativada para o tenant
     */
    public function isEnabled($tenantId): bool
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        return $tenant && $tenant->accounting_integration_enabled;
    }
    
    /**
     * Criar lançamento contabilístico a partir de fatura
     */
    public function createMoveFromInvoice($invoice)
    {
        if (!$this->isEnabled($invoice->tenant_id)) {
            Log::info('Integração desativada para tenant', ['tenant_id' => $invoice->tenant_id]);
            return null;
        }
        
        $mapping = $this->getMappingForEvent('invoice', $invoice->tenant_id);
        
        if (!$mapping || !$mapping->active) {
            Log::warning('Mapeamento não encontrado ou inativo', ['event' => 'invoice']);
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            // Buscar período ativo
            $period = Period::where('tenant_id', $invoice->tenant_id)
                ->where('state', 'open')
                ->whereDate('date_start', '<=', $invoice->invoice_date)
                ->whereDate('date_end', '>=', $invoice->invoice_date)
                ->first();
            
            if (!$period) {
                Log::error('Período não encontrado para a data', ['date' => $invoice->invoice_date]);
                return null;
            }
            
            // Nome do cliente
            $clientName = $invoice->client ? $invoice->client->name : 'Cliente';
            
            // Criar Move
            $move = Move::create([
                'tenant_id' => $invoice->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $invoice->invoice_date,
                'ref' => $invoice->invoice_number,
                'narration' => "Fatura {$invoice->invoice_number} - {$clientName}",
                'state' => $mapping->auto_post ? 'posted' : 'draft',
            ]);
            
            // Débito: Clientes
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => "Cliente: {$clientName}",
                'debit' => $invoice->total,
                'credit' => 0,
            ]);
            
            // Crédito: Vendas
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => 'Vendas de Mercadorias',
                'debit' => 0,
                'credit' => $invoice->subtotal,
            ]);
            
            // Crédito: IVA
            if ($invoice->iva_amount > 0 && $mapping->vat_account_id) {
                MoveLine::create([
                    'move_id' => $move->id,
                    'account_id' => $mapping->vat_account_id,
                    'name' => 'IVA Liquidado',
                    'debit' => 0,
                    'credit' => $invoice->iva_amount,
                ]);
            }
            
            DB::commit();
            
            Log::info('Lançamento contabilístico criado da fatura', [
                'invoice_id' => $invoice->id,
                'move_id' => $move->id,
                'auto_posted' => $mapping->auto_post
            ]);
            
            return $move;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar lançamento da fatura', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Criar lançamento contabilístico a partir de recebimento
     */
    public function createMoveFromReceipt($receipt)
    {
        if (!$this->isEnabled($receipt->tenant_id)) {
            return null;
        }
        
        $event = $receipt->payment_method === 'cash' ? 'receipt_cash' : 'receipt_bank';
        $mapping = $this->getMappingForEvent($event, $receipt->tenant_id);
        
        if (!$mapping || !$mapping->active) {
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            $period = Period::where('tenant_id', $receipt->tenant_id)
                ->where('state', 'open')
                ->whereDate('date_start', '<=', $receipt->payment_date)
                ->whereDate('date_end', '>=', $receipt->payment_date)
                ->first();
            
            if (!$period) {
                return null;
            }
            
            $move = Move::create([
                'tenant_id' => $receipt->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $receipt->payment_date,
                'ref' => $receipt->receipt_number,
                'narration' => "Recebimento {$receipt->receipt_number}",
                'state' => $mapping->auto_post ? 'posted' : 'draft',
            ]);
            
            // Débito: Caixa/Banco
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => $receipt->payment_method === 'cash' ? 'Caixa' : 'Banco',
                'debit' => $receipt->amount_paid,
                'credit' => 0,
            ]);
            
            // Crédito: Clientes
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => "Recebimento de Cliente",
                'debit' => 0,
                'credit' => $receipt->amount_paid,
            ]);
            
            DB::commit();
            
            Log::info('Lançamento contabilístico criado do recebimento', [
                'receipt_id' => $receipt->id,
                'move_id' => $move->id
            ]);
            
            return $move;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar lançamento do recebimento', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Buscar mapeamento para evento
     */
    protected function getMappingForEvent($event, $tenantId)
    {
        return IntegrationMapping::where('tenant_id', $tenantId)
            ->where('event', $event)
            ->where('active', true)
            ->with(['journal', 'debitAccount', 'creditAccount', 'vatAccount'])
            ->first();
    }
}
