<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * REABRIR UM DOCUMENTO NÃO É SÓ DE QUEM TEM `.edit`.
 *
 * O Vendedor cria facturas mas não tem `invoicing.sales.invoices.edit`: o
 * rascunho que ele próprio gravou dava 403 ao reabrir. E quem só vê recibos e
 * notas batia na mesma porta pelo «Abrir» da lista (auditoria de 2026-09-13).
 * As APIs dos editores já abrem com `.view` e recusam gravar o que foi emitido.
 */
class AbrirNoEditorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    public function test_o_vendedor_reabre_a_factura_que_criou(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create', 'invoicing.sales.invoices.view');

        $this->get('/invoicing/sales/invoices/1/edit')->assertOk();
    }

    public function test_quem_so_ve_recibos_e_notas_abre_os(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.credit-notes.view', 'invoicing.debit-notes.view');

        $this->get('/invoicing/receipts/1/edit')->assertOk();
        $this->get('/invoicing/credit-notes/1/edit')->assertOk();
        $this->get('/invoicing/debit-notes/1/edit')->assertOk();
    }

    public function test_sem_criar_nem_editar_a_factura_nao_abre_no_editor(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->get('/invoicing/sales/invoices/1/edit')->assertForbidden();
    }
}
