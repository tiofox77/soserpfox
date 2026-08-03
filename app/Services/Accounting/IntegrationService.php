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
        
        // Período ANTES de abrir a transacção.
        //
        // Estava lá dentro e o `return null` saía sem rollback: a transacção
        // ficava aberta. Como o observer corre dentro da transacção da venda
        // (POS), o nível ficava desacertado e o commit exterior deixava de
        // gravar — as vendas do POS desapareciam, consumindo o número da série.
        // Só se notou quando o mapeamento `invoice` passou a existir, porque
        // até aí a função saía antes de chegar ao beginTransaction.
        $period = Period::where('tenant_id', $invoice->tenant_id)
            ->where('state', 'open')
            ->whereDate('date_start', '<=', $invoice->invoice_date)
            ->whereDate('date_end', '>=', $invoice->invoice_date)
            ->first();

        if (!$period) {
            Log::warning('Contabilidade: sem período aberto para a data — factura gravada, lançamento por fazer', [
                'invoice_id' => $invoice->id,
                'date'       => $invoice->invoice_date,
                'tenant_id'  => $invoice->tenant_id,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Nome do cliente
            $clientName = $invoice->client ? $invoice->client->name : 'Cliente';

            // Totais: o modelo legado usava iva_amount, o SalesInvoice usa
            // tax_amount. Sem isto o IVA saía de fora e o lançamento não fechava.
            $totalDoc = (float) ($invoice->total ?? $invoice->gross_total ?? 0);
            $baseDoc  = (float) ($invoice->subtotal ?? $invoice->net_total ?? 0);
            $ivaDoc   = (float) ($invoice->iva_amount ?? $invoice->tax_amount ?? 0);

            // Partidas dobradas: débito tem de igualar crédito
            if (round($baseDoc + $ivaDoc, 2) !== round($totalDoc, 2)) {
                $baseDoc = round($totalDoc - $ivaDoc, 2);
            }
            
            // Criar Move
            $move = Move::create([
                'tenant_id' => $invoice->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $invoice->invoice_date,
                'ref' => $invoice->invoice_number,
                'narration' => "Fatura {$invoice->invoice_number} - {$clientName}",
                'state' => $mapping->auto_post ? 'posted' : 'draft',
                // A coluna era NOT NULL e ninguém a preenchia: o lançamento
                // automático rebentava. Quem emitiu o documento é o autor mais
                // fiel quando não há sessão (POS/PWA, API, jobs).
                'created_by' => auth()->id() ?? $invoice->created_by,
            ]);
            
            // Débito: Clientes
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => "Cliente: {$clientName}",
                'debit' => $totalDoc,
                'credit' => 0,
            ]);
            
            // Crédito: Vendas
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => 'Vendas de Mercadorias',
                'debit' => 0,
                'credit' => $baseDoc,
            ]);
            
            // Crédito: IVA
            if ($ivaDoc > 0 && $mapping->vat_account_id) {
                MoveLine::create([
                    'move_id' => $move->id,
                    'account_id' => $mapping->vat_account_id,
                    'name' => 'IVA Liquidado',
                    'debit' => 0,
                    'credit' => $ivaDoc,
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
        
        // Período ANTES da transacção — o `return null` lá dentro deixava-a
        // aberta e desacertava o nível da transacção de quem chamou.
        $period = Period::where('tenant_id', $receipt->tenant_id)
            ->where('state', 'open')
            ->whereDate('date_start', '<=', $receipt->payment_date)
            ->whereDate('date_end', '>=', $receipt->payment_date)
            ->first();

        if (!$period) {
            Log::warning('Contabilidade: sem período aberto para a data — recibo gravado, lançamento por fazer', [
                'receipt_id' => $receipt->id,
                'date'       => $receipt->payment_date,
                'tenant_id'  => $receipt->tenant_id,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $move = Move::create([
                'tenant_id' => $receipt->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $receipt->payment_date,
                'ref' => $receipt->receipt_number,
                'narration' => "Recebimento {$receipt->receipt_number}",
                'state' => $mapping->auto_post ? 'posted' : 'draft',
                'created_by' => auth()->id() ?? $receipt->created_by,
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
     * Criar lançamento contabilístico a partir de Nota de Crédito.
     *
     * A NC é a IMAGEM ESPELHADA da factura que credita: o que lá era débito
     * passa a crédito. Reduz a dívida do cliente (crédito em Clientes), anula o
     * proveito (débito em Vendas) e regulariza o IVA liquidado (débito em IVA).
     *
     * Não existia: uma NC emitida não produzia lançamento nenhum, pelo que as
     * devoluções ficavam fora da contabilidade e o saldo de Clientes inflava.
     */
    public function createMoveFromCreditNote($creditNote)
    {
        return $this->createMoveFromNote($creditNote, 'credit_note', true);
    }

    /**
     * Criar lançamento contabilístico a partir de Nota de Débito.
     *
     * A ND acresce à dívida, logo tem o MESMO sentido de uma factura: débito em
     * Clientes, crédito em Vendas e crédito em IVA liquidado.
     */
    public function createMoveFromDebitNote($debitNote)
    {
        return $this->createMoveFromNote($debitNote, 'debit_note', false);
    }

    /**
     * Lançamento comum às duas notas. $inverter troca os sentidos (NC).
     *
     * A base é derivada de total − imposto, e não do subtotal gravado: com IEC
     * ou Imposto de Selo na linha o subtotal não fecha com o total e as
     * partidas dobradas não batiam.
     */
    protected function createMoveFromNote($note, string $event, bool $inverter)
    {
        if (!$this->isEnabled($note->tenant_id)) {
            return null;
        }

        $mapping = $this->getMappingForEvent($event, $note->tenant_id);

        if (!$mapping || !$mapping->active) {
            Log::warning('Mapeamento não encontrado ou inativo', ['event' => $event]);
            return null;
        }

        $numero = $note->credit_note_number ?? $note->debit_note_number;
        $rotulo = $inverter ? 'Nota de Crédito' : 'Nota de Débito';

        // Período ANTES da transacção, pelo mesmo motivo das facturas: sair de
        // dentro dela desacerta o nível de quem chamou.
        $period = Period::where('tenant_id', $note->tenant_id)
            ->where('state', 'open')
            ->whereDate('date_start', '<=', $note->issue_date)
            ->whereDate('date_end', '>=', $note->issue_date)
            ->first();

        if (!$period) {
            Log::warning("Contabilidade: sem período aberto para a data — {$rotulo} gravada, lançamento por fazer", [
                'document_id' => $note->id,
                'date'        => $note->issue_date,
                'tenant_id'   => $note->tenant_id,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $clientName = $note->client->name ?? 'Cliente';

            // Imposto total: tax_payable inclui IEC e Selo; tax_amount só o IVA.
            $totalDoc = (float) ($note->total ?: $note->gross_total);
            $impostoDoc = (float) ($note->tax_payable ?: $note->tax_amount);
            $baseDoc = round($totalDoc - $impostoDoc, 2);

            $move = Move::create([
                'tenant_id' => $note->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $note->issue_date,
                'ref' => $numero,
                'narration' => "{$rotulo} {$numero} - {$clientName}"
                    . ($note->invoice ? " (ref. {$note->invoice->invoice_number})" : ''),
                'state' => $mapping->auto_post ? 'posted' : 'draft',
                'created_by' => auth()->id() ?? $note->created_by,
            ]);

            // Clientes: crédito na NC (reduz a dívida), débito na ND (acresce)
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => "Cliente: {$clientName}",
                'debit' => $inverter ? 0 : $totalDoc,
                'credit' => $inverter ? $totalDoc : 0,
            ]);

            // Vendas: débito na NC (anula o proveito), crédito na ND
            MoveLine::create([
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => $inverter ? 'Devoluções e abatimentos' : 'Vendas de Mercadorias',
                'debit' => $inverter ? $baseDoc : 0,
                'credit' => $inverter ? 0 : $baseDoc,
            ]);

            // IVA liquidado: regularizado a débito na NC, liquidado a crédito na ND
            if ($impostoDoc > 0 && $mapping->vat_account_id) {
                MoveLine::create([
                    'move_id' => $move->id,
                    'account_id' => $mapping->vat_account_id,
                    'name' => $inverter ? 'IVA Liquidado (regularização)' : 'IVA Liquidado',
                    'debit' => $inverter ? $impostoDoc : 0,
                    'credit' => $inverter ? 0 : $impostoDoc,
                ]);
            }

            DB::commit();

            Log::info("Lançamento contabilístico criado da {$rotulo}", [
                'document_id' => $note->id,
                'move_id' => $move->id,
                'auto_posted' => $mapping->auto_post,
            ]);

            return $move;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar lançamento da {$rotulo}", [
                'document_id' => $note->id,
                'error' => $e->getMessage(),
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
