<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use Tests\TenantTestCase;

/**
 * O OLHO DA LISTA DE FACTURAS abre a ficha num modal.
 *
 * Apontava para `/invoicing/sales/invoices/{id}`, a página de ver do Livewire,
 * que saiu com ele: dava 404 (FR/000060 da Tecstore, 26/09/2026). A ficha usa
 * a rota dos documentos com o tipo `facturas-venda`, que existe SÓ para ler.
 */
class FichaDaFacturaDeVendaTest extends TenantTestCase
{
    private function frComDesconto(): SalesInvoice
    {
        // Como o balcão a grava: o desconto só no documento, e nas DUAS colunas.
        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_type' => 'FR',
            'invoice_number' => 'FR FICHA/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 289900,
            'net_total' => 274000,
            'gross_total' => 274000,
            'discount_amount' => 15900,
            'discount_commercial' => 15900,
            'total' => 274000,
            'created_by' => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_name' => 'Dell Latitude E7470', 'description' => 'Dell Latitude E7470',
            'quantity' => 1, 'unit' => 'UN', 'unit_price' => 289900, 'subtotal' => 289900,
            'tax_rate' => 0, 'tax_code' => 'ISE', 'tax_exemption_code' => 'M11', 'total' => 289900, 'order' => 1,
        ]);

        return $f;
    }

    public function test_a_ficha_da_factura_abre_com_o_desconto_uma_vez_so(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.sales.invoices.view');
        $f = $this->frComDesconto();

        $ficha = $this->getJson("/api/v1/invoicing/react/documentos/facturas-venda/{$f->id}")
            ->assertOk()
            ->json();

        $this->assertSame($f->invoice_number, $ficha['numero']);
        $this->assertCount(1, $ficha['linhas']);
        $this->assertEquals(274000, $ficha['totais']['total']);
        $this->assertEquals(15900, $ficha['totais']['desconto_comercial'],
            'o balcão grava o desconto em duas colunas; a ficha não o pode dobrar');
        $this->assertNotNull($ficha['fiscal'], 'é documento fiscal: leva o bloco da AGT');
    }

    public function test_sem_permissao_de_ver_facturas_nao_abre(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.credit-notes.view');
        $f = $this->frComDesconto();

        $this->getJson("/api/v1/invoicing/react/documentos/facturas-venda/{$f->id}")->assertForbidden();
    }

    public function test_facturas_venda_so_existe_para_ler(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.sales.invoices.delete');
        $f = $this->frComDesconto();

        // Nem a lista genérica nem o apagar genérico conhecem o tipo.
        $this->getJson('/api/v1/invoicing/react/documentos/facturas-venda')->assertNotFound();
        $this->deleteJson("/api/v1/invoicing/react/documentos/facturas-venda/{$f->id}")->assertNotFound();
        $this->assertNotNull(SalesInvoice::find($f->id));
    }
}
