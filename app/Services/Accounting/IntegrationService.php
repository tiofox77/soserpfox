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
            
            // Débito: Clientes — com o cliente na linha, para a conta-corrente
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => "Cliente: {$clientName}",
                'partner_id' => $invoice->client_id,
                'partner_type' => $invoice->client_id ? 'client' : null,
                'document_ref' => $invoice->invoice_number,
                'debit' => $totalDoc,
                'credit' => 0,
            ]);

            // Crédito: Vendas
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => 'Vendas de Mercadorias',
                'debit' => 0,
                'credit' => $baseDoc,
            ]);
            
            // Crédito: IVA
            if ($ivaDoc > 0 && $mapping->vat_account_id) {
                MoveLine::create([
                    'tenant_id' => $move->tenant_id,
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
        
        // Um recibo de COMPRA é um PAGAMENTO a um fornecedor, não um recebimento.
        //
        // Estava a usar sempre o mapeamento `receipt_*` (Dr Caixa/Banco, Cr
        // Clientes): pagar a um fornecedor creditava Clientes e fazia ENTRAR
        // dinheiro na caixa. O mapeamento certo (`payment_*`: Dr Fornecedores,
        // Cr Caixa/Banco) já existia, semeado e editável nas Definições, e nada
        // o usava.
        $deCompra = $receipt->type === 'purchase';
        $meio = $receipt->payment_method === 'cash' ? 'cash' : 'bank';
        $event = ($deCompra ? 'payment_' : 'receipt_') . $meio;
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
                'narration' => ($deCompra ? 'Pagamento ' : 'Recebimento ') . $receipt->receipt_number,
                'state' => $mapping->auto_post ? 'posted' : 'draft',
                'created_by' => auth()->id() ?? $receipt->created_by,
            ]);

            $meioNome = $meio === 'cash' ? 'Caixa' : 'Banco';

            // O terceiro vai na linha da conta de terceiros: no recebimento é o
            // crédito (Clientes), no pagamento é o débito (Fornecedores).
            $cliente = !$deCompra && $receipt->client_id
                ? ['partner_id' => $receipt->client_id, 'partner_type' => 'client'] : [];
            $fornecedor = $deCompra && $receipt->supplier_id
                ? ['partner_id' => $receipt->supplier_id, 'partner_type' => 'supplier'] : [];

            // Débito: Caixa/Banco (recebimento) ou Fornecedores (pagamento)
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => $deCompra ? 'Pagamento a Fornecedor' : $meioNome,
                'document_ref' => $receipt->receipt_number,
                'debit' => $receipt->amount_paid,
                'credit' => 0,
            ] + $fornecedor);

            // Crédito: Clientes (recebimento) ou Caixa/Banco (pagamento)
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => $deCompra ? $meioNome : 'Recebimento de Cliente',
                'document_ref' => $receipt->receipt_number,
                'debit' => 0,
                'credit' => $receipt->amount_paid,
            ] + $cliente);
            
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
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => "Cliente: {$clientName}",
                'partner_id' => $note->client_id,
                'partner_type' => $note->client_id ? 'client' : null,
                'document_ref' => $numero,
                'debit' => $inverter ? 0 : $totalDoc,
                'credit' => $inverter ? $totalDoc : 0,
            ]);

            // Vendas: débito na NC (anula o proveito), crédito na ND
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => $inverter ? 'Devoluções e abatimentos' : 'Vendas de Mercadorias',
                'debit' => $inverter ? $baseDoc : 0,
                'credit' => $inverter ? 0 : $baseDoc,
            ]);

            // IVA liquidado: regularizado a débito na NC, liquidado a crédito na ND
            if ($impostoDoc > 0 && $mapping->vat_account_id) {
                MoveLine::create([
                    'tenant_id' => $move->tenant_id,
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
     * Criar lançamento contabilístico a partir de uma FATURA DE COMPRA.
     *
     *   Dr Compras ....................... base (total − IVA)
     *   Dr IVA dedutível ................. IVA
     *     Cr Fornecedores ...................... total − IRT retido
     *     Cr Retenção na fonte (serviços) ...... IRT retido
     *
     * Não existia: o mapeamento `purchase` estava semeado e editável nas
     * Definições («Fatura de compra»), mas nenhum código o usava. As compras
     * nunca chegavam à contabilidade, e a conta de Fornecedores só recebia os
     * pagamentos — o saldo de cada fornecedor saía ao contrário.
     */
    public function createMoveFromPurchaseInvoice($invoice)
    {
        if (!$this->isEnabled($invoice->tenant_id)) {
            return null;
        }

        $mapping = $this->getMappingForEvent('purchase', $invoice->tenant_id);

        if (!$mapping || !$mapping->active) {
            Log::warning('Mapeamento não encontrado ou inativo', ['event' => 'purchase']);
            return null;
        }

        // Período ANTES da transacção, pelo mesmo motivo das facturas de venda.
        $period = Period::where('tenant_id', $invoice->tenant_id)
            ->where('state', 'open')
            ->whereDate('date_start', '<=', $invoice->invoice_date)
            ->whereDate('date_end', '>=', $invoice->invoice_date)
            ->first();

        if (!$period) {
            Log::warning('Contabilidade: sem período aberto para a data — fatura de compra gravada, lançamento por fazer', [
                'purchase_invoice_id' => $invoice->id,
                'date'                => $invoice->invoice_date,
                'tenant_id'           => $invoice->tenant_id,
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            $supplierName = $invoice->supplier->name ?? 'Fornecedor';

            $totalDoc = round((float) ($invoice->total ?? $invoice->gross_total ?? 0), 2);
            // Sem conta de IVA no mapeamento, o imposto fica no custo da compra
            // (é o que acontece a quem não deduz IVA — regime simplificado/isento).
            $ivaDoc = $mapping->vat_account_id ? round((float) ($invoice->tax_amount ?? 0), 2) : 0.0;
            $baseDoc = round($totalDoc - $ivaDoc, 2);

            // IRT retido ao fornecedor (serviços): o fornecedor recebe menos e a
            // diferença deve-se ao Estado. Sem conta de retenção no plano não se
            // separa — fica tudo em Fornecedores e deixa-se aviso.
            $irt = round((float) ($invoice->irt_amount ?? 0), 2);
            $contaIrt = null;
            if ($irt > 0) {
                $contaIrt = \App\Models\Accounting\Account::where('tenant_id', $invoice->tenant_id)
                    ->where('integration_key', 'withholding_services')
                    ->where('is_view', false)
                    ->value('id');
                if (!$contaIrt) {
                    Log::warning('Contabilidade: IRT retido numa compra sem conta de retenção (withholding_services) — fica em Fornecedores', [
                        'purchase_invoice_id' => $invoice->id,
                    ]);
                    $irt = 0.0;
                }
            }

            $move = Move::create([
                'tenant_id' => $invoice->tenant_id,
                'journal_id' => $mapping->journal_id,
                'period_id' => $period->id,
                'date' => $invoice->invoice_date,
                'ref' => $invoice->invoice_number,
                'narration' => "Fatura de compra {$invoice->invoice_number} - {$supplierName}",
                'state' => $mapping->auto_post ? 'posted' : 'draft',
                'created_by' => auth()->id() ?? $invoice->created_by,
            ]);

            // Débito: Compras
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->debit_account_id,
                'name' => 'Compras - ' . $invoice->invoice_number,
                'debit' => $baseDoc,
                'credit' => 0,
            ]);

            // Débito: IVA dedutível
            if ($ivaDoc > 0) {
                MoveLine::create([
                    'tenant_id' => $move->tenant_id,
                    'move_id' => $move->id,
                    'account_id' => $mapping->vat_account_id,
                    'name' => 'IVA Dedutível',
                    'debit' => $ivaDoc,
                    'credit' => 0,
                ]);
            }

            // Crédito: Fornecedores — com o fornecedor na linha, para a conta-corrente
            MoveLine::create([
                'tenant_id' => $move->tenant_id,
                'move_id' => $move->id,
                'account_id' => $mapping->credit_account_id,
                'name' => "Fornecedor: {$supplierName}",
                'partner_id' => $invoice->supplier_id,
                'partner_type' => $invoice->supplier_id ? 'supplier' : null,
                'document_ref' => $invoice->invoice_number,
                'debit' => 0,
                'credit' => round($totalDoc - $irt, 2),
            ]);

            // Crédito: Retenção na fonte
            if ($irt > 0) {
                MoveLine::create([
                    'tenant_id' => $move->tenant_id,
                    'move_id' => $move->id,
                    'account_id' => $contaIrt,
                    'name' => 'IRT retido - ' . $invoice->invoice_number,
                    'debit' => 0,
                    'credit' => $irt,
                ]);
            }

            DB::commit();

            Log::info('Lançamento contabilístico criado da fatura de compra', [
                'purchase_invoice_id' => $invoice->id,
                'move_id' => $move->id,
                'auto_posted' => $mapping->auto_post,
            ]);

            return $move;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar lançamento da fatura de compra', [
                'purchase_invoice_id' => $invoice->id,
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
