<?php

namespace Tests\Feature\Seguranca;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * UMA NOTA DE CRÉDITO EMITIDA NÃO SE APAGA — anula-se.
 *
 * Apagar tirava-a do SAF-T (buraco na numeração) e deixava creditar a mesma
 * factura outra vez (auditoria de segurança de 2026-09-13).
 */
class DocumentoEmitidoNaoSeApagaTest extends TenantTestCase
{
    public function test_a_nota_de_credito_emitida_nao_se_elimina(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.credit-notes.view', 'invoicing.credit-notes.delete');

        $factura = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT SEG/0001', 'invoice_date' => now()->toDateString(),
            'status' => 'sent', 'total' => 5000, 'created_by' => $this->user->id,
        ]);

        $nota = CreditNote::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'credit_note_number' => 'NC SEG/0001', 'issue_date' => now()->toDateString(),
            'status' => 'issued', 'reason' => 'return', 'total' => 1000, 'created_by' => $this->user->id,
        ]);

        $this->deleteJson('/api/v1/invoicing/react/documentos/notas-credito/' . $nota->id)->assertStatus(422);
        $this->assertNotNull(CreditNote::find($nota->id), 'a nota continua lá');

        $rascunho = CreditNote::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'credit_note_number' => 'NC SEG/RASC', 'issue_date' => now()->toDateString(),
            'status' => 'draft', 'reason' => 'return', 'total' => 1000, 'created_by' => $this->user->id,
        ]);

        $this->deleteJson('/api/v1/invoicing/react/documentos/notas-credito/' . $rascunho->id)->assertOk();
    }
}
