<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

class ArquivoDeEmpresaPeloDonoTest extends TenantTestCase
{
    public function test_empresa_sem_documento_fiscal_pode_ser_arquivada_pelo_dono(): void
    {
        $result = $this->tenant->canBeArchivedByOwner();

        $this->assertTrue($result['can_delete']);
        $this->assertSame(0, $result['invoices_count']);
    }

    public function test_factura_recibo_fiscal_impede_arquivo_pelo_dono(): void
    {
        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_number' => 'FR ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'status' => 'paid',
            'invoice_status' => 'F',
            'invoice_status_date' => now(),
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);

        $result = $this->tenant->canBeArchivedByOwner();

        $this->assertFalse($result['can_delete']);
        $this->assertSame(1, $result['invoices_count']);
        $this->assertArrayHasKey('facturas/FR', $result['encontrado']);
    }

    public function test_um_unico_rascunho_tambem_impede_arquivo_pelo_dono(): void
    {
        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_number' => 'DRAFT ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'invoice_status' => 'N',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);

        $result = $this->tenant->canBeArchivedByOwner();

        $this->assertFalse($result['can_delete']);
        $this->assertSame(1, $result['invoices_count']);
    }
}
