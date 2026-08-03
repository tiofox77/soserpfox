<?php

namespace App\Observers;

use App\Models\Accounting\Move;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\Log;

/**
 * Integração Faturação → Contabilidade para Notas de Crédito e de Débito.
 *
 * Não existia nenhuma: só a factura de venda e o recibo produziam lançamentos.
 * Uma NC emitida não reduzia o saldo de Clientes nem regularizava o IVA, e uma
 * ND não acrescia nada — os livros divergiam da facturação.
 *
 * As duas notas partilham este observer porque a estrutura é a mesma; o sentido
 * dos lançamentos é decidido no IntegrationService.
 *
 * Dispara quando a nota fica DEFINITIVA (invoice_status = 'F'), tal como a
 * factura: no create() ainda não há linhas nem totais fechados.
 */
class NoteAccountingObserver
{
    public function __construct(protected IntegrationService $integrationService)
    {
    }

    public function created(CreditNote|DebitNote $note): void
    {
        // Só se a nota já nascer completa. No fluxo normal ainda não tem linhas
        // neste ponto: os totais só fecham depois de copiar o IEC/IS da factura
        // de origem, e lançar aqui gravava um valor a menos (sem IEC nem Selo)
        // que a idempotência depois impedia de corrigir.
        if ($note->invoice_status === 'F' && (float) ($note->total ?: 0) > 0 && $note->items()->exists()) {
            $this->lancar($note);
        }
    }

    public function updated(CreditNote|DebitNote $note): void
    {
        // Só na transição para definitiva. As notas fecham os totais depois de
        // criar as linhas, pelo que é neste update que o lançamento é possível.
        if ($note->invoice_status !== 'F') {
            return;
        }

        if (!$note->wasChanged('invoice_status') && !$note->wasChanged(['total', 'gross_total'])) {
            return;
        }

        $this->lancar($note);
    }

    /**
     * Cria o lançamento uma única vez por nota. Nunca deixa a contabilidade
     * rebentar a emissão: o documento já está assinado e não pode ser revertido.
     */
    protected function lancar(CreditNote|DebitNote $note): void
    {
        try {
            if (!$this->integrationService->isEnabled($note->tenant_id)) {
                return;
            }

            $numero = $note instanceof CreditNote
                ? $note->credit_note_number
                : $note->debit_note_number;

            if (blank($numero) || (float) ($note->total ?: 0) <= 0) {
                return;
            }

            // Sem linhas os totais ainda não são definitivos
            if (!$note->items()->exists()) {
                return;
            }

            // Idempotência: a mesma nota não pode gerar dois movimentos
            if (Move::where('tenant_id', $note->tenant_id)->where('ref', $numero)->exists()) {
                return;
            }

            $move = $note instanceof CreditNote
                ? $this->integrationService->createMoveFromCreditNote($note)
                : $this->integrationService->createMoveFromDebitNote($note);

            if ($move) {
                Log::info('Lançamento contabilístico criado da nota', [
                    'document'  => class_basename($note),
                    'number'    => $numero,
                    'move_id'   => $move->id,
                    'tenant_id' => $note->tenant_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Falha ao integrar nota na contabilidade', [
                'document'  => class_basename($note),
                'id'        => $note->id,
                'tenant_id' => $note->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
